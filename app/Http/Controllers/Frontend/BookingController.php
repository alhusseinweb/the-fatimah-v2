<?php

// المسار: app/Http/Controllers/Frontend/BookingController.php

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
use App\Notifications\BookingRequestReceived; // تأكد من أن هذا الإشعار يقبل المعاملات الجديدة
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

         if ($bookingDateTime < Carbon::now()->subMinutes(1)) { // مهلة دقيقة واحدة
              return redirect()->route('booking.calendar', $service->id)
                              ->with('error', 'لا يمكن اختيار وقت في الماضي.');
         }

         $bankTransferEnabledSetting = Setting::where('key', 'enable_bank_transfer')->first();
         $isBankTransferEnabled = $bankTransferEnabledSetting ? filter_var($bankTransferEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

         $tamaraEnabledSetting = Setting::where('key', 'tamara_enabled')->first();
         $isTamaraEnabled = $tamaraEnabledSetting ? filter_var($tamaraEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;
        
         if (!$isBankTransferEnabled && !$isTamaraEnabled) {
            Log::warning("Booking form accessed but no payment methods are enabled. Service ID: {$service->id}");
            // لا يزال بإمكان المستخدم رؤية النموذج، ولكن لن يتمكن من اختيار طريقة دفع
            // وسيتم التعامل مع هذا في submitBooking
         }

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
             'bankAccounts' => $bankAccounts,
             'isBankTransferEnabled' => $isBankTransferEnabled,
             'isTamaraEnabled' => $isTamaraEnabled,
         ]);
     }


    public function submitBooking(Request $request): RedirectResponse
    {
        $isBankTransferEnabled = filter_var(Setting::where('key', 'enable_bank_transfer')->value('value'), FILTER_VALIDATE_BOOLEAN);
        $isTamaraEnabled = filter_var(Setting::where('key', 'tamara_enabled')->value('value'), FILTER_VALIDATE_BOOLEAN);
        
        $availablePaymentMethodsServer = [];
        if ($isTamaraEnabled) $availablePaymentMethodsServer[] = 'tamara';
        if ($isBankTransferEnabled) $availablePaymentMethodsServer[] = 'bank_transfer';
        
        $noPaymentMethodEnabled = empty($availablePaymentMethodsServer);
        // إذا لم تكن هناك طرق دفع مفعلة، ولكن المستخدم أرسل الطلب، افترض أنه سيكون تأكيدًا يدويًا
        //  if ($noPaymentMethodEnabled) {
        //      $availablePaymentMethodsServer[] = 'manual_confirmation_due_to_no_gateway';
        //  }


        $validatedData = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'groom_name_en' => 'nullable|string|max:255',
            'bride_name_en' => 'nullable|string|max:255',
            'customer_notes' => 'nullable|string|max:1000',
            'discount_code' => ['nullable', 'string', 'max:50'],
            'agreed_to_policy' => 'required|accepted',
            'payment_option' => ['required', Rule::in(['full', 'down_payment'])],
            // التحقق من طريقة الدفع، إلا إذا لم تكن هناك طرق دفع متاحة وتم إرسال القيمة الخاصة
            'payment_method' => $noPaymentMethodEnabled ? 'nullable|string' : ['required', Rule::in($availablePaymentMethodsServer)],
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع (كامل أو عربون).',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
             'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً.',
        ]);
        
        // إذا لم تكن هناك طرق دفع مفعلة من الإعدادات، ولكن المستخدم لم يرسل القيمة الخاصة (ربما تم التلاعب بالـ JS)
        if ($noPaymentMethodEnabled && $request->input('payment_method') !== 'manual_confirmation_due_to_no_gateway') {
            Log::warning("SubmitBooking: No payment methods enabled in settings, but form submitted without manual_confirmation flag.", $validatedData);
            return back()->withInput()->with('error', 'عذراً، لا توجد طرق دفع متاحة حالياً لإتمام هذا الطلب.');
        }
        // إذا لم تكن هناك طرق دفع، اضبطها على القيمة الخاصة
        if ($noPaymentMethodEnabled) {
            $validatedData['payment_method'] = 'manual_confirmation_due_to_no_gateway';
        }


        Log::debug('Booking Submit - After Basic Validation:', $validatedData);

        $service = Service::findOrFail($validatedData['service_id']);
        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['date'] . ' ' . $validatedData['time']);
             if ($bookingDateTime < Carbon::now()->subMinutes(1)) {
                 return back()->withInput()->with('error', 'الوقت المختار أصبح في الماضي. يرجى اختيار وقت آخر.');
             }
        } catch (\Exception $e) {
             return back()->withInput()->with('error', 'التاريخ أو الوقت المحدد غير صحيح.');
        }

        $discountCodeModel = null; $discountAmount = 0;
        $originalServicePrice = $service->price_sar; // السعر الأصلي للخدمة
        $finalTotalAmount = $originalServicePrice; // السعر النهائي، يبدأ بالسعر الأصلي
        $isDiscountAppliedAndValid = false; // علم لتتبع ما إذا تم تطبيق الخصم بنجاح

        if (!empty($validatedData['discount_code'])) {
             $discountCodeModel = DiscountCode::where('code', $validatedData['discount_code'])
                                         ->active()
                                         ->first();
            
             if (!$discountCodeModel) {
                 return back()->withInput()->withErrors(['discount_code' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.']);
             }

             if (!is_null($discountCodeModel->max_uses) && $discountCodeModel->current_uses >= $discountCodeModel->max_uses) {
                 return back()->withInput()->withErrors(['discount_code' => 'لقد تم استخدام كود الخصم بالحد الأقصى.']);
             }

             // --- التحقق النهائي من شروط الخصم ---
             $discountConditionsMet = true; // افترض أن الشروط متحققة مبدئياً

             // 1. التحقق من طريقة الدفع
             if (!empty($discountCodeModel->allowed_payment_methods)) {
                 if (!in_array($validatedData['payment_method'], $discountCodeModel->allowed_payment_methods)) {
                     $discountConditionsMet = false;
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' NOT valid for payment method '{$validatedData['payment_method']}'. Allowed: " . implode(', ', $discountCodeModel->allowed_payment_methods));
                     // لا نرجع خطأ هنا مباشرة، بل نسجل أن الشروط لم تتحقق ونكمل بدون خصم
                     // أو يمكنك إرجاع خطأ إذا كان هذا هو المطلوب:
                     // return back()->withInput()->withErrors(['discount_code' => 'كود الخصم غير صالح لطريقة الدفع المختارة.']);
                 } else {
                    Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' IS valid for payment method '{$validatedData['payment_method']}'.");
                 }
             }

             // 2. التحقق من وقت الحجز (فقط إذا كانت شروط الدفع متحققة أو لا توجد شروط دفع)
             if ($discountConditionsMet && ($discountCodeModel->applicable_from_time || $discountCodeModel->applicable_to_time)) {
                 $bookingTimeOnlyCarbon = Carbon::parse($bookingDateTime->format('H:i:s')); // وقت الحجز بتنسيق H:i:s للمقارنة الدقيقة
                 $applicableFrom = $discountCodeModel->applicable_from_time ? Carbon::parse($discountCodeModel->applicable_from_time) : null;
                 $applicableTo = $discountCodeModel->applicable_to_time ? Carbon::parse($discountCodeModel->applicable_to_time) : null;

                 if ($applicableFrom && $bookingTimeOnlyCarbon->lt($applicableFrom)) {
                     $discountConditionsMet = false;
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' NOT valid for time {$bookingTimeOnlyCarbon->toTimeString()}. Starts at {$discountCodeModel->applicable_from_time}");
                 }
                 if ($applicableTo && $bookingTimeOnlyCarbon->gt($applicableTo)) {
                     $discountConditionsMet = false;
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' NOT valid for time {$bookingTimeOnlyCarbon->toTimeString()}. Ends at {$discountCodeModel->applicable_to_time}");
                 }
                 if($discountConditionsMet && ($applicableFrom || $applicableTo)){
                     Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' IS valid for time {$bookingTimeOnlyCarbon->toTimeString()}. Range: {$discountCodeModel->applicable_from_time} - {$discountCodeModel->applicable_to_time}");
                 }
             }
             // --- نهاية التحقق النهائي من الشروط ---

             if ($discountConditionsMet) { // إذا تحققت جميع الشروط
                 if ($discountCodeModel->type === DiscountCode::TYPE_PERCENTAGE) {
                      $calculatedDiscount = ($originalServicePrice * $discountCodeModel->value) / 100;
                 } elseif ($discountCodeModel->type === DiscountCode::TYPE_FIXED) {
                      $calculatedDiscount = $discountCodeModel->value;
                 }
                 // تأكد أن الخصم لا يتجاوز السعر وأن قيمته موجبة
                 $discountAmount = max(0, min($calculatedDiscount, $originalServicePrice));
                 $discountAmount = round($discountAmount, 2);

                 $finalTotalAmount = $originalServicePrice - $discountAmount;
                 $isDiscountAppliedAndValid = true; // تم تطبيق الخصم بنجاح بعد كل الشروط
                 Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' applied successfully. Amount: {$discountAmount}");
             } else {
                 Log::info("SubmitBooking: Discount '{$discountCodeModel->code}' conditions not met. No discount applied.");
                 // إذا لم تتحقق الشروط، يبقى السعر النهائي هو السعر الأصلي ولا يتم تطبيق الخصم
                 $finalTotalAmount = $originalServicePrice;
                 $discountAmount = 0; // لا يوجد خصم
                 $isDiscountAppliedAndValid = false;
                 // يمكنك اختيار إعلام المستخدم هنا بأن الكود لم ينطبق بسبب الشروط
                 // return back()->withInput()->with('warning', 'كود الخصم المدخل لم ينطبق بسبب شروط معينة (مثل طريقة الدفع أو وقت الحجز).');
             }
        }
        $finalTotalAmount = round(max(0, $finalTotalAmount), 2);


        $paymentOption = $validatedData['payment_option'];
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 2) : $finalTotalAmount;
        $amountDueNow = max(0.01, round($amountDueNow, 2)); // تأكد أن المبلغ موجب دائماً (1 هللة على الأقل)


        Log::debug('Booking Submit - Before Transaction:', [
            'original_service_price' => $originalServicePrice,
            'discount_code_attempted' => $validatedData['discount_code'] ?? 'None',
            'discount_amount_calculated_and_applied' => $discountAmount,
            'is_discount_actually_applied' => $isDiscountAppliedAndValid,
            'final_total_amount_after_all_checks' => $finalTotalAmount,
            'payment_option_selected' => $paymentOption,
            'amount_due_now_calculated' => $amountDueNow,
        ]);

        $booking = null; $invoice = null; $user = Auth::user();
        if (!$user) {
             return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.');
        }

        try {
            DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCodeModel, $finalTotalAmount, $paymentOption, $user, &$booking, &$invoice, $amountDueNow, $isDiscountAppliedAndValid, $discountAmount) {
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'booking_datetime' => $bookingDateTime,
                    'status' => Booking::STATUS_PENDING, // دائماً يبدأ معلقاً
                    'event_location' => $validatedData['event_location'],
                    'groom_name_en' => $validatedData['groom_name_en'],
                    'bride_name_en' => $validatedData['bride_name_en'],
                    'customer_notes' => $validatedData['customer_notes'],
                    'agreed_to_policy' => true,
                    'discount_code_id' => $isDiscountAppliedAndValid ? $discountCodeModel->id : null,
                    'down_payment_amount' => ($paymentOption === 'down_payment') ? $amountDueNow : null, // المبلغ المستحق كعربون
                    // يمكنك إضافة حقل لتخزين قيمة الخصم المطبق إذا أردت
                    // 'applied_discount_amount' => $discountAmount,
                ]);

                $invoiceStatus = ($validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway')
                               ? Invoice::STATUS_PENDING_CONFIRMATION // إذا لا توجد طرق دفع، الفاتورة بانتظار التأكيد اليدوي
                               : Invoice::STATUS_UNPAID;

                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    'invoice_number' => 'INV-' . $booking->id . '-' . time(),
                    'amount' => $finalTotalAmount, // السعر الإجمالي للفاتورة بعد الخصم
                    'currency' => 'SAR',
                    'status' => $invoiceStatus,
                    'payment_method' => $validatedData['payment_method'],
                    'payment_option' => $paymentOption,
                    'due_date' => Carbon::today(),
                ]);
                
                $booking->invoice_id = $invoice->id;
                $booking->save();

                if ($isDiscountAppliedAndValid && $discountCodeModel) {
                     $discountCodeModel->increment('current_uses');
                     Log::info("Discount code {$discountCodeModel->code} usage incremented for booking ID: {$booking->id}");
                }
                Log::info("Booking and Invoice created successfully in transaction.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id, 'invoice_status' => $invoice->status]);
            });

        } catch (\Exception $e) {
             Log::error('Booking Creation Transaction Failed: ' . $e->getMessage(), ['exception_class' => get_class($e), 'trace' => Str::limit($e->getTraceAsString(),1000)]);
             return back()->withInput()->with('error', 'حدث خطأ غير متوقع أثناء إنشاء الحجز. يرجى المحاولة مرة أخرى أو التواصل معنا.');
        }
        
        // إرسال الإشعارات
        if ($booking && $user && $invoice) { // تأكد من وجود الفاتورة
            try {
                // تمرير قيمة الفاتورة وخيار الدفع للإشعار
                // إذا كان المبلغ المستحق الآن (العربون) هو المهم في الإشعار، يمكنك تمريره بدلاً من $invoice->amount
                $amountForNotification = ($invoice->payment_option === 'down_payment' && $booking->down_payment_amount > 0)
                                         ? $booking->down_payment_amount
                                         : $invoice->amount;

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

        // توجيه المستخدم بناءً على طريقة الدفع
        if ($validatedData['payment_method'] === 'bank_transfer' || $validatedData['payment_method'] === 'manual_confirmation_due_to_no_gateway') {
             Log::info("Redirecting to pending page for bank transfer or manual confirmation.", ['booking_id' => $booking->id, 'payment_method' => $validatedData['payment_method']]);
             return redirect()->route('booking.pending', $booking->id);
        }
        elseif ($validatedData['payment_method'] === 'tamara') {
             if (!$invoice || $amountDueNow < 0.01) { // تمارا تتطلب على الأقل هللة واحدة
                Log::error("Tamara initiation skipped due to missing invoice or zero/negative amountDueNow.", [
                    'booking_id' => $booking?->id, 'invoice_id' => $invoice?->id, 'amount_due_now' => $amountDueNow ?? 'Not set'
                ]);
                return redirect()->route('booking.pending', $booking->id)
                                 ->with('error', 'حدث خطأ في تجهيز معلومات الدفع لتمارا. المبلغ المطلوب غير صحيح.');
             }
             Log::info("Initiating Tamara checkout.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id, 'amount_to_send_tamara' => $amountDueNow, 'payment_option_for_tamara' => $paymentOption]);
             // تأكد أن invoice->amount يعكس السعر الإجمالي بعد الخصم
             // وأن amountDueNow هو المبلغ الصحيح الذي يجب أن يدفعه العميل الآن عبر تمارا
             $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountDueNow, $paymentOption);
             if ($checkoutResponse && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['checkout_id'])) { // تمارا v2 تستخدم checkout_id
                  $invoice->payment_gateway_ref = $checkoutResponse['checkout_id']; // استخدام checkout_id كمرجع
                  $invoice->save();
                  Log::info("Tamara checkout URL obtained. Redirecting user.", ['invoice_id' => $invoice->id, 'tamara_checkout_id' => $checkoutResponse['checkout_id']]);
                  return redirect()->away($checkoutResponse['checkout_url']);
             } else {
                  Log::error("Tamara initiateCheckout failed in BookingController.", ['invoice_id' => $invoice->id, 'response_from_tamara_service' => $checkoutResponse]);
                  return redirect()->route('booking.pending', $booking->id)
                                 ->with('error', 'لم نتمكن من بدء الدفع مع تمارا حالياً. يمكنك محاولة الدفع من صفحة الحجز المعلق أو اختيار طريقة دفع أخرى إذا كانت متاحة.');
             }
        }

        // هذا لا يجب أن يحدث إذا كانت التحققات صحيحة
        Log::error('Unknown payment method or unhandled scenario after validation in submitBooking.', ['method' => $validatedData['payment_method']]);
        return redirect()->route('booking.pending', $booking->id)
                        ->with('error', 'حدث خطأ غير متوقع في طريقة الدفع المختارة. يرجى مراجعة طلبك.');
    }

     public function showPendingPage(Booking $booking) : View|RedirectResponse
     {
          if(Auth::id() !== $booking->user_id && !(Auth::user() && Auth::user()->is_admin)) {
            Log::warning("Unauthorized attempt to view pending page.", ['booking_id' => $booking->id, 'user_id' => Auth::id()]);
            return redirect()->route('home')->with('error', 'غير مصرح لك بعرض هذا الحجز.');
          }

          $booking->load(['service', 'invoice.payments', 'discountCode']);
          $bankAccounts = BankAccount::where('is_active', true)->get();
          $invoice = $booking->invoice;
          
          $amountDueNowOnPending = 0.0;
          $paymentOptionOnPending = $invoice?->payment_option ?? 'full'; // الخيار الذي تم اختياره عند إنشاء الفاتورة
          $invoiceTotalAmount = $invoice?->amount ?? 0; // المبلغ الإجمالي للفاتورة بعد أي خصومات

          if ($invoice) {
            $currentPaidAmount = $invoice->payments->sum('amount');
            // تأكد أن المبلغ المتبقي لا يكون بالسالب
            $remainingForInvoice = max(0, round($invoiceTotalAmount - $currentPaidAmount, 2));

            // إذا كانت الفاتورة مدفوعة بالكامل أو ملغاة أو مسترجعة، فالمبلغ المستحق صفر
            if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                $amountDueNowOnPending = 0.0;
            }
            // إذا كانت الفاتورة غير مدفوعة أو بانتظار التأكيد
            elseif (in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING_CONFIRMATION])) {
                if ($paymentOptionOnPending === 'down_payment' && $booking->down_payment_amount > 0) {
                    // المبلغ المستحق هو العربون ناقص ما تم دفعه (إذا كان هناك شيء مدفوع بالفعل تجاه العربون)
                    $amountDueNowOnPending = max(0, round($booking->down_payment_amount - $currentPaidAmount, 2));
                } else { // دفع كامل
                    $amountDueNowOnPending = $remainingForInvoice;
                }
            }
            // إذا كانت مدفوعة جزئياً (يعني تم دفع العربون، والآن مطلوب الباقي)
            elseif ($invoice->status == Invoice::STATUS_PARTIALLY_PAID) {
                 $amountDueNowOnPending = $remainingForInvoice;
            }
            $amountDueNowOnPending = max(0, round($amountDueNowOnPending, 2)); // ضمان عدم وجود قيمة سالبة
          }

          $isBankTransferEnabled = filter_var(Setting::where('key', 'enable_bank_transfer')->value('value'), FILTER_VALIDATE_BOOLEAN);
          $isTamaraEnabled = filter_var(Setting::where('key', 'tamara_enabled')->value('value'), FILTER_VALIDATE_BOOLEAN);


         return view('frontend.booking.pending', compact(
             'booking', 
             'bankAccounts', 
             'amountDueNowOnPending', 
             'paymentOptionOnPending', // لعرض ما تم اختياره
             'invoiceTotalAmount', // لعرض إجمالي الفاتورة
             'isBankTransferEnabled',
             'isTamaraEnabled'
           ));
     }
}
