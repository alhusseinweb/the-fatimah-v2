<?php

// المسار: app/Http/Controllers/Frontend/BookingController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting; // <-- *** إضافة: استيراد موديل Setting ***
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

    /**
     * عرض تقويم الحجز للخدمة المحددة.
     */
    public function showCalendar(Service $service) : View
    {
        if (!$service->is_active) {
            abort(404, 'Service not available.');
        }
        $photographerWhatsApp = Setting::where('key', 'contact_whatsapp')->value('value');

        return view('frontend.booking.calendar', compact('service', 'photographerWhatsApp'));
    }


    /**
     * عرض نموذج تأكيد الحجز.
     */
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

         if ($bookingDateTime < Carbon::now()->subMinutes(5)) {
              return redirect()->route('booking.calendar', $service->id)
                              ->with('error', 'لا يمكن اختيار وقت في الماضي.');
         }

         // --- MODIFICATION START: Fetch payment method enabled status ---
         $bankTransferEnabledSetting = Setting::where('key', 'enable_bank_transfer')->first();
         $isBankTransferEnabled = $bankTransferEnabledSetting ? filter_var($bankTransferEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

         // نفترض أن المفتاح للتحكم في تمارا هو 'tamara_enabled' كما في تعديلات SettingController الأخيرة
         $tamaraEnabledSetting = Setting::where('key', 'tamara_enabled')->first();
         $isTamaraEnabled = $tamaraEnabledSetting ? filter_var($tamaraEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;
         // --- MODIFICATION END ---

         $bookingPolicyAr = Setting::where('key', 'policy_ar')->value('value');
         $bookingPolicyEn = Setting::where('key', 'policy_en')->value('value');
         $bankAccounts = $isBankTransferEnabled ? BankAccount::where('is_active', true)->get() : collect();


         return view('frontend.booking.form', [
             'service' => $service,
             'selectedDate' => $selectedDate,
             'selectedTime' => $selectedTime,
             'bookingDateTime' => $bookingDateTime,
             'bookingPolicyAr' => $bookingPolicyAr ?? '',
             'bookingPolicyEn' => $bookingPolicyEn ?? '',
             'bankAccounts' => $bankAccounts, // تم تحديثها لتكون مشروطة
             // --- MODIFICATION START: Pass flags to view ---
             'isBankTransferEnabled' => $isBankTransferEnabled,
             'isTamaraEnabled' => $isTamaraEnabled,
             // --- MODIFICATION END ---
         ]);
     }


    /**
     * استقبال بيانات نموذج الحجز، التحقق منها، وإنشاء الحجز والفاتورة.
     */
    public function submitBooking(Request $request): RedirectResponse
    {
        // --- MODIFICATION START: Fetch payment enabled status for validation ---
        $isBankTransferEnabled = filter_var(Setting::where('key', 'enable_bank_transfer')->value('value'), FILTER_VALIDATE_BOOLEAN);
        $isTamaraEnabled = filter_var(Setting::where('key', 'tamara_enabled')->value('value'), FILTER_VALIDATE_BOOLEAN);
        
        $availablePaymentMethods = [];
        if ($isTamaraEnabled) $availablePaymentMethods[] = 'tamara';
        if ($isBankTransferEnabled) $availablePaymentMethods[] = 'bank_transfer';
        // --- MODIFICATION END ---

        $validatedData = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'groom_name_en' => 'nullable|string|max:255',
            'bride_name_en' => 'nullable|string|max:255',
            'customer_notes' => 'nullable|string|max:1000',
            'discount_code' => ['nullable', 'string', Rule::exists('discount_codes', 'code')->where(function ($query) {
                 $query->where('is_active', true)
                       ->whereDate('start_date', '<=', Carbon::today())
                       ->where(function ($q) { $q->whereNull('end_date')->orWhereDate('end_date', '>=', Carbon::today()); });
             })],
            'agreed_to_policy' => 'required|accepted',
            'payment_option' => ['required', Rule::in(['full', 'down_payment'])],
            // --- MODIFICATION START: Validate against available methods ---
            'payment_method' => ['required', Rule::in($availablePaymentMethods)],
            // --- MODIFICATION END ---
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'discount_code.exists' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع (كامل أو عربون).',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
             'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً.', // رسالة جديدة
        ]);

        Log::debug('Booking Submit - After Validation:', $validatedData);

        $service = Service::findOrFail($validatedData['service_id']);
        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['date'] . ' ' . $validatedData['time']);
             if ($bookingDateTime < Carbon::now()->subMinutes(2)) {
                 return back()->withInput()->with('error', 'الوقت المختار أصبح في الماضي. يرجى اختيار وقت آخر.');
             }
        } catch (\Exception $e) {
             return back()->withInput()->with('error', 'التاريخ أو الوقت المحدد غير صحيح.');
        }

        $discountCode = null; $discountAmount = 0;
        $totalAmount = $service->price_sar; $finalTotalAmount = $totalAmount;
        $discountValueRaw = 0;
        $discountType = null;

        if (!empty($validatedData['discount_code'])) {
             $discountCode = DiscountCode::where('code', $validatedData['discount_code'])
                                         ->where('is_active', true)
                                         ->whereDate('start_date', '<=', Carbon::today())
                                         ->where(function ($q) { $q->whereNull('end_date')->orWhereDate('end_date', '>=', Carbon::today()); })
                                         ->first();
             if ($discountCode) {
                   if (!is_null($discountCode->max_uses) && $discountCode->current_uses >= $discountCode->max_uses) {
                       return back()->withInput()->withErrors(['discount_code' => 'لقد تم استخدام كود الخصم بالحد الأقصى.']);
                   }
                   $discountType = $discountCode->type;
                   $discountValueRaw = $discountCode->value;
                   if ($discountType === DiscountCode::TYPE_PERCENTAGE) {
                        $discountAmount = ($totalAmount * $discountValueRaw) / 100;
                   } elseif ($discountType === DiscountCode::TYPE_FIXED) {
                        $discountAmount = $discountValueRaw;
                   }
                   $discountAmount = min($discountAmount, $totalAmount);
                   $finalTotalAmount = $totalAmount - $discountAmount;
             } else {
                   return back()->withInput()->withErrors(['discount_code' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.']);
             }
        }
        $finalTotalAmount = round($finalTotalAmount, 2);

        $paymentOption = $validatedData['payment_option'];
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 2) : round($finalTotalAmount, 2);


        Log::debug('Booking Submit - Before Transaction:', [
            'service_price_before_discount' => $totalAmount,
            'discount_amount_calculated' => $discountAmount,
            'final_total_amount_after_discount' => $finalTotalAmount,
            'payment_option_selected' => $paymentOption,
            'amount_due_now_calculated' => $amountDueNow,
            'discount_code_applied' => $discountCode ? $discountCode->code : 'None'
        ]);

        $booking = null; $invoice = null; $user = Auth::user();
        if (!$user) {
             return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.');
        }

        try {
            DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCode, $finalTotalAmount, $paymentOption, $user, &$booking, &$invoice, $amountDueNow) {
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
                    'discount_code_id' => $discountCode?->id,
                    'down_payment_amount' => ($paymentOption === 'down_payment') ? $amountDueNow : null,
                ]);
                Log::debug('Booking Submit - Inside Transaction - Booking Created:', [
                    'booking_id' => $booking->id,
                    'stored_down_payment_amount' => $booking->down_payment_amount
                ]);

                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    'invoice_number' => 'INV-' . $booking->id . '-' . time(),
                    'amount' => $finalTotalAmount,
                    'currency' => 'SAR',
                    'status' => Invoice::STATUS_UNPAID,
                    'payment_method' => $validatedData['payment_method'],
                    'payment_option' => $paymentOption,
                    'due_date' => Carbon::today(), // أو $bookingDateTime
                ]);
                 Log::debug('Booking Submit - Inside Transaction - Invoice Created:', [
                    'invoice_id' => $invoice->id,
                    'invoice_total_amount' => $invoice->amount,
                    'saved_payment_option_on_invoice' => $invoice->payment_option
                ]);
                
                // ربط الفاتورة بالحجز
                $booking->invoice_id = $invoice->id;
                $booking->save();

                if ($discountCode) {
                     $discountCode->increment('current_uses');
                     Log::info("Discount code usage incremented.", ['code' => $discountCode->code]);
                }
                Log::info("Booking and Invoice created successfully in transaction.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id]);
            });

        } catch (\Exception $e) {
             Log::error('Booking Creation Transaction Failed: ' . $e->getMessage(), ['exception_class' => get_class($e), 'trace' => Str::limit($e->getTraceAsString(),1000)]);
             return back()->withInput()->with('error', 'حدث خطأ غير متوقع أثناء إنشاء الحجز. يرجى المحاولة مرة أخرى أو التواصل معنا.');
        }

        if ($booking && $user) {
            try {
                $paymentMethodForNotification = $validatedData['payment_method'];
                Log::info("Attempting to send BookingRequestReceived notification for Booking ID: {$booking->id}");
                $user->notify(new BookingRequestReceived($booking, $paymentMethodForNotification));
                Log::info("BookingRequestReceived notification queued for CUSTOMER for Booking ID: {$booking->id}");
                $admins = User::where('is_admin', true)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new BookingRequestReceived($booking, $paymentMethodForNotification));
                    Log::info("BookingRequestReceived notification queued for ADMIN {$admin->email} for Booking ID: {$booking->id}");
                }
            } catch (\Exception $e) {
                 Log::error("Failed to queue BookingRequestReceived notifications for Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($validatedData['payment_method'] === 'bank_transfer') {
             Log::info("Redirecting to pending page for bank transfer.", ['booking_id' => $booking->id]);
             return redirect()->route('booking.pending', $booking->id);
        }
        elseif ($validatedData['payment_method'] === 'tamara') {
             if (!$invoice || $amountDueNow <= 0.009) { // تأكد أن الفاتورة موجودة والمبلغ موجب
                Log::error("Tamara initiation skipped due to missing invoice or zero/negative amountDueNow.", [
                    'booking_id' => $booking?->id, 'invoice_id' => $invoice?->id, 'amount_due_now' => $amountDueNow ?? 'Not set'
                ]);
                return redirect()->route('booking.pending', $booking->id)
                                 ->with('error', 'حدث خطأ في تجهيز معلومات الدفع لتمارا. يرجى المحاولة لاحقاً.');
             }
             Log::info("Initiating Tamara checkout.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id, 'amount_due_to_send_tamara' => $amountDueNow, 'payment_option_for_tamara' => $paymentOption]);
             $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountDueNow, $paymentOption);
             if ($checkoutResponse && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['order_id'])) {
                  $invoice->payment_gateway_ref = $checkoutResponse['order_id'];
                  $invoice->save();
                  Log::info("Tamara checkout URL obtained. Redirecting user.", ['invoice_id' => $invoice->id, 'tamara_order_id' => $checkoutResponse['order_id']]);
                  return redirect()->away($checkoutResponse['checkout_url']);
             } else {
                  Log::error("Tamara initiateCheckout failed in BookingController.", ['invoice_id' => $invoice->id, 'response_from_tamara_service' => $checkoutResponse]);
                  // --- MODIFICATION: Redirect to pending page even if Tamara fails to initiate ---
                  return redirect()->route('booking.pending', $booking->id)
                                 ->with('error', 'لم نتمكن من بدء الدفع مع تمارا حالياً. يمكنك محاولة الدفع من صفحة الحجز المعلق أو اختيار طريقة دفع أخرى إذا كانت متاحة.');
                  // --- MODIFICATION END ---
             }
        }

        Log::error('Unknown payment method after validation in submitBooking.', ['method' => $validatedData['payment_method']]);
        // --- MODIFICATION: Redirect to pending page if payment method is somehow invalid after validation ---
        return redirect()->route('booking.pending', $booking->id)
                        ->with('error', 'حدث خطأ غير متوقع في طريقة الدفع المختارة. يرجى مراجعة طلبك.');
        // --- MODIFICATION END ---
    }

     public function showPendingPage(Booking $booking) : View|RedirectResponse
     {
          if(Auth::id() !== $booking->user_id && !(Auth::user() && Auth::user()->is_admin)) {
            Log::warning("Unauthorized attempt to view pending page.", ['booking_id' => $booking->id, 'user_id' => Auth::id()]);
            return redirect()->route('home')->with('error', 'غير مصرح لك بعرض هذا الحجز.');
          }

          $booking->load(['service', 'invoice.payments']);
          $bankAccounts = BankAccount::where('is_active', true)->get(); // جلب الحسابات دائماً إذا كانت الصفحة تعرضها
          $invoice = $booking->invoice;
          
          $amountDueNowOnPending = 0.0;
          $paymentOptionOnPending = $invoice?->payment_option ?? 'full';

          if ($invoice) {
            if ($invoice->status == Invoice::STATUS_UNPAID) {
                $amountDueNowOnPending = ($paymentOptionOnPending === 'down_payment' && $booking->down_payment_amount > 0)
                                        ? $booking->down_payment_amount
                                        : $invoice->amount;
            } elseif ($invoice->status == Invoice::STATUS_PARTIALLY_PAID) {
                $amountDueNowOnPending = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : 0.0;
            } elseif ($invoice->status == Invoice::STATUS_PAID) {
                $amountDueNowOnPending = 0.0;
            }
            $amountDueNowOnPending = round($amountDueNowOnPending, 2);
          }

          // جلب إعدادات تفعيل طرق الدفع لعرضها بشكل شرطي في صفحة pending إذا لزم الأمر
          $isBankTransferEnabled = filter_var(Setting::where('key', 'enable_bank_transfer')->value('value'), FILTER_VALIDATE_BOOLEAN);
          $isTamaraEnabled = filter_var(Setting::where('key', 'tamara_enabled')->value('value'), FILTER_VALIDATE_BOOLEAN);


          return view('frontend.booking.pending', compact(
              'booking', 
              'bankAccounts', 
              'amountDueNowOnPending', 
              'paymentOptionOnPending',
              'isBankTransferEnabled', // تمرير لإمكانية عرض خيار التحويل البنكي
              'isTamaraEnabled'      // تمرير لإمكانية عرض خيار تمارا للدفع المتبقي
            ));
     }
}
