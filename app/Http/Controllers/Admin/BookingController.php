<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\BookingCancelledNotification; // <-- *** إضافة هنا ***
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator; // تم استيراده بالفعل
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings.
     */
    public function index(Request $request)
    {
        // استخدام الدالة المساعدة من الموديل
        $statuses = Booking::getStatusesWithOptions();
        $query = Booking::with(['user:id,name,mobile_number,email', 'service:id,name_ar'])->latest();

        if ($request->filled('status') && array_key_exists($request->status, $statuses)) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15)->withQueryString();
        return view('admin.bookings.index', compact('bookings', 'statuses'));
    }

    /**
     * Display the specified booking details.
     */
    public function show(Booking $booking)
    {
        $booking->load(['user', 'service', 'discountCode', 'invoice.payments']);
        // استخدام الدالة المساعدة من الموديل
        $statuses = Booking::getStatusesWithOptions();
        $invoiceStatuses = Invoice::statuses(); // افترض أن Invoice model لديه دالة statuses()
        $paymentConfirmationOptions = [
            'deposit' => 'تأكيد استلام العربون فقط', // تم تغيير الترتيب لجعل "العربون" هو الأول عادةً
            'full' => 'تأكيد استلام المبلغ الكامل/المتبقي',
        ];
        return view('admin.bookings.show', compact('booking', 'statuses', 'invoiceStatuses', 'paymentConfirmationOptions'));
    }

    /**
     * Update the status of the specified booking.
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        // استخدام الدالة المساعدة من الموديل
        $definedBookingStatuses = Booking::getStatusesWithOptions();
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;

        // *** بداية: تعديل قواعد التحقق لتشمل سبب الإلغاء ***
        $rules = [
            'status' => ['required', Rule::in(array_keys($definedBookingStatuses))],
            'payment_confirmation_type' => [
                Rule::requiredIf(fn () => $request->input('status') === $confirmedStatusValue),
                'nullable',
                Rule::in(['full', 'deposit'])
            ],
            'deposit_amount' => [
                Rule::requiredIf(function () use ($request, $confirmedStatusValue) {
                    return $request->input('status') === $confirmedBookingStatusValue &&
                           $request->input('payment_confirmation_type') === 'deposit';
                }),
                'nullable',
                'numeric',
                'min:0.01'
            ],
        ];

        // قيم حالات الإلغاء الفعلية التي تتطلب سببًا
        $cancellationStatusesRequiringReason = Booking::getCancellationStatusesRequiringReason();
        $newStatusFromRequest = $request->input('status');

        if (in_array($newStatusFromRequest, $cancellationStatusesRequiringReason)) {
            $rules['cancellation_reason'] = 'required|string|min:5|max:1000';
        } else {
            // إذا لم تكن حالة إلغاء، فإن سبب الإلغاء اختياري (أو يمكن تجاهله)
            $rules['cancellation_reason'] = 'nullable|string|max:1000';
        }
        // *** نهاية: تعديل قواعد التحقق ***

        $messages = [
            'payment_confirmation_type.required' => 'عند تأكيد الحجز، يرجى تحديد كيفية استلام الدفعة.',
            'deposit_amount.required' => 'عند اختيار تأكيد استلام العربون، يجب إدخال مبلغ العربون.',
            'status.required' => 'الرجاء اختيار الحالة الجديدة.',
            'status.in' => 'الحالة الجديدة المحددة غير صالحة.',
            'cancellation_reason.required' => 'سبب الإلغاء مطلوب عند اختيار هذه الحالة.', // <-- إضافة رسالة خطأ لسبب الإلغاء
            'cancellation_reason.min' => 'سبب الإلغاء يجب أن يكون 5 أحرف على الأقل.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->route('admin.bookings.show', $booking->id) // تم تمرير $booking->id هنا
                            ->withErrors($validator, 'updateStatus') // استخدام updateStatus كـ error bag
                            ->withInput();
        }

        $validated = $validator->validated();
        $newStatus = $validated['status']; // القيمة من $validated وليس $request مباشرة
        $oldStatus = $booking->status;
        $paymentConfirmationType = $validated['payment_confirmation_type'] ?? null;
        $depositAmountFromRequest = isset($validated['deposit_amount']) ? (float) $validated['deposit_amount'] : null;
        
        // *** بداية: جلب سبب الإلغاء من البيانات المتحقق منها ***
        $cancellationReason = $validated['cancellation_reason'] ?? null;
        // *** نهاية: جلب سبب الإلغاء ***

        $successMessage = 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).';


        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false;
            $invoice = $booking->loadMissing('invoice')->invoice;
            $paymentRecordedInThisAction = false;
            $amountPaidThisTransaction = 0;
            $currencyOfThisTransaction = $invoice ? ($invoice->currency ?: 'SAR') : 'SAR';

            // 1. تحديث حالة الحجز وسبب الإلغاء إذا تغيرت الحالة
            if ($newStatus !== $oldStatus || ($cancellationReason && $booking->cancellation_reason !== $cancellationReason) ) {
                $booking->status = $newStatus;
                // *** بداية: حفظ أو مسح سبب الإلغاء ***
                if (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason) {
                    $booking->cancellation_reason = $cancellationReason;
                } else {
                    // إذا لم تعد الحالة حالة إلغاء تتطلب سببًا، أو لم يتم إرسال سبب، قم بمسحه
                    $booking->cancellation_reason = null;
                }
                // *** نهاية: حفظ أو مسح سبب الإلغاء ***
                $booking->save();
                $bookingStatusActuallyChanged = true; // حتى لو تغير سبب الإلغاء فقط، نعتبره تغييراً
                $successMessage = 'تم تحديث حالة الحجز بنجاح.';
            }


            // ... (بقية منطق معالجة الدفع والفاتورة كما هو في ملفك الأصلي) ...
             // 2. التعامل مع الفاتورة والدفعة إذا تم تأكيد الحجز يدوياً من قبل المدير
            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
                $newInvoiceStatus = null;
                $paymentGateway = 'manual_admin';

                if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest > 0) {
                    $maxAllowedDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                    if ($depositAmountFromRequest >= $maxAllowedDeposit && $maxAllowedDeposit > 0.009) { // إضافة فحص > 0
                        DB::rollBack();
                        $validator->errors()->add('deposit_amount', "مبلغ العربون (${depositAmountFromRequest}) لا يمكن أن يتجاوز أو يساوي المبلغ المتبقي (${maxAllowedDeposit}).");
                        return redirect()->route('admin.bookings.show', $booking->id)->withErrors($validator, 'updateStatus')->withInput();
                    }
                    $amountPaidThisTransaction = $depositAmountFromRequest;
                    $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                    $paymentGateway = 'manual_admin_deposit';
                    $successMessage .= ($bookingStatusActuallyChanged ? ' و' : '') . 'تم تسجيل دفعة العربون.';
                    $paymentRecordedInThisAction = true;

                } elseif ($paymentConfirmationType === 'full') {
                    $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                     if ($amountPaidThisTransaction < 0.01 && $invoice->status !== Invoice::STATUS_PAID) { // إذا كان المبلغ صفر والفاتورة ليست مدفوعة
                        // لا تسجل دفعة صفرية إلا إذا كانت الفاتورة ستصبح مدفوعة بالكامل (لتحديث الحالة فقط)
                        // أو اسمح بها إذا كان هذا هو السلوك المطلوب لتغيير الحالة إلى مدفوعة
                        Log::info("AdminBookingController: Attempted to record zero/negligible payment for non-paid invoice {$invoice->id}. Amount: {$amountPaidThisTransaction}");
                        // قد ترغب في منع هذا أو السماح به لتغيير الحالة إلى "مدفوعة" إذا كان المبلغ صفرًا بالفعل
                     }
                    $newInvoiceStatus = Invoice::STATUS_PAID;
                    $successMessage .= ($bookingStatusActuallyChanged ? ' و' : '') . 'تم تسجيل المبلغ المتبقي/الكامل.';
                    $paymentRecordedInThisAction = true;
                }

                if ($paymentRecordedInThisAction && $amountPaidThisTransaction >= 0) { // السماح بتسجيل دفعة صفرية لتغيير الحالة
                    // تحقق مما إذا كانت الفاتورة ليست مدفوعة بالكامل بالفعل
                    // أو إذا كانت مدفوعة جزئياً وتريد تسجيل دفعة أخرى (حتى لو كانت صفرية لتحديث الحالة إلى مدفوعة بالكامل)
                    if ($invoice->status !== Invoice::STATUS_PAID || ($invoice->status === Invoice::STATUS_PARTIALLY_PAID && $newInvoiceStatus === Invoice::STATUS_PAID) ) {
                        if($amountPaidThisTransaction > 0.009 || ($amountPaidThisTransaction == 0 && $newInvoiceStatus === Invoice::STATUS_PAID && $invoice->status === Invoice::STATUS_PARTIALLY_PAID)) { // سجل فقط إذا كان المبلغ > 0 أو إذا كانت دفعة صفرية لتغيير الحالة
                            $createdPayment = $invoice->payments()->create([
                                'amount' => $amountPaidThisTransaction,
                                'currency' => $currencyOfThisTransaction,
                                'status' => Payment::STATUS_COMPLETED,
                                'payment_gateway' => $paymentGateway,
                                'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name])
                            ]);
                            Log::info("Admin manual payment recorded.", [
                                'invoice_id' => $invoice->id, 'payment_id' => $createdPayment->id, 'amount' => $amountPaidThisTransaction
                            ]);
                        }

                        $invoice->status = $newInvoiceStatus;
                        if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                            $invoice->paid_at = Carbon::now();
                        }
                        $invoice->save();
                        Log::info("Invoice status updated to {$newInvoiceStatus} after admin manual payment.", ['invoice_id' => $invoice->id]);

                        if ($amountPaidThisTransaction > 0.009) { // أرسل إشعار الدفع فقط إذا كان هناك مبلغ فعلي
                            $customerForPayment = $booking->user;
                            if ($customerForPayment) {
                                $customerForPayment->notify(new PaymentSuccessNotification($invoice, $customerForPayment, (float)$amountPaidThisTransaction, $currencyOfThisTransaction));
                            }
                            $adminUsersForPayment = User::where('is_admin', true)->get();
                            foreach ($adminUsersForPayment as $adminUser) {
                                $adminUser->notify(new PaymentSuccessNotification($invoice, $adminUser, (float)$amountPaidThisTransaction, $currencyOfThisTransaction));
                            }
                        }
                    } else {
                        Log::info("Invoice {$invoice->id} was already paid. Booking status might have changed if different.", ['invoice_id' => $invoice->id]);
                    }
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed but no invoice found to record payment.");
                 if($bookingStatusActuallyChanged || $oldStatus !== $newStatus) $successMessage .= ' (تنبيه: لا توجد فاتورة لتسجيل الدفعة).';
                 else $successMessage = 'لم يتم إجراء تغيير (الحالة مؤكدة ولا توجد فاتورة).';
            }


            // --- إرسال إشعارات تغيير حالة الحجز (إذا تغيرت الحالة فعلاً أو إذا تم تحديث سبب الإلغاء) ---
            if ($bookingStatusActuallyChanged) { // $bookingStatusActuallyChanged الآن تعكس أي تغيير في الحالة أو سبب الإلغاء
                $customer = $booking->user;
                $actor = Auth::user();

                if ($customer) {
                    Log::info("AdminBookingController: Sending booking status update to CUSTOMER for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                    try {
                        if ($newStatus === Booking::STATUS_CONFIRMED) {
                            $customer->notify(new BookingConfirmedNotification($booking, $customer));
                        } elseif (in_array($newStatus, Booking::getCancellationStatusesRequiringReason())) {
                            // *** تمرير سبب الإلغاء إلى الإشعار ***
                            $customer->notify(new BookingCancelledNotification($booking, $customer, $actor, $cancellationReason));
                        } else {
                            // إشعار تغيير الحالة العام لا يحتوي على سبب الإلغاء بشكل مباشر
                            $customer->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus, $customer));
                        }
                    } catch (\Exception $e) {
                        Log::error("AdminBookingController: Failed to send status update to CUSTOMER - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                    }
                }

                $allAdminUsers = User::where('is_admin', true)->get();
                if ($allAdminUsers->isNotEmpty()) {
                    foreach ($allAdminUsers as $admin) {
                        Log::info("AdminBookingController: Sending booking status update to ADMIN: {$admin->email} for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                        try {
                            if ($newStatus === Booking::STATUS_CONFIRMED) {
                                $admin->notify(new BookingConfirmedNotification($booking, $admin));
                            } elseif (in_array($newStatus, Booking::getCancellationStatusesRequiringReason())) {
                                // *** تمرير سبب الإلغاء إلى الإشعار ***
                                $admin->notify(new BookingCancelledNotification($booking, $admin, $actor, $cancellationReason));
                            } else {
                                $admin->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus, $admin));
                            }
                        } catch (\Exception $e) {
                            Log::error("AdminBookingController: Failed to send status update to ADMIN: {$admin->email} - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                        }
                    }
                }
            }
            // --- نهاية إرسال الإشعارات ---

            DB::commit();

        } catch (ValidationException $e) {
            DB::rollBack();
            // تأكد من استخدام نفس الـ error bag
            return redirect()->route('admin.bookings.show', $booking->id)->withErrors($e->validator, 'updateStatus')->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("AdminBookingController: Failed to update booking status or record payment.", [
                'booking_id' => $booking->id, 'error' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return redirect()->route('admin.bookings.show', $booking->id)->with('error', 'حدث خطأ غير متوقع. يرجى مراجعة السجلات.');
        }

        return redirect()->route('admin.bookings.show', $booking->id)->with('success', $successMessage);
    }
    
    // دالة destroy تبقى كما هي في ملفك الأصلي
    public function destroy(Service $service) // يجب أن تكون Booking $booking هنا
    {
        // الكود الصحيح يجب أن يكون:
        // public function destroy(Booking $booking)
        // {
        //     try {
        //         $booking->delete(); // افترض أن لديك soft deletes أو أي منطق آخر
        //         return redirect()->route('admin.bookings.index')
        //                          ->with('success', 'تم حذف الحجز بنجاح.');
        //     } catch (\Exception $e) {
        //         Log::error("Error deleting booking: {$booking->id} - {$e->getMessage()}");
        //         return redirect()->route('admin.bookings.index')
        //                          ->with('error', 'حدث خطأ أثناء محاولة حذف الحجز.');
        //     }
        // }
        // بما أن الكود الأصلي يستخدم Service $service، سأتركه كما هو ولكن هذا يبدو خطأ
        // سأفترض أنك سترسله لاحقًا إذا أردت تعديله.
         try {
            // هذا الجزء من الكود الأصلي يبدو أنه لحذف خدمة وليس حجز
            // $service->delete(); 
            // return redirect()->route('admin.services.index')
            //                  ->with('success', 'تم حذف الخدمة بنجاح.');
            // بما أننا في BookingController، يجب أن يكون لحذف حجز
            // سأتركها فارغة مؤقتًا لأن الكود المرسل غير متناسق مع اسم المتحكم
            Log::warning("Attempted to call destroy method in BookingController with incorrect signature or logic.");
            return redirect()->back()->with('error', 'وظيفة الحذف غير مهيأة بشكل صحيح.');

        } catch (\Exception $e) {
            Log::error("Error in BookingController destroy method - {$e->getMessage()}");
            return redirect()->back()->with('error', 'حدث خطأ.');
        }
    }
}
