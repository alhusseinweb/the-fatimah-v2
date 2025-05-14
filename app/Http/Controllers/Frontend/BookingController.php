<?php

// المسار: app/Http/Controllers/Frontend/BookingController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Setting; // <-- تأكد من استيراد موديل Setting
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
use App\Notifications\NewBookingReceived;

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
     * !!! تم التعديل: إضافة جلب رقم الواتساب !!!
     */
    public function showCalendar(Service $service) : View
    {
        if (!$service->is_active) {
            abort(404, 'Service not available.');
        }
        // جلب رقم الواتساب من الإعدادات
        $photographerWhatsApp = Setting::where('key', 'contact_whatsapp')->value('value'); // استبدل 'contact_whatsapp' بالمفتاح الصحيح إذا لزم الأمر

        return view('frontend.booking.calendar', compact('service', 'photographerWhatsApp')); // تمرير الرقم للواجهة
    }


    /**
     * عرض نموذج تأكيد الحجز.
     */
     public function showBookingForm(Request $request): View|RedirectResponse
     {
         // ... (الكود السابق كما هو) ...

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

         $bookingPolicyAr = Setting::where('key', 'policy_ar')->value('value');
         $bookingPolicyEn = Setting::where('key', 'policy_en')->value('value');
         $bankAccounts = BankAccount::where('is_active', true)->get();

         return view('frontend.booking.form', [
             'service' => $service,
             'selectedDate' => $selectedDate,
             'selectedTime' => $selectedTime,
             'bookingDateTime' => $bookingDateTime,
             'bookingPolicyAr' => $bookingPolicyAr ?? '',
             'bookingPolicyEn' => $bookingPolicyEn ?? '',
             'bankAccounts' => $bankAccounts ?? collect(),
         ]);
     }


/**
     * استقبال بيانات نموذج الحجز، التحقق منها، وإنشاء الحجز والفاتورة.
     */
    public function submitBooking(Request $request): RedirectResponse
    {
        // --- 1. Validation ---
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
            'payment_method' => ['required', Rule::in(['tamara', 'bank_transfer'])],
        ], [
             'agreed_to_policy.accepted' => 'يجب الموافقة على سياسة الحجز للمتابعة.',
             'discount_code.exists' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.',
             'payment_option.required' => 'الرجاء اختيار خيار الدفع (كامل أو عربون).',
             'payment_method.required' => 'الرجاء اختيار طريقة الدفع.',
        ]);

        Log::debug('Booking Submit - After Validation:', $validatedData);

        // --- 2. Get Service & DateTime ---
        $service = Service::findOrFail($validatedData['service_id']);
        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['date'] . ' ' . $validatedData['time']);
            // --- تحقق إضافي: الوقت المحدد ليس في الماضي ---
             if ($bookingDateTime < Carbon::now()->subMinutes(2)) { // هامش دقيقتين
                 return back()->withInput()->with('error', 'الوقت المختار أصبح في الماضي. يرجى اختيار وقت آخر.');
             }
        } catch (\Exception $e) {
             return back()->withInput()->with('error', 'التاريخ أو الوقت المحدد غير صحيح.');
        }

        // --- 3. Re-check Availability (Optional but recommended) ---
        // ... (يمكن إضافة كود التحقق من التوافر هنا إذا أردت) ...

        // --- 4. Apply Discount Code ---
        $discountCode = null; $discountAmount = 0;
        $totalAmount = $service->price_sar; $finalTotalAmount = $totalAmount;
        $discountValueRaw = 0; // قيمة الخصم كرقم خام
        $discountType = null; // نوع الخصم

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
                   $discountAmount = min($discountAmount, $totalAmount); // تأكد أن الخصم لا يتجاوز السعر
                   $finalTotalAmount = $totalAmount - $discountAmount;
             } else {
                  // الكود المدخل غير صالح، أرجع مع خطأ
                   return back()->withInput()->withErrors(['discount_code' => 'كود الخصم المدخل غير صالح أو منتهي الصلاحية.']);
             }
        }
        $finalTotalAmount = round($finalTotalAmount, 2);

        // --- 4.5. حساب المبلغ المطلوب دفعه الآن ---
        $paymentOption = $validatedData['payment_option'];
        // استخدام التقريب الصحيح
        $amountDueNow = ($paymentOption === 'down_payment') ? round($finalTotalAmount / 2, 0) : round($finalTotalAmount, 0);

        Log::debug('Booking Submit - Before Transaction:', [
            'payment_option_var' => $paymentOption,
            'amount_due_now' => $amountDueNow,
            'final_total_amount' => $finalTotalAmount,
            'discount_applied' => $discountCode ? $discountCode->code : 'None'
        ]);


        // --- 5. Create Booking & Invoice in Transaction ---
        $booking = null; $invoice = null; $user = Auth::user();
        if (!$user) {
             return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول أولاً.');
        }

        // !!! التأكد من صحة بناء الجملة هنا !!!
        try {
            DB::transaction(function () use ($validatedData, $bookingDateTime, $service, $discountCode, $finalTotalAmount, $paymentOption, $user, &$booking, &$invoice) {

                // إنشاء الحجز
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'booking_datetime' => $bookingDateTime,
                    'status' => Booking::STATUS_PENDING, // أو STATUS_PENDING_CONFIRMATION إذا أردت تمييزها
                    'event_location' => $validatedData['event_location'],
                    'groom_name_en' => $validatedData['groom_name_en'],
                    'bride_name_en' => $validatedData['bride_name_en'],
                    'customer_notes' => $validatedData['customer_notes'],
                    'agreed_to_policy' => true,
                    'discount_code_id' => $discountCode?->id,
                ]);

                Log::debug('Booking Submit - Inside Transaction - Booking Created:', ['booking_id' => $booking->id]);

                // إنشاء الفاتورة
                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    // إنشاء رقم فاتورة فريد (يمكن تحسين هذه الطريقة لاحقاً)
                    'invoice_number' => 'INV-' . $booking->id . '-' . time(),
                    'amount' => $finalTotalAmount, // المبلغ النهائي بعد الخصم
                    'currency' => 'SAR',
                    'status' => Invoice::STATUS_UNPAID, // تبدأ الفاتورة غير مدفوعة
                    'payment_method' => $validatedData['payment_method'],
                    'payment_option' => $paymentOption, // حفظ خيار الدفع (كامل أو عربون)
                    'due_date' => Carbon::today(), // يمكن تعديل تاريخ الاستحقاق إذا لزم الأمر
                ]);

                 Log::debug('Booking Submit - Inside Transaction - Invoice Created:', [
                    'invoice_id' => $invoice->id,
                    'saved_payment_option' => $invoice->payment_option
                ]);

                // ربط الفاتورة بالحجز (إذا لم يتم ذلك تلقائياً بواسطة العلاقات)
                // $booking->invoice_id = $invoice->id; // قد لا تحتاج لهذا السطر إذا كانت العلاقة معرفة بشكل صحيح
                // $booking->save(); // قد لا تحتاج لهذا السطر إذا كان الربط يتم تلقائياً

                // زيادة عداد استخدام كود الخصم إذا تم تطبيقه
                if ($discountCode) {
                     $discountCode->increment('current_uses');
                     Log::info("Discount code usage incremented.", ['code' => $discountCode->code]);
                }

                Log::info("Booking and Invoice created successfully in transaction.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id]);

            }); // نهاية Transaction

        } catch (\Exception $e) {
             Log::error('Booking Creation Transaction Failed: ' . $e->getMessage(), ['exception' => $e]);
             // عرض رسالة خطأ أكثر تحديداً إذا أمكن
             return back()->withInput()->with('error', 'حدث خطأ غير متوقع أثناء إنشاء الحجز. يرجى المحاولة مرة أخرى أو التواصل معنا.');
        }

        // --- 6. إرسال إشعار استلام طلب الحجز ---
        if ($booking && $user) {
            try {
                Log::info("Attempting to send NewBookingReceived notification for Booking ID: {$booking->id}");
                // تمرير طريقة الدفع للإشعار إذا كان يستخدمها
                $user->notify(new NewBookingReceived($booking, $validatedData['payment_method']));
                Log::info("NewBookingReceived notification queued/sent successfully for Booking ID: {$booking->id}");
            } catch (\Exception $e) {
                 Log::error("Failed to send NewBookingReceived notification for Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                 // لا توقف العملية بسبب فشل الإشعار، لكن سجل الخطأ
            }
        }

        // --- 7. التعامل مع طريقة الدفع وتوجيه المستخدم ---
        if ($validatedData['payment_method'] === 'bank_transfer') {
             Log::info("Redirecting to pending page for bank transfer.", ['booking_id' => $booking->id]);
             return redirect()->route('booking.pending', $booking->id);
        }
        elseif ($validatedData['payment_method'] === 'tamara') {
             Log::info("Initiating Tamara checkout.", ['booking_id' => $booking->id, 'invoice_id' => $invoice->id, 'amount_due' => $amountDueNow]);
             // تمرير الفاتورة، المبلغ المستحق الآن، وخيار الدفع إلى خدمة تمارا
             $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountDueNow, $paymentOption);
             if ($checkoutResponse && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['order_id'])) {
                  // تحديث مرجع بوابة الدفع في الفاتورة
                  $invoice->payment_gateway_ref = $checkoutResponse['order_id'];
                  // يمكن تغيير حالة الفاتورة إلى pending هنا إذا أردت
                  // $invoice->status = Invoice::STATUS_PENDING;
                  $invoice->save();
                  Log::info("Tamara checkout URL obtained. Redirecting user.", ['invoice_id' => $invoice->id, 'tamara_order_id' => $checkoutResponse['order_id']]);
                  return redirect()->away($checkoutResponse['checkout_url']);
             } else {
                  Log::error("Tamara initiateCheckout failed in BookingController.", ['invoice_id' => $invoice->id, 'response' => $checkoutResponse]);
                  // توجيه المستخدم لصفحة الانتظار مع رسالة خطأ واضحة
                  return redirect()->route('booking.pending', $booking->id)
                                 ->with('error', 'لم نتمكن من بدء الدفع مع تمارا حالياً. يمكنك المحاولة لاحقاً من صفحة حسابك أو اختيار التحويل البنكي إذا كان متاحاً.');
             }
        }

        // حالة غير متوقعة (لا يجب أن يصل الكود إلى هنا إذا كانت التحقق يعمل)
        Log::error('Unknown payment method after validation in submitBooking.', ['method' => $validatedData['payment_method']]);
        return redirect()->route('services.index')->with('error', 'حدث خطأ غير متوقع في طريقة الدفع المختارة.');
    } // نهاية submitBooking

    /**
     * عرض صفحة تأكيد استلام الطلب وانتظار الدفع.
     */
     public function showPendingPage(Booking $booking) : View|RedirectResponse
     {
         // ... (الكود السابق كما هو) ...
          if(Auth::id() !== $booking->user_id) { return redirect()->route('home')->with('error', 'غير مصرح لك بعرض هذا الحجز.'); }
          $booking->load(['service', 'invoice']);
          $bankAccounts = BankAccount::where('is_active', true)->get();
          $invoice = $booking->invoice;
          $amountDueNowOnPending = null; $paymentOptionOnPending = $invoice?->payment_option ?? 'full';
          if ($invoice && $invoice->status == Invoice::STATUS_UNPAID) { $amountDueNowOnPending = ($paymentOptionOnPending === 'down_payment') ? round($invoice->amount / 2, 0) : round($invoice->amount, 0); } // تقريب
          elseif ($invoice && $invoice->status == Invoice::STATUS_PARTIALLY_PAID) { $amountDueNowOnPending = $invoice->remaining_amount ?? round($invoice->amount - ($invoice->amount / 2), 0); } // تقريب

          return view('frontend.booking.pending', compact('booking', 'bankAccounts', 'amountDueNowOnPending', 'paymentOptionOnPending'));
     }

} // نهاية الكلاس BookingController