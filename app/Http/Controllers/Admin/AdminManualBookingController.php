<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\User;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting; // لاستخدام رسوم خارج الأحساء
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
// استيراد الإشعارات إذا كنت سترسلها من هنا
// use App\Notifications\BookingConfirmedNotification;
// use App\Notifications\PaymentSuccessNotification;

class AdminManualBookingController extends Controller
{
    public function create()
    {
        $services = Service::where('is_active', true)->orderBy('name_ar')->get();
        // يمكنك تمرير المدن هنا إذا أردت ذلك من الإعدادات أو بشكل ثابت
        $outsideAhsaCities = [
            'الخبر' => 'الخبر', 'الظهران' => 'الظهران', 'الدمام' => 'الدمام', 
            'سيهات' => 'سيهات', 'القطيف' => 'القطيف',
        ];
        $outsideAhsaFee = (float)(Setting::where('key', 'outside_ahsa_fee')->value('value') ?? 300.00);

        return view('admin.manual-booking.create', compact('services', 'outsideAhsaCities', 'outsideAhsaFee'));
    }

    public function store(Request $request)
    {
        $outsideAhsaCitiesKeys = ['الخبر', 'الظهران', 'الدمام', 'سيهات', 'القطيف'];
        $rules = [
            // Customer Details
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => ['required', 'string', 'regex:/^05[0-9]{8}$/'], // قد تحتاج لإزالة unique إذا كنت ستحدّث المستخدم الموجود
            'customer_email' => ['required', 'string', 'email', 'max:255'], // قد تحتاج لإزالة unique

            // Booking Details
            'service_id' => 'required|integer|exists:services,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booking_time' => 'required|date_format:H:i',
            
            // Location Details
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
            
            // Payment Details by Admin
            'booking_total_amount_from_form' => 'required|numeric|min:0', // الإجمالي الذي يراه المدير في النموذج (بعد إضافة رسوم المنطقة يدويًا أو بالـ JS)
            'amount_paid_manually' => 'nullable|numeric|min:0', // ما دفعه العميل للمدير يدويًا
            'payment_option_for_customer' => ['required', Rule::in(['full', 'down_payment'])], // ماذا سيتوقع من العميل أن يدفع (الباقي)
        ];

        // رسائل الخطأ المخصصة
        $messages = [
            'customer_mobile.regex' => 'رقم الجوال يجب أن يكون بصيغة سعودية صحيحة (مثال: 05XXXXXXXX).',
            'booking_total_amount_from_form.required' => 'حقل مبلغ الحجز الإجمالي مطلوب.',
            'booking_total_amount_from_form.min' => 'مبلغ الحجز الإجمالي يجب أن يكون قيمة موجبة.',
            'amount_paid_manually.min' => 'المبلغ المدفوع يدويًا يجب أن يكون قيمة موجبة.',
            'payment_option_for_customer.required' => 'يرجى تحديد خيار الدفع المتوقع من العميل.',
        ];
        
        // التحقق من unicité البريد والجوال بشكل منفصل لتوفير رسائل خطأ أفضل إذا كان المستخدم جديدًا
        $existingUserByEmail = User::where('email', $request->customer_email)->first();
        if ($existingUserByEmail && (!$request->filled('user_id_to_update') || $existingUserByEmail->id != $request->user_id_to_update)) {
            return back()->withInput()->withErrors(['customer_email' => 'هذا البريد الإلكتروني مسجل لعميل آخر.']);
        }
        $existingUserByMobile = User::where('mobile_number', $request->customer_mobile)->first();
        if ($existingUserByMobile && (!$request->filled('user_id_to_update') || $existingUserByMobile->id != $request->user_id_to_update)) {
             return back()->withInput()->withErrors(['customer_mobile' => 'رقم الجوال هذا مسجل لعميل آخر.']);
        }


        $validatedData = $request->validate($rules, $messages);

        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['booking_date'] . ' ' . $validatedData['booking_time']);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'صيغة تاريخ أو وقت الحجز غير صحيحة.');
        }

        $user = null;
        $booking = null;
        $invoice = null;

        DB::beginTransaction();
        try {
            // 1. إنشاء أو إيجاد/تحديث المستخدم
            $user = User::where('email', $validatedData['customer_email'])->orWhere('mobile_number', $validatedData['customer_mobile'])->first();
            if ($user) {
                // تحديث بيانات المستخدم إذا اختلف الاسم أو الجوال (مع الحذر من تحديث الجوال إذا كان يستخدم للتحقق)
                $user->name = $validatedData['customer_name'];
                if ($user->mobile_number !== $validatedData['customer_mobile']) {
                    // قد تحتاج لإعادة التحقق من الجوال إذا تم تغييره، أو منع التغيير من هنا
                    // $user->mobile_verified_at = null; 
                }
                $user->mobile_number = $validatedData['customer_mobile'];
                $user->save();
                Log::info("Admin Manual Booking: Existing user found and details potentially updated.", ['user_id' => $user->id]);
            } else {
                $user = User::create([
                    'name' => $validatedData['customer_name'],
                    'email' => $validatedData['customer_email'],
                    'mobile_number' => $validatedData['customer_mobile'],
                    'password' => Hash::make(Str::random(12)), 
                    'mobile_verified_at' => now(), // نفترض أن المدير قام بالتحقق
                    'is_admin' => false,
                ]);
                Log::info("Admin Manual Booking: New user created.", ['user_id' => $user->id]);
            }

            // 2. حساب إجمالي الفاتورة (يشمل رسوم خارج المنطقة إذا طبقت)
            $service = Service::findOrFail($validatedData['service_id']);
            $baseServicePrice = (float) $service->price_sar;
            
            $outsideAhsaFeeValue = (float)(Setting::where('key', 'outside_ahsa_fee')->value('value') ?? 300.00);
            $currentOutsideLocationFeeApplied = 0.0;
            if ($validatedData['shooting_area_option'] === 'outside_ahsa') {
                $currentOutsideLocationFeeApplied = $outsideAhsaFeeValue;
            }
            // الإجمالي النهائي للفاتورة = سعر الخدمة + رسوم خارج المنطقة
            // (نفترض عدم وجود خصومات للحجوزات اليدوية، أو يمكنك إضافة منطق لها)
            $finalInvoiceAmount = round($baseServicePrice + $currentOutsideLocationFeeApplied, 2);
            
            // المبلغ الذي قام العميل بدفعه للمدير يدويًا
            $amountPaidManuallyByCustomer = (float)($validatedData['amount_paid_manually'] ?? 0.0);

            if ($amountPaidManuallyByCustomer > $finalInvoiceAmount) {
                 DB::rollBack();
                 return back()->withInput()->withErrors(['amount_paid_manually' => 'المبلغ المدفوع يدويًا لا يمكن أن يكون أكبر من إجمالي الفاتورة.']);
            }

            // خيار الدفع الذي سيظهر للعميل إذا كان هناك مبلغ متبقي
            $customerPaymentOptionForInvoice = $validatedData['payment_option_for_customer'];


            // 3. إنشاء الحجز
            $bookingStatus = Booking::STATUS_AWAITING_PAYMENT; // افتراضي للحجز اليدوي (لأنه معتمد من المدير)
            $downPaymentAmountForBookingRecord = null;

            if ($amountPaidManuallyByCustomer > 0.009) {
                // إذا تم دفع أي مبلغ يدويًا، اعتبر الحجز مؤكدًا مبدئيًا
                $isFull = $amountPaidManuallyByCustomer >= ($finalInvoiceAmount - 0.01);
                $bookingStatus = $isFull ? Booking::STATUS_CONFIRMED_PAID : Booking::STATUS_CONFIRMED_DEPOSIT;
                
                if ($customerPaymentOptionForInvoice === 'down_payment') {
                    // إذا كان الخيار للعميل هو دفع عربون، فإن `down_payment_amount` للحجز هو نصف الإجمالي
                    $downPaymentAmountForBookingRecord = round($finalInvoiceAmount / 2, 2);
                } elseif ($amountPaidManuallyByCustomer < ($finalInvoiceAmount - 0.01) ) {
                    // إذا دفع مبلغًا أقل من الإجمالي ولم يكن الخيار عربونًا، اعتبره عربونًا مبدئيًا
                     $downPaymentAmountForBookingRecord = $amountPaidManuallyByCustomer; 
                }
            } elseif ($customerPaymentOptionForInvoice === 'down_payment') {
                 // إذا لم يدفع شيئًا يدويًا، ولكن المطلوب من العميل دفع عربون عبر تمارا
                 $downPaymentAmountForBookingRecord = round($finalInvoiceAmount / 2, 2);
            }


            $booking = Booking::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'booking_datetime' => $bookingDateTime,
                'status' => $bookingStatus,
                'event_location' => $validatedData['event_location'],
                'groom_name_en' => $validatedData['groom_name_en'],
                'bride_name_en' => $validatedData['bride_name_en'],
                'customer_notes' => $validatedData['customer_notes'],
                'agreed_to_policy' => true, 
                'discount_code_id' => null, 
                'down_payment_amount' => $downPaymentAmountForBookingRecord,
                'shooting_area' => $validatedData['shooting_area_option'],
                'outside_location_city' => ($validatedData['shooting_area_option'] === 'outside_ahsa') ? $validatedData['outside_ahs_city'] : null,
                'outside_location_fee_applied' => ($currentOutsideLocationFeeApplied > 0) ? $currentOutsideLocationFeeApplied : null,
                // الحقول الجديدة للمزامنة مع موديل الحجز المعدل
                'total_price' => $finalInvoiceAmount,
                'requested_payment_option' => $customerPaymentOptionForInvoice,
                'requested_payment_method' => 'tamara', // أو يمكن جعلها متغيرة حسب الحاجة
            ]);

            // 4. إنشاء الفاتورة
            $invoiceStatus = Invoice::STATUS_UNPAID;
            if ($amountPaidManuallyByCustomer > 0.009) {
                if ($amountPaidManuallyByCustomer >= ($finalInvoiceAmount - 0.01)) { // هامش صغير
                    $invoiceStatus = Invoice::STATUS_PAID;
                } else {
                    $invoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                }
            }

            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'invoice_number' => Invoice::generateUniqueInvoiceNumber(), // استخدام دالة الموديل
                'amount' => $finalInvoiceAmount,
                'currency' => 'SAR',
                'status' => $invoiceStatus,
                // **هنا نحدد أن طريقة الدفع المتبقية/المطلوبة هي تمارا**
                'payment_method' => 'tamara', 
                'payment_option' => $customerPaymentOptionForInvoice, // ماذا نتوقع من العميل أن يدفع لاحقًا
                'due_date' => $bookingDateTime->isFuture() ? $bookingDateTime->copy()->subDays(1) : Carbon::today(), // استخدام copy()
                'paid_at' => ($invoiceStatus === Invoice::STATUS_PAID || $invoiceStatus === Invoice::STATUS_PARTIALLY_PAID) ? Carbon::now() : null,
            ]);
            
            $booking->invoice_id = $invoice->id;
            $booking->save();

            // 5. إنشاء سجل دفع للمبلغ المدفوع يدويًا (إذا وجد)
            if ($amountPaidManuallyByCustomer > 0.009) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => 'MANUAL_ADMIN-' . Str::uuid()->toString(),
                    'amount' => $amountPaidManuallyByCustomer,
                    'currency' => 'SAR',
                    'status' => Payment::STATUS_COMPLETED,
                    'payment_gateway' => 'manual_by_admin', // أو 'cash', 'bank_transfer_admin_confirmed'
                    'payment_details' => json_encode([
                        'admin_id' => auth()->id(), 
                        'admin_name' => auth()->user()->name, 
                        'entry_type' => 'manual_booking_initial_payment'
                    ]),
                ]);
            }
            
            // 6. تحديث حالة الحجز النهائية بناءً على الدفع
            if ($invoice->status === Invoice::STATUS_PAID) {
                $booking->status = Booking::STATUS_CONFIRMED_PAID;
                $booking->save();
            } else if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                $booking->status = Booking::STATUS_CONFIRMED_DEPOSIT;
                $booking->save();
            } else if ($invoice->status === Invoice::STATUS_UNPAID && $amountPaidManuallyByCustomer <= 0.009){
                 // إذا لم يتم دفع شيء، والمطلوب دفعة عبر تمارا، ننتقل لحالة انتظار الدفع
                 $booking->status = Booking::STATUS_AWAITING_PAYMENT;
                 $booking->save();
            }


            DB::commit();
            Log::info("Admin Manual Booking: Successfully created booking and invoice.", [
                'user_id' => $user->id, 'booking_id' => $booking->id, 'invoice_id' => $invoice->id
            ]);

            // يمكنك إضافة إرسال إشعار للعميل هنا
            // مثلاً، إشعار بإنشاء حجز جديد مع تفاصيل الفاتورة ورابط للدفع إذا كان هناك مبلغ متبقي
            // if ($invoice->remaining_amount > 0) {
            // User::find($user->id)->notify(new ManualBookingCreatedForCustomerNotification($booking, $invoice));
            // } else {
            // User::find($user->id)->notify(new ManualBookingFullyPaidNotification($booking, $invoice));
            // }

            return redirect()->route('admin.bookings.show', $booking->id)
                             ->with('success', 'تم إنشاء الحجز يدويًا بنجاح. الفاتورة جاهزة ويمكن للعميل دفع المتبقي عبر تمارا إذا لزم الأمر.');

        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error('Admin Manual Booking: Validation failed.', ['errors' => $e->errors()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Manual Booking: Failed to create booking.', [
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 2000) // زيادة طول التتبع
            ]);
            return back()->withInput()->with('error', 'فشل إنشاء الحجز اليدوي: ' . $e->getMessage());
        }
    }
}
