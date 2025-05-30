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
    public function index(Request $request) // <<--- هذه الدالة يجب أن تكون موجودة
    {
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
    public function show(Booking $booking) // <<--- هذه الدالة يجب أن تكون موجودة
    {
        $booking->load(['user', 'service', 'discountCode', 'invoice.payments']);
        $statuses = Booking::getStatusesWithOptions();
        $invoiceStatuses = Invoice::statuses(); // افترض أن Invoice model لديه دالة statuses()
        $paymentConfirmationOptions = [
            'deposit' => 'تأكيد استلام العربون فقط',
            'full' => 'تأكيد استلام المبلغ الكامل/المتبقي',
        ];
        return view('admin.bookings.show', compact('booking', 'statuses', 'invoiceStatuses', 'paymentConfirmationOptions'));
    }

    /**
     * Update the status of the specified booking.
     * (الكود المعدل لهذه الدالة كما في الردود السابقة)
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $definedBookingStatuses = Booking::getStatusesWithOptions();
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;
        $cancellationStatuses = Booking::getCancellationStatusesRequiringReason();
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
        $notificationsSentSummary = [];

        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false;
            $invoice = $booking->loadMissing('invoice')->invoice;
            $paymentRecordedInThisAction = false;
            $createdPayment = null;

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
                    $newInvoiceStatus = $invoice->status;
                    $paymentGateway = 'manual_admin';
                    $amountPaidThisTransaction = 0.0;
                    $currencyOfThisTransaction = $invoice->currency ?: 'SAR';

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
                        if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' || $successMessage === 'تم تحديث حالة الحجز بنجاح.') {
                            $successMessage = $paymentConfirmationType === 'deposit' ? 'تم تسجيل دفعة العربون بنجاح.' : 'تم تسجيل المبلغ الكامل/المتبقي بنجاح.';
                            if($bookingStatusActuallyChanged) $successMessage = 'تم تحديث حالة الحجز و' . lcfirst($successMessage);
                        } else {
                             $successMessage .= ($paymentConfirmationType === 'deposit' ? ' وتم تسجيل دفعة العربون.' : ' وتم تسجيل المبلغ الكامل/المتبقي.');
                        }

                    } elseif ($paymentConfirmationType === 'full') {
                        $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        if (!($invoice->status === Invoice::STATUS_PAID && $amountPaidThisTransaction <=0)) {
                             $newInvoiceStatus = Invoice::STATUS_PAID;
                             if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' || $successMessage === 'تم تحديث حالة الحجز بنجاح.') {
                                $successMessage = $paymentConfirmationType === 'deposit' ? 'تم تسجيل دفعة العربون بنجاح.' : 'تم تسجيل المبلغ الكامل/المتبقي بنجاح.';
                                if($bookingStatusActuallyChanged) $successMessage = 'تم تحديث حالة الحجز و' . lcfirst($successMessage);
                            } else {
                                 $successMessage .= ($paymentConfirmationType === 'deposit' ? ' وتم تسجيل دفعة العربون.' : ' وتم تسجيل المبلغ الكامل/المتبقي.');
                            }
                        }
                    }

                    if ($amountPaidThisTransaction > 0.009 || ($newInvoiceStatus === Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PAID)) {
                        if (!($invoice->status === Invoice::STATUS_PAID && $amountPaidThisTransaction == 0)) {
                            $createdPayment = $invoice->payments()->create([
                                'amount' => $amountPaidThisTransaction,
                                'currency' => $currencyOfThisTransaction,
                                'status' => Payment::STATUS_COMPLETED,
                                'payment_gateway' => $paymentGateway,
                                'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name])
                            ]);
                            Log::info("Admin manual payment recorded.", ['invoice_id' => $invoice->id, 'payment_id' => $createdPayment->id, 'amount' => $amountPaidThisTransaction]);
                            $paymentRecordedInThisAction = true; 
                        }

                        if ($invoice->status !== Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PAID) {
                            $invoice->status = $newInvoiceStatus;
                            if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to {$newInvoiceStatus} after admin manual payment.", ['invoice_id' => $invoice->id]);
                        }
                        
                        if ($paymentRecordedInThisAction && isset($createdPayment) && $createdPayment->amount > 0.009) {
                            $customerForPayment = $booking->user;
                            if ($customerForPayment) {
                                Log::info("AdminBookingController: Attempting to queue PaymentSuccessNotification for CUSTOMER {$customerForPayment->id}");
                                try {
                                    $customerForPayment->notify(new PaymentSuccessNotification(
                                        $invoice, (float)$createdPayment->amount, $createdPayment->currency
                                    ));
                                    Log::info("PaymentSuccessNotification queued for CUSTOMER {$customerForPayment->id} for invoice {$invoice->id}");
                                    $notificationsSentSummary[] = "نجاح الدفع للعميل";
                                } catch (\Exception $e) {
                                    Log::error("AdminBookingController: Failed to queue PaymentSuccessNotification to CUSTOMER - Invoice ID: {$invoice->id}", ['error' => $e->getMessage()]);
                                }
                            }

                            $adminUsersForPayment = User::where('is_admin', true)->where('id', '!=', Auth::id())->get();
                            Log::info("AdminBookingController: Found " . $adminUsersForPayment->count() . " other admins for PaymentSuccessNotification.");
                            foreach ($adminUsersForPayment as $adminUser) {
                                Log::info("AdminBookingController: Attempting to queue PaymentSuccessNotification for ADMIN {$adminUser->id}");
                                try {
                                    $adminUser->notify(new PaymentSuccessNotification(
                                        $invoice, (float)$createdPayment->amount, $createdPayment->currency
                                    ));
                                    Log::info("PaymentSuccessNotification queued for ADMIN {$adminUser->id} for invoice {$invoice->id}");
                                    $notificationsSentSummary[] = "نجاح الدفع للمدير {$adminUser->email}";
                                } catch (\Exception $e) {
                                    Log::error("AdminBookingController: Failed to queue PaymentSuccessNotification to ADMIN: {$adminUser->email} - Invoice ID: {$invoice->id}", ['error' => $e->getMessage()]);
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
                $actor = Auth::user();

                $shouldSendBookingConfirmed = ($newStatus === Booking::STATUS_CONFIRMED);
                // لإرسال تأكيد الحجز دائماً عند تأكيد الحجز، بغض النظر عن الدفع في هذا الإجراء، علّق السطرين التاليين أو احذفهما
                // if ($paymentRecordedInThisAction) {
                //     $shouldSendBookingConfirmed = false; 
                //     Log::info("BookingConfirmedNotification will be SKIPPED for booking ID {$booking->id} because a payment was recorded in this action.");
                // }

                if ($customer) {
                    Log::info("AdminBookingController: Processing other status update notifications for CUSTOMER Booking ID: {$booking->id} (NewStatus:{$newStatus})");
                    try {
                        if ($shouldSendBookingConfirmed) {
                            Log::info("AdminBookingController: Attempting to queue BookingConfirmedNotification for CUSTOMER {$customer->id}");
                            $customer->notify(new BookingConfirmedNotification($booking));
                            $notificationsSentSummary[] = "تأكيد الحجز للعميل";
                        } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                            Log::info("AdminBookingController: Attempting to queue BookingCancelledNotification for CUSTOMER {$customer->id}");
                            $customer->notify(new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason));
                            $notificationsSentSummary[] = "إلغاء الحجز للعميل";
                        } elseif ($newStatus !== Booking::STATUS_CONFIRMED) {
                            Log::info("AdminBookingController: Attempting to queue BookingStatusChangedNotification for CUSTOMER {$customer->id}");
                            $customer->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus));
                            $notificationsSentSummary[] = "تغيير حالة الحجز للعميل";
                        }
                    } catch (\Exception $e) {
                        Log::error("AdminBookingController: Failed to queue status update to CUSTOMER - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                    }
                }

                $allAdminUsers = User::where('is_admin', true)->where('id', '!=', Auth::id())->get();
                Log::info("AdminBookingController: Found " . $allAdminUsers->count() . " other admins for general status update notifications.");
                foreach ($allAdminUsers as $admin) {
                    Log::info("AdminBookingController: Processing other status update notifications for ADMIN: {$admin->email} Booking ID: {$booking->id} (NewStatus:{$newStatus})");
                    try {
                        if ($shouldSendBookingConfirmed) {
                             Log::info("AdminBookingController: Attempting to queue BookingConfirmedNotification for ADMIN {$admin->id}");
                            $admin->notify(new BookingConfirmedNotification($booking));
                             $notificationsSentSummary[] = "تأكيد الحجز للمدير {$admin->email}";
                        } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                             Log::info("AdminBookingController: Attempting to queue BookingCancelledNotification for ADMIN {$admin->id}");
                            $admin->notify(new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason));
                            $notificationsSentSummary[] = "إلغاء الحجز للمدير {$admin->email}";
                        } elseif ($newStatus !== Booking::STATUS_CONFIRMED) {
                             Log::info("AdminBookingController: Attempting to queue BookingStatusChangedNotification for ADMIN {$admin->id}");
                            $admin->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus));
                            $notificationsSentSummary[] = "تغيير حالة الحجز للمدير {$admin->email}";
                        }
                    } catch (\Exception $e) {
                        Log::error("AdminBookingController: Failed to queue status update to ADMIN: {$admin->email} - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
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
        
        if (!empty($notificationsSentSummary)) {
            if (str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز') && !$bookingStatusActuallyChanged && !$paymentRecordedInThisAction ) {
                // No actual change to booking or payment, but notifications might have been attempted (unlikely with current logic)
                // If $successMessage is still the default "no change" message, and we did try to send notifications
                // it's better to append to a more neutral message or just show the notification summary.
                 $successMessage = "ملخص الإشعارات (محاولة إرسال): " . implode('، ', $notificationsSentSummary) . ".";
            } elseif(str_starts_with($successMessage, 'لم يتم تغيير حالة الحجز')) {
                 // This means no change to booking status, but payment might have been recorded
                 // The successMessage for payment recording should take precedence.
                 $successMessage .= " ملخص الإشعارات (محاولة إرسال): " . implode('، ', $notificationsSentSummary) . ".";
            } else {
                 $successMessage .= " ملخص الإشعارات (محاولة إرسال): " . implode('، ', $notificationsSentSummary) . ".";
            }
        }

        if ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' && empty($notificationsSentSummary)) {
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
