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
// --- MODIFICATION START: Import AddOnService model ---
use App\Models\AddOnService;
// --- MODIFICATION END ---

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
        
         if (!$isBankTransferEnabled && !$isTamaraEnabled) {
            Log::warning("Booking form accessed but no payment methods are enabled. Service ID: {$service->id}");
         }

         $bookingPolicyAr = $settingsAll['policy_ar'] ?? '';
         $bookingPolicyEn = $settingsAll['policy_en'] ?? '';
         $bankAccounts = $isBankTransferEnabled ? BankAccount::where('is_active', true)->get() : collect();

         // --- MODIFICATION START: Fetch active Add-on Services ---
         $addOnServices = AddOnService::where('is_active', true)->orderBy('name_ar')->get();
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
             'settingsHomepage' => $settingsAll,
             // --- MODIFICATION START: Pass Add-on Services to the view ---
             'addOnServices' => $addOnServices,
             // --- MODIFICATION END ---
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
            // --- MODIFICATION START: Validation for Add-on Services ---
            'add_on_services' => 'nullable|array',
            'add_on_services.*' => 'integer|exists:add_on_services,id', // Validate each selected add-on ID
            // --- MODIFICATION END ---
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع.',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
             'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً.',
             'shooting_area_option.required' => 'الرجاء اختيار منطقة التصوير.',
             'outside_ahs_city.required' => 'الرجاء اختيار المدينة عند تحديد منطقة خارج الأحساء.',
             'outside_ahs_city.in' => 'المدينة المختارة غير صالحة.',
             // --- MODIFICATION START: Validation messages for Add-on Services ---
             'add_on_services.array' => 'اختيار الخدمات الإضافية غير صحيح.',
             'add_on_services.*.integer' => 'أحد الخدمات الإضافية المختارة غير صحيح.',
             'add_on_services.*.exists' => 'أحد الخدمات الإضافية المختارة غير متاح حالياً.',
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

        // --- MODIFICATION START: Calculate total price of selected add-on services ---
        $selectedAddOnServiceIds = $validatedData['add_on_services'] ?? [];
        $totalAddOnServicesPrice = 0;
        $activeAddOnServicesWithPrices = []; // لتخزين الخدمات الإضافية النشطة بأسعارها من قاعدة البيانات

        if (!empty($selectedAddOnServiceIds)) {
            $activeAddOns = AddOnService::whereIn('id', $selectedAddOnServiceIds)->where('is_active', true)->get();
            foreach ($activeAddOns as $addOn) {
                $totalAddOnServicesPrice += (float)$addOn->price;
                $activeAddOnServicesWithPrices[$addOn->id] = (float)$addOn->price; // تخزين السعر الفعلي من قاعدة البيانات
            }
            // تحقق إذا كان عدد الخدمات النشطة المسترجعة يطابق عدد الـ IDs المرسلة
            if (count($activeAddOns) !== count($selectedAddOnServiceIds)) {
                Log::warning('Discrepancy in selected add-on services. Some might be inactive or invalid.', [
                    'requested_ids' => $selectedAddOnServiceIds,
                    'active_found_ids' => $activeAddOns->pluck('id')->toArray(),
                ]);
                // يمكنك هنا إرجاع خطأ إذا كنت تريد أن تكون صارمًا
                // return back()->withInput()->with('error', 'بعض الخدمات الإضافية المختارة لم تعد متاحة.');
            }
        }
        $totalAddOnServicesPrice = round($totalAddOnServicesPrice, 2);
        // --- MODIFICATION END ---

        $originalServicePrice = (float) $service->price_sar;
        $priceAfterDiscount = $originalServicePrice; // الخصم يطبق على سعر الخدمة الأساسية
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
                 Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' applied. Amount: {$discountAmountApplied}");
             } else {
                Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' conditions not met. Not applied.");
             }
        }
        $priceAfterDiscount = round(max(0, $priceAfterDiscount), 2); // هذا سعر الخدمة بعد الخصم

        $currentOutsideLocationFeeApplied = 0.0;
        if ($validatedData['shooting_area_option'] === 'outside_ahsa') {
            $currentOutsideLocationFeeApplied = $outsideAhsaFeeFromSettings;
        }

        // --- MODIFICATION START: Calculate final total amount including add-ons ---
        // السعر النهائي = (سعر الخدمة بعد الخصم) + رسوم المنطقة + إجمالي سعر الخدمات الإضافية
        $finalTotalAmount = $priceAfterDiscount + $currentOutsideLocationFeeApplied + $totalAddOnServicesPrice;
        // --- MODIFICATION END ---
        $finalTotalAmount = round(max(0, $finalTotalAmount), 2);

        $paymentOption = $validatedData['payment_option'];
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 2) : $finalTotalAmount;
        $amountDueNow = max(0.01, round($amountDueNow, 2));

        Log::debug('Booking Submit - Price Calculation Details:', [
            'original_service_price' => $originalServicePrice,
            'discount_code_attempted' => $validatedData['discount_code'] ?? 'None',
            'discount_amount_applied_value' => $discountAmountApplied,
            'is_discount_actually_applied' => $isDiscountActuallyApplied,
            'price_of_service_after_discount' => $priceAfterDiscount, // تم تغيير اسم المفتاح للتوضيح
            'total_add_on_services_price' => $totalAddOnServicesPrice, // إضافة سعر الخدمات الإضافية
            'outside_location_fee_applied_server' => $currentOutsideLocationFeeApplied,
            'final_total_amount_for_invoice' => $finalTotalAmount,
            'payment_option_selected' => $paymentOption,
            'amount_due_now_for_payment' => $amountDueNow,
        ]);
        
        $user = Auth::user();
        if (!$user) { return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.'); }

        $booking = null; $invoice = null;

        // --- MODIFICATION START: Pass $activeAddOnServicesWithPrices to the transaction ---
        DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCodeModel, $finalTotalAmount, $paymentOption, $user, &$booking, &$invoice, $amountDueNow, $isDiscountActuallyApplied, $currentOutsideLocationFeeApplied, $selectedAddOnServiceIds, $activeAddOnServicesWithPrices) {
        // --- MODIFICATION END ---
            $bookingData = [
                'user_id' => $user->id,
                'service_id' => $service->id,
                'booking_datetime' => $bookingDateTime,
                'status' => Booking::STATUS_PENDING,
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
            ];
            Log::debug('Data for Booking::create():', $bookingData);
            $booking = Booking::create($bookingData);

            if (!$booking || !$booking->id) {
                Log::error('CRITICAL: Failed to create booking or booking ID is null right before invoice creation.');
                throw new \Exception('فشل إنشاء سجل الحجز بشكل صحيح، لا يمكن إنشاء الفاتورة.');
            }
            Log::debug('Booking created successfully:', ['booking_id' => $booking->id]);

            // --- MODIFICATION START: Save selected add-on services to pivot table ---
            if (!empty($selectedAddOnServiceIds) && !empty($activeAddOnServicesWithPrices)) {
                $addOnsToSync = [];
                foreach ($selectedAddOnServiceIds as $addOnId) {
                    if (isset($activeAddOnServicesWithPrices[$addOnId])) { // تأكد من أن الخدمة نشطة وسعرها معروف
                        $addOnsToSync[$addOnId] = ['price_at_booking' => $activeAddOnServicesWithPrices[$addOnId]];
                    }
                }
                if (!empty($addOnsToSync)) {
                    $booking->addOnServices()->sync($addOnsToSync);
                    Log::info("Add-on services synced for booking ID: {$booking->id}", ['synced_add_ons' => $addOnsToSync]);
                }
            }
            // --- MODIFICATION END ---

            $invoiceStatus = ($validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway')
                           ? Invoice::STATUS_PENDING_CONFIRMATION
                           : Invoice::STATUS_UNPAID;

            $invoiceData = [
                'booking_id' => $booking->id,
                'invoice_number' => Invoice::generateUniqueInvoiceNumber(),
                'amount' => $finalTotalAmount, // المبلغ النهائي يشمل كل شيء
                'currency' => 'SAR',
                'status' => $invoiceStatus,
                'payment_method' => $validatedData['payment_method'],
                'payment_option' => $paymentOption,
                'due_date' => Carbon::today(),
            ];
            Log::debug('Data for Invoice::create():', $invoiceData);
            $invoice = Invoice::create($invoiceData);
            
            if (!$invoice || !$invoice->id) {
                Log::error('CRITICAL: Failed to create invoice or invoice ID is null.');
                throw new \Exception('فشل إنشاء سجل الفاتورة بشكل صحيح.');
            }
            Log::debug('Invoice created successfully:', ['invoice_id' => $invoice->id]);

            $booking->invoice_id = $invoice->id;
            $booking->save();

            if ($isDiscountActuallyApplied && $discountCodeModel) {
                 $discountCodeModel->increment('current_uses');
            }
            Log::info("Booking and Invoice created successfully in transaction.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id, 'invoice_status' => $invoice->status]);
        });
        
        if (!$booking || !$invoice) {
            Log::error('Booking or Invoice object is null after transaction. Redirecting back with error.', ['booking_exists' => !is_null($booking), 'invoice_exists' => !is_null($invoice)]);
            return back()->withInput()->with('error', 'حدث خطأ جسيم أثناء إنشاء الحجز. الرجاء التواصل مع الدعم.');
        }

        if ($booking && $user && $invoice) {
            try {
                $amountForNotification = $amountDueNow;
                // ملاحظة: إذا أردت تضمين تفاصيل الخدمات الإضافية في الإشعار، ستحتاج لتعديل كلاس BookingRequestReceived
                $user->notify(new BookingRequestReceived($booking, $invoice->payment_method, $amountForNotification, $invoice->payment_option));
                Log::info("BookingRequestReceived notification queued for CUSTOMER for Booking ID: {$booking->id}");
                
                $admins = User::where('is_admin', true)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new BookingRequestReceived($booking, $invoice->payment_method, $amountForNotification, $invoice->payment_option));
                    Log::info("BookingRequestReceived notification queued for ADMIN {$admin->email} for Booking ID: {$booking->id}");
                }
            } catch (\Exception $e) {
                 Log::error("Failed to queue BookingRequestReceived notifications for Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($validatedData['payment_method'] === 'bank_transfer' || $validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway') {
             return redirect()->route('booking.pending', $booking->id);
        }
        elseif ($validatedData['payment_method'] === 'tamara') {
             if (!$invoice || $amountDueNow < 0.01) {
                Log::error("Tamara initiation skipped.", ['invoice_id' => $invoice?->id, 'amount_due_now' => $amountDueNow ?? 'Not set']);
                return redirect()->route('booking.pending', $booking->id)->with('error', 'خطأ في تجهيز الدفع لتمارا.');
             }
             $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountDueNow, $paymentOption);
             if ($checkoutResponse && isset($checkoutResponse['checkout_url']) && (isset($checkoutResponse['order_id']) || isset($checkoutResponse['checkout_id']))) {
                  $gatewayRef = $checkoutResponse['checkout_id'] ?? $checkoutResponse['order_id'];
                  $invoice->payment_gateway_ref = $gatewayRef; 
                  $invoice->save();
                  Log::info("Tamara checkout URL obtained.", ['invoice_id' => $invoice->id, 'tamara_ref' => $gatewayRef]);
                  return redirect()->away($checkoutResponse['checkout_url']);
             } else {
                  Log::error("Tamara initiateCheckout failed.", ['invoice_id' => $invoice->id, 'response' => $checkoutResponse]);
                  return redirect()->route('booking.pending', $booking->id)->with('error', 'لم نتمكن من بدء الدفع مع تمارا.');
             }
        }
        
        return redirect()->route('booking.pending', $booking->id);
    }

     public function showPendingPage(Booking $booking) : View|RedirectResponse
     {
          if(Auth::id() !== $booking->user_id && !(Auth::user() && Auth::user()->is_admin)) {
            return redirect()->route('home')->with('error', 'غير مصرح لك بعرض هذا الحجز.');
          }

          // --- MODIFICATION START: Eager load addOnServices ---
          $booking->load(['service', 'invoice.payments', 'discountCode', 'addOnServices']);
          // --- MODIFICATION END ---
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

         return view('frontend.booking.pending', compact(
             'booking', 'bankAccounts', 'amountDueNowOnPending', 
             'paymentOptionOnPending', 'invoiceTotalAmount', 
             'isBankTransferEnabled', 'isTamaraEnabled'
           ));
     }
}
