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
// تم استيراد AddOnService في رد سابق، وهو صحيح.

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

         $settingsAll = Setting::pluck('value', 'key')->all();
         $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isPaylinkEnabled = filter_var($settingsAll['paylink_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
         if (!$isBankTransferEnabled && !$isTamaraEnabled && !$isPaylinkEnabled) {
            Log::warning("Booking form accessed but no payment methods are enabled. Service ID: {$service->id}");
         }

         $bookingPolicyAr = $settingsAll['policy_ar'] ?? '';
         $bookingPolicyEn = $settingsAll['policy_en'] ?? '';
         $bankAccounts = $isBankTransferEnabled ? BankAccount::where('is_active', true)->get() : collect();

         // --- MODIFICATION START: Fetch Add-on Services applicable to the current main service ---
         // تم تعديل طريقة جلب الخدمات الإضافية لتعتمد على الخدمة الرئيسية الحالية
         $addOnServices = $service->availableAddOns()->orderBy('name_ar')->get();
         // --- MODIFICATION END ---

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
             'isPaylinkEnabled' => $isPaylinkEnabled,
             'settingsHomepage' => $settingsAll,
             'addOnServices' => $addOnServices,
         ]);
     }


    public function submitBooking(Request $request): RedirectResponse
    {
        $settingsAll = Setting::pluck('value', 'key')->all();
        $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isPaylinkEnabled = filter_var($settingsAll['paylink_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $outsideAhsaFeeFromSettings = (float)($settingsAll['outside_ahsa_fee'] ?? 300.00);
        
        $availablePaymentMethodsServer = [];
        if ($isTamaraEnabled) $availablePaymentMethodsServer[] = 'tamara';
        if ($isPaylinkEnabled) $availablePaymentMethodsServer[] = 'paylink';
        if ($isBankTransferEnabled) $availablePaymentMethodsServer[] = 'bank_transfer';
        
        $noPaymentMethodEnabled = empty($availablePaymentMethodsServer);
        $outsideAhsaCitiesKeys = ['الخبر', 'الظهران', 'الدمام', 'بقيق', 'العيون', 'سيهات', 'القطيف'];

        // --- MODIFICATION START: Update validation rules for add_on_services ---
        $mainServiceForValidation = Service::find($request->input('service_id'));
        $allowedAddOnIds = $mainServiceForValidation ? $mainServiceForValidation->availableAddOns()->pluck('add_on_services.id')->toArray() : [];
        // --- MODIFICATION END ---

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
            // --- MODIFICATION START: Update validation for Add-on Services to check against allowed IDs ---
            'add_on_services' => 'nullable|array',
            'add_on_services.*' => ['integer', Rule::in($allowedAddOnIds)],
            // --- MODIFICATION END ---
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع.',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
             'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً.',
             'shooting_area_option.required' => 'الرجاء اختيار منطقة التصوير.',
             'outside_ahs_city.required' => 'الرجاء اختيار المدينة عند تحديد منطقة خارج الأحساء.',
             'outside_ahs_city.in' => 'المدينة المختارة غير صالحة.',
             'add_on_services.array' => 'اختيار الخدمات الإضافية غير صحيح.',
             'add_on_services.*.integer' => 'أحد الخدمات الإضافية المختارة غير صحيح.',
             // --- MODIFICATION START: Update validation message for add_on_services.*.in ---
             'add_on_services.*.in' => 'أحد الخدمات الإضافية المختارة غير متاح لهذه الخدمة الرئيسية أو غير نشط.',
             // --- MODIFICATION END ---
        ]);
        
        if ($noPaymentMethodEnabled && $request->input('payment_method') !== 'manual_confirmation_due_to_no_gateway') {
            return back()->withInput()->with('error', 'عذراً، لا توجد طرق دفع متاحة حالياً لإتمام هذا الطلب.');
        }
        if ($noPaymentMethodEnabled) {
            $validatedData['payment_method'] = 'manual_confirmation_due_to_no_gateway';
        }

        Log::debug('Booking Submit - After Basic Validation (including location and add-ons):', $validatedData);

        $service = Service::findOrFail($validatedData['service_id']);
        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['date'] . ' ' . $validatedData['time']);
             if ($bookingDateTime < Carbon::now()->subMinutes(1)) {
                 return back()->withInput()->with('error', 'الوقت المختار أصبح في الماضي. يرجى اختيار وقت آخر.');
             }
        } catch (\Exception $e) {
             return back()->withInput()->with('error', 'التاريخ أو الوقت المحدد غير صحيح.');
        }

        $selectedAddOnServiceIds = $validatedData['add_on_services'] ?? [];
        $totalAddOnServicesPrice = 0;
        $activeAddOnServicesWithPrices = []; 

        if (!empty($selectedAddOnServiceIds)) {
            // --- MODIFICATION START: Ensure only allowed and active add-ons are processed ---
            // $allowedAddOnsForThisService = $service->availableAddOns()->whereIn('add_on_services.id', $selectedAddOnServiceIds)->get();
            // بدلاً من الاستعلام مرة أخرى، سنستخدم $allowedAddOnIds التي تم جلبها للتحقق
            // ونجلب تفاصيل الخدمات المختارة والصالحة فقط
            $validSelectedAddOns = \App\Models\AddOnService::whereIn('id', $selectedAddOnServiceIds)
                                        ->where('is_active', true)
                                        ->whereIn('id', $allowedAddOnIds) // تحقق إضافي أنها ضمن المسموح به لهذه الخدمة
                                        ->get();

            foreach ($validSelectedAddOns as $addOn) {
                $totalAddOnServicesPrice += (float)$addOn->price;
                $activeAddOnServicesWithPrices[$addOn->id] = (float)$addOn->price;
            }

            if(count($validSelectedAddOns) !== count($selectedAddOnServiceIds)){
                 Log::warning('Discrepancy or invalid add-on ID submitted by client.', [
                    'submitted_ids' => $selectedAddOnServiceIds,
                    'processed_valid_ids' => $validSelectedAddOns->pluck('id')->toArray(),
                ]);
                // لا داعي لإرجاع خطأ هنا لأن التحقق Rule::in($allowedAddOnIds) يجب أن يكون قد قام بذلك
            }
            // --- MODIFICATION END ---
        }
        $totalAddOnServicesPrice = round($totalAddOnServicesPrice, 2);

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
                 // --- MODIFICATION START: Decide if discount applies to service only or service + add-ons ---
                 // حاليًا، الخصم يطبق على سعر الخدمة الأساسية فقط.
                 // إذا أردت أن يطبق الخصم على (الخدمة الأساسية + الخدمات الإضافية)، ستحتاج لتعديل $priceToApplyDiscountOn
                 $priceToApplyDiscountOn = $originalServicePrice;
                 // مثال إذا أردت تطبيق الخصم على الإجمالي:
                 // $priceToApplyDiscountOn = $originalServicePrice + $totalAddOnServicesPrice;
                 // --- MODIFICATION END ---

                 if ($discountCodeModel->type === DiscountCode::TYPE_PERCENTAGE) {
                     $tempDiscountAmount = ($priceToApplyDiscountOn * $discountCodeModel->value) / 100;
                 } elseif ($discountCodeModel->type === DiscountCode::TYPE_FIXED) {
                     $tempDiscountAmount = $discountCodeModel->value;
                 }
                 $discountAmountApplied = round(max(0, min($tempDiscountAmount, $priceToApplyDiscountOn)), 2); // الخصم لا يمكن أن يتجاوز السعر المطبق عليه

                 // --- MODIFICATION START: Adjust priceAfterDiscount carefully ---
                 // priceAfterDiscount الآن يمثل سعر الخدمة الأساسية بعد الخصم.
                 // إذا كان الخصم يطبق على (الخدمة + الإضافات)، يجب تعديل هذا المنطق.
                 // حاليًا، إذا طبق الخصم على الخدمة الأساسية فقط:
                 $priceAfterDiscount = $originalServicePrice - $discountAmountApplied;
                 // --- MODIFICATION END ---

                 $isDiscountActuallyApplied = true;
                 Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' applied. Amount: {$discountAmountApplied}");
             } else {
                Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' conditions not met. Not applied.");
             }
        }
        $priceAfterDiscount = round(max(0, $priceAfterDiscount), 2);

        $currentOutsideLocationFeeApplied = 0.0;
        if ($validatedData['shooting_area_option'] === 'outside_ahsa') {
            $currentOutsideLocationFeeApplied = $outsideAhsaFeeFromSettings;
        }
        
        // السعر النهائي = (سعر الخدمة بعد الخصم) + رسوم المنطقة + إجمالي سعر الخدمات الإضافية
        $finalTotalAmount = $priceAfterDiscount + $currentOutsideLocationFeeApplied + $totalAddOnServicesPrice;
        $finalTotalAmount = round(max(0, $finalTotalAmount), 2);

        $paymentOption = $validatedData['payment_option'];
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 2) : $finalTotalAmount;
        $amountDueNow = max(0.01, round($amountDueNow, 2));

        Log::debug('Booking Submit - Price Calculation Details Updated:', [
            'original_service_price' => $originalServicePrice,
            'total_add_on_services_price_calculated' => $totalAddOnServicesPrice,
            'price_of_service_plus_addons_before_discount' => $originalServicePrice + $totalAddOnServicesPrice,
            'discount_code_attempted' => $validatedData['discount_code'] ?? 'None',
            'discount_amount_applied_value' => $discountAmountApplied,
            'is_discount_actually_applied' => $isDiscountActuallyApplied,
            'price_of_service_after_discount_alone' => $priceAfterDiscount,
            'outside_location_fee_applied_server' => $currentOutsideLocationFeeApplied,
            'final_total_amount_for_invoice' => $finalTotalAmount,
            'payment_option_selected' => $paymentOption,
            'amount_due_now_for_payment' => $amountDueNow,
        ]);
        
        $user = Auth::user();
        if (!$user) { return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.'); }

        $booking = null;

        DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCodeModel, $finalTotalAmount, $paymentOption, $user, &$booking, $amountDueNow, $isDiscountActuallyApplied, $currentOutsideLocationFeeApplied, $selectedAddOnServiceIds, $activeAddOnServicesWithPrices) {
            $bookingData = [
                'user_id' => $user->id,
                'service_id' => $service->id,
                'booking_datetime' => $bookingDateTime,
                'status' => Booking::STATUS_UNDER_REVIEW,
                'event_location' => $validatedData['event_location'],
                'groom_name_ar' => $validatedData['groom_name_ar'] ?? null,
                'groom_name_en' => $validatedData['groom_name_en'] ?? null,
                'bride_name_ar' => $validatedData['bride_name_ar'] ?? null,
                'bride_name_en' => $validatedData['bride_name_en'] ?? null,
                'customer_notes' => $validatedData['customer_notes'] ?? null,
                'agreed_to_policy' => true,
                'discount_code_id' => $isDiscountActuallyApplied ? $discountCodeModel->id : null,
                'down_payment_amount' => ($paymentOption === 'down_payment') ? $amountDueNow : null,
                'shooting_area' => $validatedData['shooting_area_option'],
                'outside_location_city' => ($validatedData['shooting_area_option'] === 'outside_ahsa') ? $validatedData['outside_ahs_city'] : null,
                'outside_location_fee_applied' => ($currentOutsideLocationFeeApplied > 0) ? $currentOutsideLocationFeeApplied : null,
                // حفظ معلومات السعر والدفع على الحجز – لن تُنشأ الفاتورة إلا عند موافقة المدير
                'total_price' => $finalTotalAmount,
                'requested_payment_option' => $paymentOption,
                'requested_payment_method' => $validatedData['payment_method'],
            ];
            Log::debug('Data for Booking::create():', $bookingData);
            $booking = Booking::create($bookingData);

            if (!$booking || !$booking->id) {
                Log::error('CRITICAL: Failed to create booking.');
                throw new \Exception('فشل إنشاء سجل الحجز.');
            }
            Log::debug('Booking created successfully (no invoice yet):', ['booking_id' => $booking->id]);

            if (!empty($selectedAddOnServiceIds) && !empty($activeAddOnServicesWithPrices)) {
                $addOnsToSync = [];
                foreach ($selectedAddOnServiceIds as $addOnId) {
                    if (isset($activeAddOnServicesWithPrices[(int)$addOnId])) {
                        $addOnsToSync[(int)$addOnId] = ['price_at_booking' => $activeAddOnServicesWithPrices[(int)$addOnId]];
                    }
                }
                if (!empty($addOnsToSync)) {
                    $booking->addOnServices()->sync($addOnsToSync);
                    Log::info("Add-on services synced for booking ID: {$booking->id}", ['synced_add_ons' => $addOnsToSync]);
                }
            }

            // زيادة عداد استخدام كود الخصم عند إنشاء الحجز (بغض النظر عن الموافقة)
            if ($isDiscountActuallyApplied && $discountCodeModel) {
                 $discountCodeModel->increment('current_uses');
            }

            Log::info("Booking created successfully (awaiting admin review). No invoice created yet.", ['booking_id' => $booking->id]);
        });
        
        if (!$booking) {
            Log::error('Booking object is null after transaction.', []);
            return back()->withInput()->with('error', 'حدث خطأ جسيم أثناء إنشاء الحجز. الرجاء التواصل مع الدعم.');
        }

        if ($booking && $user) {
            try {
                $user->notify(new BookingRequestReceived($booking, $validatedData['payment_method'], $amountDueNow, $paymentOption));
                Log::info("BookingRequestReceived notification queued for CUSTOMER for Booking ID: {$booking->id}");
                
                $admins = User::where('is_admin', true)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new BookingRequestReceived($booking, $validatedData['payment_method'], $amountDueNow, $paymentOption));
                }
            } catch (\Exception $e) {
                 Log::error("Failed to queue BookingRequestReceived notifications for Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('booking.pending', $booking->id)
                         ->with('success', 'تم استلام طلب حجزك بنجاح وهو الآن تحت المراجعة. سنقوم بإبلاغك فور الموافقة عليه لتتمكن من إتمام عملية الدفع.');
    }

     public function showPendingPage(Booking $booking) : View|RedirectResponse
     {
          if(Auth::id() !== $booking->user_id && !(Auth::user() && Auth::user()->is_admin)) {
            return redirect()->route('home')->with('error', 'غير مصرح لك بعرض هذا الحجز.');
          }

          $booking->load(['service', 'invoice.payments', 'discountCode', 'addOnServices']);
          $bankAccounts = BankAccount::where('is_active', true)->get();
          $invoice = $booking->invoice;
          
          $amountDueNowOnPending = 0.0;
          $paymentOptionOnPending = $invoice?->payment_option ?? 'full';
          $invoiceTotalAmount = $invoice?->amount ?? 0;

          if ($invoice) {
            $currentPaidAmount = $invoice->payments()->where('status', 'completed')->sum('amount');
            $remainingForInvoice = max(0, round($invoiceTotalAmount - $currentPaidAmount, 2));

            if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                $amountDueNowOnPending = 0.0;
            }
            elseif (in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING_CONFIRMATION, Invoice::STATUS_FAILED, Invoice::STATUS_PENDING])) {
                if ($paymentOptionOnPending === 'down_payment' && $booking->down_payment_amount > 0) {
                    $amountDueNowOnPending = max(0, round($booking->down_payment_amount - $currentPaidAmount, 2));
                } else { 
                    $amountDueNowOnPending = $remainingForInvoice;
                }
            }
            elseif ($invoice->status == Invoice::STATUS_PARTIALLY_PAID) {
                 $amountDueNowOnPending = $remainingForInvoice;
            }
            $amountDueNowOnPending = max(0, round($amountDueNowOnPending, 2));
          }

         $settingsAll = Setting::pluck('value', 'key')->all();
         $isBankTransferEnabled = filter_var($settingsAll['enable_bank_transfer'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isTamaraEnabled = filter_var($settingsAll['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
         $isPaylinkEnabled = filter_var($settingsAll['paylink_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

         return view('frontend.booking.pending', compact(
             'booking', 'bankAccounts', 'amountDueNowOnPending', 
             'paymentOptionOnPending', 'invoiceTotalAmount', 
             'isBankTransferEnabled', 'isTamaraEnabled', 'isPaylinkEnabled'
           ));
     }
}
