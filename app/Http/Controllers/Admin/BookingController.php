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
use App\Notifications\BookingCancelledNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings.
     */
    public function index(Request $request)
    {
        $statuses = Booking::getStatusesWithOptions(); // <-- استخدام الدالة الجديدة
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
        $statuses = Booking::getStatusesWithOptions(); // <-- استخدام الدالة الجديدة
        $invoiceStatuses = Invoice::statuses(); // افترض أن Invoice model لديه دالة statuses()
        $paymentConfirmationOptions = [
            'deposit' => 'تأكيد استلام العربون فقط',
            'full' => 'تأكيد استلام المبلغ الكامل/المتبقي',
        ];
        return view('admin.bookings.show', compact('booking', 'statuses', 'invoiceStatuses', 'paymentConfirmationOptions'));
    }

    /**
     * Update the status of the specified booking.
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $definedBookingStatuses = Booking::getStatusesWithOptions();
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;
        $cancellationStatuses = Booking::getCancellationStatusesRequiringReason(); // حالات الإلغاء التي تتطلب سبب
        // يمكنك إضافة جميع حالات الإلغاء هنا إذا أردت تطبيق نفس المنطق على الفاتورة
        $allCancellationStatusValues = [
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_CANCELLED_BY_USER,
            // Booking::STATUS_CANCELLED, // إذا كانت لديك حالة إلغاء عامة
        ];


        $rules = [
            'status' => ['required', Rule::in(array_keys($definedBookingStatuses))],
            'payment_confirmation_type' => [
                Rule::requiredIf(fn () => $request->input('status') === $confirmedStatusValue),
                'nullable',
                Rule::in(['full', 'deposit'])
            ],
            'deposit_amount' => [
                Rule::requiredIf(function () use ($request, $confirmedStatusValue) {
                    return $request->input('status') === $confirmedStatusValue &&
                           $request->input('payment_confirmation_type') === 'deposit';
                }),
                'nullable',
                'numeric',
                'min:0.01'
            ],
        ];

        $newStatusFromRequest = $request->input('status');

        if (in_array($newStatusFromRequest, $cancellationStatuses)) { // فقط الحالات التي حددناها تتطلب سبب
            $rules['cancellation_reason'] = 'required|string|min:5|max:1000';
        } else {
            $rules['cancellation_reason'] = 'nullable|string|max:1000';
        }

        $messages = [
            'payment_confirmation_type.required' => 'عند تأكيد الحجز، يرجى تحديد كيفية استلام الدفعة.',
            'deposit_amount.required' => 'عند اختيار تأكيد استلام العربون، يجب إدخال مبلغ العربون.',
            'status.required' => 'الرجاء اختيار الحالة الجديدة.',
            'status.in' => 'الحالة الجديدة المحددة غير صالحة.',
            'cancellation_reason.required' => 'سبب الإلغاء مطلوب عند اختيار هذه الحالة.',
            'cancellation_reason.min' => 'سبب الإلغاء يجب أن يكون ٥ أحرف على الأقل.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->route('admin.bookings.show', $booking->id)
                            ->withErrors($validator, 'updateStatus')
                            ->withInput();
        }

        $validated = $validator->validated();
        $newStatus = $validated['status'];
        $oldStatus = $booking->status;
        $paymentConfirmationType = $validated['payment_confirmation_type'] ?? null;
        $depositAmountFromRequest = isset($validated['deposit_amount']) ? (float) $validated['deposit_amount'] : null;
        $cancellationReason = $validated['cancellation_reason'] ?? null;
        $successMessage = 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).';

        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false;
            $invoice = $booking->loadMissing('invoice')->invoice; // تأكد من تحميل الفاتورة
            $paymentRecordedInThisAction = false;
            $amountPaidThisTransaction = 0;
            $currencyOfThisTransaction = $invoice ? ($invoice->currency ?: 'SAR') : 'SAR';

            if ($newStatus !== $oldStatus || ($cancellationReason && $booking->cancellation_reason !== $cancellationReason) || (in_array($newStatus, $cancellationStatuses) && $cancellationReason !== $booking->cancellation_reason ) ) {
                $booking->status = $newStatus;
                if (in_array($newStatus, $cancellationStatuses) && $cancellationReason) {
                    $booking->cancellation_reason = $cancellationReason;
                } elseif (!in_array($newStatus, $cancellationStatuses)) {
                    $booking->cancellation_reason = null;
                }
                $booking->save();
                $bookingStatusActuallyChanged = true;
                $successMessage = 'تم تحديث حالة الحجز بنجاح.';
            }
            
            // *** بداية: تعديل حالة الفاتورة عند إلغاء الحجز ***
            if ($invoice && in_array($newStatus, $allCancellationStatusValues)) {
                // قم بإلغاء الفاتورة فقط إذا لم تكن مدفوعة بالكامل أو ملغاة بالفعل
                if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                    $invoice->status = Invoice::STATUS_CANCELLED;
                    // يمكنك إضافة ملاحظة إلى الفاتورة هنا إذا كان لديك حقل لذلك
                    // $invoice->cancellation_notes = "تم إلغاء الحجز المرتبط رقم: " . $booking->id;
                    $invoice->save();
                    Log::info("Invoice ID {$invoice->id} status updated to CANCELLED due to booking cancellation.", ['booking_id' => $booking->id]);
                    $successMessage .= ' وتم تحديث حالة الفاتورة المرتبطة إلى ملغاة.';
                }
            }
            // *** نهاية: تعديل حالة الفاتورة عند إلغاء الحجز ***


            // منطق معالجة الدفع والفاتورة إذا كان الحجز مؤكدًا (يبقى كما هو)
            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
                // ... (الكود الخاص بمعالجة الدفع اليدوي للفاتورة المؤكدة) ...
                // تأكد من أن هذا المنطق لا يتعارض مع إلغاء الفاتورة أعلاه.
                // هذا المنطق يجب أن يُنفذ فقط إذا لم يتم إلغاء الحجز.
                if (!in_array($newStatus, $allCancellationStatusValues)) { // تحقق إضافي
                    $newInvoiceStatus = null;
                    $paymentGateway = 'manual_admin';

                    if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest > 0) {
                        $maxAllowedDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        if ($depositAmountFromRequest >= $maxAllowedDeposit && $maxAllowedDeposit > 0.009) {
                            DB::rollBack();
                            $validator->errors()->add('deposit_amount', "مبلغ العربون (${depositAmountFromRequest}) لا يمكن أن يتجاوز أو يساوي المبلغ المتبقي (${maxAllowedDeposit}).");
                            return redirect()->route('admin.bookings.show', $booking->id)->withErrors($validator, 'updateStatus')->withInput();
                        }
                        $amountPaidThisTransaction = $depositAmountFromRequest;
                        $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                        $paymentGateway = 'manual_admin_deposit';
                        $currentMsgPrefix = ($bookingStatusActuallyChanged || $oldStatus !== $newStatus) ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و');
                        if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).') $successMessage = ''; // مسح الرسالة الافتراضية إذا كان هناك إجراء دفع
                        $successMessage .= $currentMsgPrefix . 'تم تسجيل دفعة العربون.';
                        $paymentRecordedInThisAction = true;

                    } elseif ($paymentConfirmationType === 'full') {
                        $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        $newInvoiceStatus = Invoice::STATUS_PAID;
                        $currentMsgPrefix = ($bookingStatusActuallyChanged || $oldStatus !== $newStatus) ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و');
                         if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).') $successMessage = '';
                        $successMessage .= $currentMsgPrefix . 'تم تسجيل المبلغ المتبقي/الكامل.';
                        $paymentRecordedInThisAction = true;
                    }

                    if ($paymentRecordedInThisAction && $amountPaidThisTransaction >= 0) { 
                        if ($invoice->status !== Invoice::STATUS_PAID || ($invoice->status === Invoice::STATUS_PARTIALLY_PAID && $newInvoiceStatus === Invoice::STATUS_PAID) ) {
                            if($amountPaidThisTransaction > 0.009 || ($amountPaidThisTransaction == 0 && $newInvoiceStatus === Invoice::STATUS_PAID && $invoice->status === Invoice::STATUS_PARTIALLY_PAID)) {
                                $createdPayment = $invoice->payments()->create([
                                    'amount' => $amountPaidThisTransaction,
                                    'currency' => $currencyOfThisTransaction,
                                    'status' => Payment::STATUS_COMPLETED,
                                    'payment_gateway' => $paymentGateway,
                                    'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name])
                                ]);
                                Log::info("Admin manual payment recorded.", ['invoice_id' => $invoice->id, 'payment_id' => $createdPayment->id, 'amount' => $amountPaidThisTransaction]);
                            }

                            $invoice->status = $newInvoiceStatus;
                            if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to {$newInvoiceStatus} after admin manual payment.", ['invoice_id' => $invoice->id]);

                            if ($amountPaidThisTransaction > 0.009) {
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
                             if ($oldStatus === $newStatus && !$bookingStatusActuallyChanged && str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز')) {
                                 $successMessage = 'لم يتم إجراء تغيير (الفاتورة مدفوعة والحالة لم تتغير).';
                             }
                        }
                    }
                } // نهاية التحقق من أن الحجز لم يتم إلغاؤه
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed but no invoice found to record payment.");
                 if($bookingStatusActuallyChanged || $oldStatus !== $newStatus) $successMessage .= ' (تنبيه: لا توجد فاتورة لتسجيل الدفعة).';
                 else if (str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز')) $successMessage = 'لم يتم إجراء تغيير (الحالة مؤكدة ولا توجد فاتورة).';
            }


            if ($bookingStatusActuallyChanged) {
                $customer = $booking->user;
                $actor = Auth::user();

                if ($customer) {
                    Log::info("AdminBookingController: Sending booking status update to CUSTOMER for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                    try {
                        if ($newStatus === Booking::STATUS_CONFIRMED) {
                            $customer->notify(new BookingConfirmedNotification($booking, $customer));
                        } elseif (in_array($newStatus, $allCancellationStatusValues)) { // استخدام allCancellationStatusValues
                            $customer->notify(new BookingCancelledNotification($booking, $customer, $actor, $booking->cancellation_reason));
                        } else {
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
                            } elseif (in_array($newStatus, $allCancellationStatusValues)) { // استخدام allCancellationStatusValues
                                $admin->notify(new BookingCancelledNotification($booking, $admin, $actor, $booking->cancellation_reason));
                            } else {
                                $admin->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus, $admin));
                            }
                        } catch (\Exception $e) {
                            Log::error("AdminBookingController: Failed to send status update to ADMIN: {$admin->email} - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
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
    
    public function destroy(Booking $booking)
    {
        try {
            $bookingId = $booking->id;
            // يمكنك إضافة منطق هنا لإلغاء الفاتورة المرتبطة أيضًا إذا أردت عند حذف الحجز
            if ($booking->invoice && $booking->invoice->status !== Invoice::STATUS_PAID) {
                 $booking->invoice->status = Invoice::STATUS_CANCELLED;
                 // $booking->invoice->cancellation_notes = "تم حذف الحجز المرتبط رقم: " . $bookingId;
                 $booking->invoice->save();
                 Log::info("Invoice ID {$booking->invoice->id} cancelled due to booking deletion.", ['booking_id' => $bookingId]);
            }
            $booking->delete();
            Log::info("Booking ID {$bookingId} deleted by admin ID: " . Auth::id());
            return redirect()->route('admin.bookings.index')
                             ->with('success', 'تم حذف الحجز بنجاح (وتم إلغاء الفاتورة المرتبطة إذا لم تكن مدفوعة).');
        } catch (\Exception $e) {
            Log::error("Error deleting booking: {$booking->id} - {$e->getMessage()}");
            return redirect()->route('admin.bookings.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الحجز.');
        }
    }
}
