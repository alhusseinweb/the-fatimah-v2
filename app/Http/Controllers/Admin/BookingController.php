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
use App\Notifications\BookingCancelledNotification; // <-- تم التأكد من استيراده
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $statuses = Booking::getStatusesWithOptions(); // <-- *** استخدام الدالة الجديدة ***
        $query = Booking::with(['user:id,name,mobile_number,email', 'service:id,name_ar'])->latest();

        if ($request->filled('status') && array_key_exists($request->status, $statuses)) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15)->withQueryString();
        return view('admin.bookings.index', compact('bookings', 'statuses'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['user', 'service', 'discountCode', 'invoice.payments']);
        $statuses = Booking::getStatusesWithOptions(); // <-- *** استخدام الدالة الجديدة ***
        $invoiceStatuses = Invoice::statuses();
        $paymentConfirmationOptions = [
            'deposit' => 'تأكيد استلام العربون فقط',
            'full' => 'تأكيد استلام المبلغ الكامل/المتبقي',
        ];
        return view('admin.bookings.show', compact('booking', 'statuses', 'invoiceStatuses', 'paymentConfirmationOptions'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $definedBookingStatuses = Booking::getStatusesWithOptions(); // <-- *** استخدام الدالة الجديدة ***
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;

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

        $cancellationStatusesRequiringReason = Booking::getCancellationStatusesRequiringReason();
        $newStatusFromRequest = $request->input('status');

        if (in_array($newStatusFromRequest, $cancellationStatusesRequiringReason)) {
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

            $bookingStatusActuallyChanged = false; // لتتبع ما إذا تغيرت حالة الحجز أو سبب الإلغاء
            $invoice = $booking->loadMissing('invoice')->invoice;
            $paymentRecordedInThisAction = false;
            $amountPaidThisTransaction = 0;
            $currencyOfThisTransaction = $invoice ? ($invoice->currency ?: 'SAR') : 'SAR';

            if ($newStatus !== $oldStatus || ($cancellationReason && $booking->cancellation_reason !== $cancellationReason) || (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason !== $booking->cancellation_reason ) ) {
                $booking->status = $newStatus;
                if (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason) {
                    $booking->cancellation_reason = $cancellationReason;
                } elseif (!in_array($newStatus, $cancellationStatusesRequiringReason)) {
                    // مسح السبب إذا لم تعد الحالة حالة إلغاء تتطلب سببًا
                    $booking->cancellation_reason = null;
                }
                // إذا كانت الحالة حالة إلغاء ولم يتم إرسال سبب (مع أنه مطلوب بالتحقق)،
                // قد لا يتم تحديثه هنا، التحقق يجب أن يمنع ذلك.
                // إذا كان $cancellationReason هو null وتم إرسال حالة إلغاء، سيتم ترك السبب القديم إذا لم يتم مسحه أعلاه.
                // لذا، من الأفضل التأكد من مسحه إذا لم تعد الحالة حالة إلغاء تتطلب سببًا.

                $booking->save();
                $bookingStatusActuallyChanged = true;
                $successMessage = 'تم تحديث حالة الحجز بنجاح.';
            }
            
            // ... (منطق الدفع والفاتورة كما هو في ملفك الأصلي، مع التأكد من صحة المتغيرات) ...
            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
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
                    $successMessage .= ($bookingStatusActuallyChanged || $oldStatus !== $newStatus ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و')) . 'تم تسجيل دفعة العربون.';
                    $paymentRecordedInThisAction = true;

                } elseif ($paymentConfirmationType === 'full') {
                    $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                    $newInvoiceStatus = Invoice::STATUS_PAID;
                    $successMessage .= ($bookingStatusActuallyChanged || $oldStatus !== $newStatus ? ' و' : ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).' ? '' : ' و')) . 'تم تسجيل المبلغ المتبقي/الكامل.';
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
                         if ($oldStatus === $newStatus && !$bookingStatusActuallyChanged && ($successMessage === 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).')) {
                             $successMessage = 'لم يتم إجراء تغيير (الفاتورة مدفوعة والحالة لم تتغير).';
                         }
                    }
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed but no invoice found to record payment.");
                 if($bookingStatusActuallyChanged || $oldStatus !== $newStatus) $successMessage .= ' (تنبيه: لا توجد فاتورة لتسجيل الدفعة).';
                 else $successMessage = 'لم يتم إجراء تغيير (الحالة مؤكدة ولا توجد فاتورة).';
            }


            if ($bookingStatusActuallyChanged) {
                $customer = $booking->user;
                $actor = Auth::user();

                if ($customer) {
                    Log::info("AdminBookingController: Sending booking status update to CUSTOMER for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                    try {
                        if ($newStatus === Booking::STATUS_CONFIRMED) {
                            $customer->notify(new BookingConfirmedNotification($booking, $customer));
                        } elseif (in_array($newStatus, Booking::getCancellationStatusesRequiringReason())) {
                            $customer->notify(new BookingCancelledNotification($booking, $customer, $actor, $booking->cancellation_reason)); // *** استخدام $booking->cancellation_reason المحفوظ ***
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
                            } elseif (in_array($newStatus, Booking::getCancellationStatusesRequiringReason())) {
                                $admin->notify(new BookingCancelledNotification($booking, $admin, $actor, $booking->cancellation_reason)); // *** استخدام $booking->cancellation_reason المحفوظ ***
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

    public function destroy(Booking $booking) // <-- تم تعديل الـ Type Hint إلى Booking
    {
        try {
            // يمكنك إضافة أي منطق إضافي هنا قبل الحذف إذا لزم الأمر
            // مثل التحقق من الأذونات أو إذا كان يمكن حذف الحجز بناءً على حالته
            $bookingId = $booking->id;
            $booking->delete(); // افترض أن لديك soft deletes أو أي منطق آخر
            Log::info("Booking ID {$bookingId} deleted by admin ID: " . Auth::id());
            return redirect()->route('admin.bookings.index')
                             ->with('success', 'تم حذف الحجز بنجاح.');
        } catch (\Exception $e) {
            Log::error("Error deleting booking: {$booking->id} - {$e->getMessage()}");
            return redirect()->route('admin.bookings.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الحجز.');
        }
    }
}
