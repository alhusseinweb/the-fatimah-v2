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
        $allCancellationStatusValues = [
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_CANCELLED_BY_USER,
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

        if (in_array($newStatusFromRequest, $cancellationStatuses)) {
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
            $invoice = $booking->loadMissing('invoice')->invoice;
            $paymentRecordedInThisAction = false;
            $amountPaidThisTransaction = 0.0; // تهيئة كـ float
            $currencyOfThisTransaction = $invoice ? ($invoice->currency ?: 'SAR') : 'SAR';
            $createdPayment = null; // لتعريف المتغير خارج نطاق if

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
            
            if ($invoice && in_array($newStatus, $allCancellationStatusValues)) {
                if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                    $invoice->status = Invoice::STATUS_CANCELLED;
                    $invoice->save();
                    Log::info("Invoice ID {$invoice->id} status updated to CANCELLED due to booking cancellation.", ['booking_id' => $booking->id]);
                    $successMessage .= ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? 'تم' : ' و') . ' تحديث حالة الفاتورة المرتبطة إلى ملغاة.';
                }
            }

            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
                if (!in_array($newStatus, $allCancellationStatusValues)) {
                    $newInvoiceStatus = $invoice->status; // ابدأ بالحالة الحالية للفاتورة
                    $paymentGateway = 'manual_admin';

                    if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest !== null && $depositAmountFromRequest > 0) {
                        $maxAllowedDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        if ($depositAmountFromRequest >= $maxAllowedDeposit && $maxAllowedDeposit > 0.009) {
                            DB::rollBack();
                            $validator->errors()->add('deposit_amount', "مبلغ العربون (${depositAmountFromRequest}) لا يمكن أن يتجاوز أو يساوي المبلغ المتبقي (${maxAllowedDeposit}). اختر 'تأكيد استلام المبلغ الكامل' بدلاً من ذلك.");
                            return redirect()->route('admin.bookings.show', $booking->id)->withErrors($validator, 'updateStatus')->withInput();
                        }
                        $amountPaidThisTransaction = $depositAmountFromRequest;
                        $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                        $paymentGateway = 'manual_admin_deposit';
                        $currentMsgPrefix = ($bookingStatusActuallyChanged || $oldStatus !== $newStatus) ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و');
                        if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).') $successMessage = '';
                        $successMessage .= $currentMsgPrefix . 'تم تسجيل دفعة العربون.';
                        $paymentRecordedInThisAction = true;

                    } elseif ($paymentConfirmationType === 'full') {
                        $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                         // تأكد من أن هناك مبلغ فعلي يتم دفعه إذا لم تكن مدفوعة جزئيًا بالفعل
                        if ($invoice->status === Invoice::STATUS_PAID && $amountPaidThisTransaction <=0) {
                             // لا تقم بتسجيل دفعة صفر إذا كانت الفاتورة مدفوعة بالفعل ولم يتم تغيير المبلغ
                        } else {
                            $newInvoiceStatus = Invoice::STATUS_PAID;
                            $currentMsgPrefix = ($bookingStatusActuallyChanged || $oldStatus !== $newStatus) ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و');
                            if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).') $successMessage = '';
                            $successMessage .= $currentMsgPrefix . 'تم تسجيل المبلغ المتبقي/الكامل.';
                            $paymentRecordedInThisAction = true;
                        }
                    }

                    if ($paymentRecordedInThisAction && ($amountPaidThisTransaction > 0.009 || ($newInvoiceStatus === Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PAID))) {
                        // لا تسجل دفعة إذا كانت الفاتورة مدفوعة بالفعل والمبلغ المدفوع صفر
                        if (!($invoice->status === Invoice::STATUS_PAID && $amountPaidThisTransaction == 0)) {
                            $createdPayment = $invoice->payments()->create([
                                'amount' => $amountPaidThisTransaction,
                                'currency' => $currencyOfThisTransaction,
                                'status' => Payment::STATUS_COMPLETED,
                                'payment_gateway' => $paymentGateway,
                                'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name])
                            ]);
                            Log::info("Admin manual payment recorded.", ['invoice_id' => $invoice->id, 'payment_id' => $createdPayment->id, 'amount' => $amountPaidThisTransaction]);
                        }

                        // تحديث حالة الفاتورة وتاريخ الدفع فقط إذا لم تكن مدفوعة بالكامل سابقًا أو إذا تغيرت الحالة
                        if ($invoice->status !== Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PAID) {
                            $invoice->status = $newInvoiceStatus;
                            if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to {$newInvoiceStatus} after admin manual payment.", ['invoice_id' => $invoice->id]);
                        }
                        
                        // إرسال إشعار نجاح الدفع فقط إذا تم تسجيل دفعة فعلية بمبلغ أكبر من صفر
                        if (isset($createdPayment) && $createdPayment->amount > 0.009) {
                            $customerForPayment = $booking->user;
                            if ($customerForPayment) {
                                try {
                                    $customerForPayment->notify(new PaymentSuccessNotification(
                                        $invoice,
                                        (float)$createdPayment->amount, // استخدام مبلغ الدفعة المسجلة
                                        $createdPayment->currency    // استخدام عملة الدفعة المسجلة
                                    ));
                                    Log::info("PaymentSuccessNotification sent to customer {$customerForPayment->id} for invoice {$invoice->id}");
                                } catch (\Exception $e) {
                                    Log::error("AdminBookingController: Failed to send PaymentSuccessNotification to CUSTOMER - Invoice ID: {$invoice->id}", ['error' => $e->getMessage()]);
                                }
                            }

                            $adminUsersForPayment = User::where('is_admin', true)->get();
                            foreach ($adminUsersForPayment as $adminUser) {
                                if ($adminUser->id !== Auth::id() && (!$customerForPayment || $adminUser->id !== $customerForPayment->id)) {
                                    try {
                                        $adminUser->notify(new PaymentSuccessNotification(
                                            $invoice,
                                            (float)$createdPayment->amount,
                                            $createdPayment->currency
                                        ));
                                        Log::info("PaymentSuccessNotification sent to admin {$adminUser->id} for invoice {$invoice->id}");
                                    } catch (\Exception $e) {
                                        Log::error("AdminBookingController: Failed to send PaymentSuccessNotification to ADMIN: {$adminUser->email} - Invoice ID: {$invoice->id}", ['error' => $e->getMessage()]);
                                    }
                                }
                            }
                        }
                    } elseif ($invoice->status === Invoice::STATUS_PAID && !$bookingStatusActuallyChanged && $oldStatus === $newStatus) {
                        Log::info("Invoice {$invoice->id} was already paid. No new payment recorded. Booking status unchanged.", ['invoice_id' => $invoice->id]);
                        if (str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز')) {
                           $successMessage = 'لم يتم إجراء تغيير (الفاتورة مدفوعة والحالة لم تتغير).';
                        }
                    }
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed but no invoice found to record payment.");
                if($bookingStatusActuallyChanged || $oldStatus !== $newStatus) $successMessage .= ' (تنبيه: لا توجد فاتورة لتسجيل الدفعة).';
                else if (str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز')) $successMessage = 'لم يتم إجراء تغيير (الحالة مؤكدة ولا توجد فاتورة).';
            }

            if ($bookingStatusActuallyChanged) {
                $customer = $booking->user;
                $actor = Auth::user(); // المدير الحالي هو الفاعل

                if ($customer) {
                    Log::info("AdminBookingController: Sending booking status update to CUSTOMER for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                    try {
                        if ($newStatus === Booking::STATUS_CONFIRMED && !$paymentRecordedInThisAction) { // لا ترسل تأكيد حجز إذا تم إرسال إشعار دفع (يفترض أن الدفع يؤكد الحجز)
                            // ولكن إذا تم تأكيد الحجز بدون تسجيل دفعة في هذه العملية، أرسل تأكيد الحجز
                            $customer->notify(new BookingConfirmedNotification($booking));
                        } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                            $customer->notify(new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason));
                        } elseif ($newStatus !== Booking::STATUS_CONFIRMED) { // تجنب إرسال StatusChanged إذا كان CONFIRMED وتم التعامل معه أو سيتم عبر الدفع
                            $customer->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus));
                        }
                    } catch (\Exception $e) {
                        Log::error("AdminBookingController: Failed to send status update to CUSTOMER - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                    }
                }

                $allAdminUsers = User::where('is_admin', true)->get();
                if ($allAdminUsers->isNotEmpty()) {
                    foreach ($allAdminUsers as $admin) {
                         if ($admin->id !== Auth::id()) { // لا ترسل للمدير الذي قام بالإجراء
                            Log::info("AdminBookingController: Sending booking status update to ADMIN: {$admin->email} for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                            try {
                                if ($newStatus === Booking::STATUS_CONFIRMED && !$paymentRecordedInThisAction) {
                                    $admin->notify(new BookingConfirmedNotification($booking));
                                } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                                    $admin->notify(new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason));
                                } elseif ($newStatus !== Booking::STATUS_CONFIRMED) {
                                    $admin->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus));
                                }
                            } catch (\Exception $e) {
                                Log::error("AdminBookingController: Failed to send status update to ADMIN: {$admin->email} - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                            }
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
        if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).') {
            // إذا لم يتم تغيير أي شيء، قد لا تحتاج لعرض رسالة نجاح على الإطلاق أو رسالة مختلفة
            return redirect()->route('admin.bookings.show', $booking->id);
        }
        return redirect()->route('admin.bookings.show', $booking->id)->with('success', $successMessage);
    }
    
    public function destroy(Booking $booking)
    {
        try {
            $bookingId = $booking->id;
            if ($booking->invoice && !in_array($booking->invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED])) {
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
