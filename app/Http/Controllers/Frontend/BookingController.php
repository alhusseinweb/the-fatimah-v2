<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Services\TamaraService;
use App\Notifications\BookingRequestReceived;
use App\Models\User;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    protected AvailabilityService $availabilityService;
    protected TamaraService $tamaraService;

    public function __construct(
        AvailabilityService $availabilityService,
        TamaraService $tamaraService
    ) {
        $this->availabilityService = $availabilityService;
        $this->tamaraService = $tamaraService;
    }

    public function showCalendar(Service $service) : View
    {
        if (!$service->is_active) {
            abort(404, 'Service not available.');
        }
        $photographerWhatsApp = Setting::where('key', 'contact_whatsapp')->value('value');

        return view('frontend.booking.calendar', compact('service', 'photographerWhatsApp'));
    }

     public function showBookingForm(Request $request): View|RedirectResponse
     {
         $validator = Validator::make($request->all(), [
             'service_id' => 'required|integer|exists:services,id',
             'date' => 'required|date_format:Y-m-d',
             'time' => 'required|date_format:H:i',
         ]);

         if ($validator->fails()) {
             return redirect()->route('services.index')
                             ->with('error', 'رابط الحجز غير صحيح أو منتهي الصلاحية.');
         }

         $service = Service::find($request->query('service_id'));
         if (!$service || !$service->is_active) {
              return redirect()->route('services.index')
                              ->with('error', 'الخدمة المطلوبة غير متاحة.');
         }

         $selectedDate = $request->query('date');
         $selectedTime = $request->query('time');
         try {
             $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $selectedDate . ' ' . $selectedTime);
         } catch (\Exception $e) {
              return redirect()->route('booking.calendar', $service->id)
                              ->with('error', 'الوقت المحدد غير صحيح.');
         }

         if ($bookingDateTime < Carbon::now()->subMinutes(1)) {
              return redirect()->route('booking.calendar', $service->id)
                              ->with('error', 'لا يمكن اختيار وقت في الماضي.');
         }

         $settingsAll = Setting::pluck('value', 'key')->all(); // جلب جميع الإعدادات مرة واحدة
         $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
         if (!$isBankTransferEnabled && !$isTamaraEnabled) {
            Log::warning("Booking form accessed but no payment methods are enabled. Service ID: {$service->id}");
         }

         $bookingPolicyAr = $settingsAll['policy_ar'] ?? '';
         $bookingPolicyEn = $settingsAll['policy_en'] ?? '';
         $bankAccounts = $isBankTransferEnabled ? BankAccount::where('is_active', true)->get() : collect();


         return view('frontend.booking.form', [
             'service' => $service,
             'selectedDate' => $selectedDate,
             'selectedTime' => $selectedTime,
             'bookingDateTime' => $bookingDateTime,
             'bookingPolicyAr' => $bookingPolicyAr,
             'bookingPolicyEn' => $bookingPolicyEn,
             'bankAccounts' => $bankAccounts,
             'isBankTransferEnabled' => $isBankTransferEnabled,
             'isTamaraEnabled' => $isTamaraEnabled,
             'settingsHomepage' => $settingsAll, // تمرير الإعدادات لاستخدامها في Blade
         ]);
     }


    public function submitBooking(Request $request): RedirectResponse
    {
        $settingsAll = Setting::pluck('value', 'key')->all();
        $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $outsideAhsaFeeFromSettings = (float)($settingsAll['outside_ahsa_fee'] ?? 300.00);
        
        $availablePaymentMethodsServer = [];
        if ($isTamaraEnabled) $availablePaymentMethodsServer[] = 'tamara';
        if ($isBankTransferEnabled) $availablePaymentMethodsServer[] = 'bank_transfer';
        
        $noPaymentMethodEnabled = empty($availablePaymentMethodsServer);
        $outsideAhsaCitiesKeys = ['الخبر', 'الظهران', 'الدمام', 'سيهات', 'القطيف'];


        $validatedData = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:' . Carbon::today()->toDateString(),
            'time' => 'required|date_format:H:i',
            'shooting_area_option' => ['required', Rule::in(['inside_ahsa', 'outside_ahsa'])],
            'outside_ahs_city' => [
                Rule::requiredIf(fn () => $request->input('shooting_area_option') === 'outside_ahsa'),
                'nullable', 
                Rule::in($outsideAhsaCitiesKeys)
            ],
            'event_location' => 'nullable|string|max:255',
            'groom_name_en' => 'nullable|string|max:255',
            'bride_name_en' => 'nullable|string|max:255',
            'customer_notes' => 'nullable|string|max:1000',
            'discount_code' => ['nullable', 'string', 'max:50'],
            'agreed_to_policy' => 'required|accepted',
            'payment_option' => ['required', Rule::in(['full', 'down_payment'])],
            'payment_method' => $noPaymentMethodEnabled ? 'nullable|string' : ['required', Rule::in($availablePaymentMethodsServer)],
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع.',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
             'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً.',
             'shooting_area_option.required' => 'الرجاء اختيار منطقة التصوير.',
             'outside_ahs_city.required' => 'الرجاء اختيار المدينة عند تحديد منطقة خارج الأحساء.',
             'outside_ahs_city.in' => 'المدينة المختارة غير صالحة.',
        ]);
        
        if ($noPaymentMethodEnabled && $request->input('payment_method') !== 'manual_confirmation_due_to_no_gateway') {
            return back()->withInput()->with('error', 'عذراً، لا توجد طرق دفع متاحة حالياً لإتمام هذا الطلب.');
        }
        if ($noPaymentMethodEnabled) {
            $validatedData['payment_method'] = 'manual_confirmation_due_to_no_gateway';
        }

        Log::debug('Booking Submit - After Basic Validation (including location):', $validatedData);

        $service = Service::findOrFail($validatedData['service_id']);
        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['date'] . ' ' . $validatedData['time']);
             if ($bookingDateTime < Carbon::now()->subMinutes(1)) {
                 return back()->withInput()->with('error', 'الوقت المختار أصبح في الماضي. يرجى اختيار وقت آخر.');
             }
        } catch (\Exception $e) {
             return back()->withInput()->with('error', 'التاريخ أو الوقت المحدد غير صحيح.');
        }

        $originalServicePrice = (float) $service->price_sar;
        $priceAfterDiscount = $originalServicePrice;
        $discountCodeModel = null;
        $discountAmountApplied = 0.0;
        $isDiscountActuallyApplied = false;

        if (!empty($validatedData['discount_code'])) {
             $discountCodeModel = DiscountCode::where('code', $validatedData['discount_code'])->active()->first();
            
             if (!$discountCodeModel) {
                 return back()->withInput()->withErrors(['discount_code' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.']);
             }

             if (!is_null($discountCodeModel->max_uses) && $discountCodeModel->current_uses >= $discountCodeModel->max_uses) {
                 return back()->withInput()->withErrors(['discount_code' => 'لقد تم استخدام كود الخصم بالحد الأقصى.']);
             }

             $discountConditionsMet = true;
             if (!empty($discountCodeModel->allowed_payment_methods)) {
                 if (!in_array($validatedData['payment_method'], $discountCodeModel->allowed_payment_methods)) {
                     $discountConditionsMet = false;
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' NOT valid for payment method '{$validatedData['payment_method']}'.");
                 }
             }

             if ($discountConditionsMet && ($discountCodeModel->applicable_from_time || $discountCodeModel->applicable_to_time)) {
                 $bookingTimeOnlyCarbon = Carbon::parse($bookingDateTime->format('H:i:s'));
                 $applicableFrom = $discountCodeModel->applicable_from_time ? Carbon::parse($discountCodeModel->applicable_from_time) : null;
                 $applicableTo = $discountCodeModel->applicable_to_time ? Carbon::parse($discountCodeModel->applicable_to_time) : null;

                 if (($applicableFrom && $bookingTimeOnlyCarbon->lt($applicableFrom)) || ($applicableTo && $bookingTimeOnlyCarbon->gt($applicableTo))) {
                     $discountConditionsMet = false;
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' NOT valid for time {$bookingTimeOnlyCarbon->toTimeString()}.");
                 }
             }

             if ($discountConditionsMet) {
                 $tempDiscountAmount = 0.0;
                 if ($discountCodeModel->type === DiscountCode::TYPE_PERCENTAGE) {
                     $tempDiscountAmount = ($originalServicePrice * $discountCodeModel->value) / 100;
                 } elseif ($discountCodeModel->type === DiscountCode::TYPE_FIXED) {
                     $tempDiscountAmount = $discountCodeModel->value;
                 }
                 $discountAmountApplied = round(max(0, min($tempDiscountAmount, $originalServicePrice)), 2);
                 $priceAfterDiscount = $originalServicePrice - $discountAmountApplied;
                 $isDiscountActuallyApplied = true;
                 Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' conditions met. Calculated discount: {$discountAmountApplied}");
             } else {
                // إذا لم تتحقق الشروط، لا يتم تطبيق الخصم ولكن لا نرجع خطأ، فقط لا نطبقه
                Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' conditions NOT met. Discount not applied.");
             }
        }
        $priceAfterDiscount = round(max(0, $priceAfterDiscount), 2);

        $currentOutsideLocationFeeApplied = 0.0;
        if ($validatedData['shooting_area_option'] === 'outside_ahsa') {
            $currentOutsideLocationFeeApplied = $outsideAhsaFeeFromSettings;
        }
        $finalTotalAmount = $priceAfterDiscount + $currentOutsideLocationFeeApplied;
        $finalTotalAmount = round(max(0, $finalTotalAmount), 2);


        $paymentOption = $validatedData['payment_option'];
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 2) : $finalTotalAmount;
        $amountDueNow = max(0.01, round($amountDueNow, 2));

        Log::debug('Booking Submit - Price Calculation Details:', [ /* ... */ ]);
        
        $user = Auth::user();
        if (!$user) { return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.'); }

        $booking = null; $invoice = null; // تعريف المتغيرات خارج الـ transaction

        DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCodeModel, $finalTotalAmount, $paymentOption, $user, &$booking, &$invoice, $amountDueNow, $isDiscountActuallyApplied, $currentOutsideLocationFeeApplied, $discountAmountApplied) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'booking_datetime' => $bookingDateTime,
                'status' => Booking::STATUS_PENDING,
                'event_location' => $validatedData['event_location'],
                'groom_name_en' => $validatedData['groom_name_en'],
                'bride_name_en' => $validatedData['bride_name_en'],
                'customer_notes' => $validatedData['customer_notes'],
                'agreed_to_policy' => true,
                'discount_code_id' => $isDiscountActuallyApplied ? $discountCodeModel->id : null,
                'down_payment_amount' => ($paymentOption === 'down_payment') ? $amountDueNow : null,
                'shooting_area' => $validatedData['shooting_area_option'],
                'outside_location_city' => ($validatedData['shooting_area_option'] === 'outside_ahsa') ? $validatedData['outside_ahs_city'] : null,
                'outside_location_fee_applied' => ($currentOutsideLocationFeeApplied > 0) ? $currentOutsideLocationFeeApplied : null,
            ]);

            $invoiceStatus = ($validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway')
                           ? Invoice::STATUS_PENDING_CONFIRMATION
                           : Invoice::STATUS_UNPAID;

            $invoice = Invoice::create([ /* ... */ ]); // كما في ردك السابق
            
            $booking->invoice_id = $invoice->id;
            $booking->save();

            if ($isDiscountActuallyApplied && $discountCodeModel) {
                 $discountCodeModel->increment('current_uses');
            }
        });
        
        if (!$booking || !$invoice) { // تحقق إضافي
            Log::error('Booking or Invoice object is null after transaction.', ['booking_id' => $booking?->id, 'invoice_id' => $invoice?->id]);
            return back()->withInput()->with('error', 'حدث خطأ جسيم أثناء إنشاء الحجز. الرجاء التواصل مع الدعم.');
        }

        if ($booking && $user && $invoice) { /* ... (منطق الإشعارات) ... */ }
        if ($validatedData['payment_method'] === 'bank_transfer' || $validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway') { /* ... */ }
        elseif ($validatedData['payment_method'] === 'tamara') { /* ... (منطق تمارا) ... */ }
        
        return redirect()->route('booking.pending', $booking->id);
    }

    public function showPendingPage(Booking $booking) : View|RedirectResponse
    {
        // ... (التحقق من صلاحية المستخدم كما هو) ...
         $booking->load(['service', 'invoice.payments', 'discountCode']);
         $bankAccounts = BankAccount::where('is_active', true)->get();
         $invoice = $booking->invoice;
         
         $amountDueNowOnPending = 0.0;
         $paymentOptionOnPending = $invoice?->payment_option ?? 'full';
         $invoiceTotalAmount = $invoice?->amount ?? 0;

         if ($invoice) {
            // ... (حساب $amountDueNowOnPending كما هو) ...
         }

         $settingsAll = Setting::pluck('value', 'key')->all();
         $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

         return view('frontend.booking.pending', compact(
             'booking', 'bankAccounts', 'amountDueNowOnPending', 
             'paymentOptionOnPending', 'invoiceTotalAmount', 
             'isBankTransferEnabled', 'isTamaraEnabled'
           ));
    }
}
