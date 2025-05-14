<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User; // <-- !!! تأكد من استيراد موديل User !!!
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Notifications\BookingConfirmedNotification; // استيراد إشعار تأكيد الحجز
use App\Notifications\BookingStatusChangedNotification; // استيراد إشعار تغيير الحالة
use App\Notifications\PaymentSuccessNotification; // استيراد إشعار نجاح الدفع
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str; // لاستخدام Str::limit إذا لزم الأمر

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings.
     */
    public function index(Request $request)
    {
        $statuses = Booking::statuses();
        // جلب بيانات المستخدم والخدمة لتجنب N+1 في العرض
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
        $statuses = Booking::statuses();
        $invoiceStatuses = Invoice::statuses();
        $paymentConfirmationOptions = [
            'full' => 'تأكيد استلام المبلغ الكامل',
            'deposit' => 'تأكيد استلام العربون فقط'
        ];
        return view('admin.bookings.show', compact('booking', 'statuses', 'invoiceStatuses', 'paymentConfirmationOptions'));
    }

    /**
     * Update the status of the specified booking.
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $definedBookingStatuses = Booking::statuses();
        $confirmedStatusValue = Booking::STATUS_CONFIRMED; // افترض أن هذا ثابت في موديل Booking

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

        $messages = [
            'payment_confirmation_type.required' => 'عند تأكيد الحجز، يرجى تحديد كيفية استلام الدفعة.',
            'deposit_amount.required' => 'عند اختيار تأكيد استلام العربون، يجب إدخال مبلغ العربون.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->route('admin.bookings.show', $booking)
                            ->withErrors($validator, 'updateStatus')
                            ->withInput();
        }

        $validated = $validator->validated();
        $newStatus = $validated['status'];
        $oldStatus = $booking->status; // الحالة القديمة للحجز
        $paymentConfirmationType = $validated['payment_confirmation_type'] ?? null;
        $depositAmountFromRequest = isset($validated['deposit_amount']) ? (float) $validated['deposit_amount'] : null;
        $successMessage = 'لم يتم تغيير حالة الحجز (الحالة المحددة هي نفسها الحالية).';

        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false; // لتتبع ما إذا تغيرت حالة الحجز فعلاً
            $invoice = $booking->loadMissing('invoice')->invoice;
            $paymentRecordedInThisAction = false; // لتتبع ما إذا تم تسجيل دفعة في هذه العملية
            $amountPaidThisTransaction = 0; // المبلغ الذي تم دفعه في هذه العملية
            $currencyOfThisTransaction = $invoice ? ($invoice->currency ?: 'SAR') : 'SAR'; // عملة الدفعة


            // 1. تحديث حالة الحجز إذا تغيرت
            if ($newStatus !== $oldStatus) {
                $booking->status = $newStatus;
                $booking->save();
                $bookingStatusActuallyChanged = true;
                $successMessage = 'تم تحديث حالة الحجز بنجاح.';
            }

            // 2. التعامل مع الفاتورة والدفعة إذا تم تأكيد الحجز يدوياً من قبل المدير
            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
                $newInvoiceStatus = null;
                $paymentGateway = 'manual_admin'; // بوابة الدفع اليدوية الافتراضية

                if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest > 0) {
                    $maxAllowedDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                    if ($depositAmountFromRequest >= $maxAllowedDeposit && $maxAllowedDeposit > 0) {
                        DB::rollBack();
                        $validator->errors()->add('deposit_amount', "مبلغ العربون (${depositAmountFromRequest}) لا يمكن أن يتجاوز المبلغ المتبقي (${maxAllowedDeposit}).");
                        return redirect()->route('admin.bookings.show', $booking)->withErrors($validator, 'updateStatus')->withInput();
                    }
                    $amountPaidThisTransaction = $depositAmountFromRequest;
                    $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                    $paymentGateway = 'manual_admin_deposit'; // لتمييز دفعة العربون
                    $successMessage .= ' وتم تسجيل دفعة العربون.';
                    $paymentRecordedInThisAction = true;
                } elseif ($paymentConfirmationType === 'full') {
                    $amountPaidThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                    $newInvoiceStatus = Invoice::STATUS_PAID;
                    $successMessage .= ' وتم تسجيل المبلغ المتبقي/الكامل.';
                    $paymentRecordedInThisAction = true;
                }

                // تسجيل الدفعة وتحديث الفاتورة
                if ($paymentRecordedInThisAction && $amountPaidThisTransaction > 0) {
                    if ($invoice->status !== Invoice::STATUS_PAID || ($invoice->status === Invoice::STATUS_PAID && $paymentConfirmationType === 'deposit' && $invoice->remaining_amount > 0) ) {
                        $createdPayment = $invoice->payments()->create([
                            'amount' => $amountPaidThisTransaction,
                            'currency' => $currencyOfThisTransaction,
                            'status' => Payment::STATUS_COMPLETED, // افترض أن لديك ثابت لحالة الدفع
                            'payment_gateway' => $paymentGateway,
                            'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()->name]) // تحويل لـ JSON
                        ]);
                        Log::info("Admin manual payment recorded.", [
                            'invoice_id' => $invoice->id, 'payment_id' => $createdPayment->id, 'amount' => $amountPaidThisTransaction
                        ]);

                        $invoice->status = $newInvoiceStatus;
                        if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                            $invoice->paid_at = Carbon::now();
                        }
                        $invoice->save();
                        Log::info("Invoice status updated to {$newInvoiceStatus} after admin manual payment.", ['invoice_id' => $invoice->id]);

                        // إرسال إشعار نجاح الدفع هنا بعد تسجيل الدفعة
                        $customerForPayment = $booking->user;
                        if ($customerForPayment) {
                            Log::info("AdminBookingController: Sending PaymentSuccessNotification to CUSTOMER for Invoice ID: {$invoice->id}");
                            $customerForPayment->notify(new PaymentSuccessNotification($invoice, $customerForPayment, (float)$amountPaidThisTransaction, $currencyOfThisTransaction));
                        }
                        $adminUsersForPayment = User::where('is_admin', true)->get();
                        foreach ($adminUsersForPayment as $adminUser) {
                            Log::info("AdminBookingController: Sending PaymentSuccessNotification to ADMIN: {$adminUser->email} for Invoice ID: {$invoice->id}");
                            $adminUser->notify(new PaymentSuccessNotification($invoice, $adminUser, (float)$amountPaidThisTransaction, $currencyOfThisTransaction));
                        }
                    } else {
                        Log::info("Invoice {$invoice->id} was already paid. Booking status might have changed.", ['invoice_id' => $invoice->id]);
                         if ($bookingStatusActuallyChanged) { // إذا تغيرت حالة الحجز فقط والفاتورة مدفوعة
                            // لا تغير رسالة النجاح إذا كانت حالة الحجز هي ما تغير فقط
                        } elseif ($oldStatus === $newStatus) { // إذا لم يتغير شيء
                             $successMessage = 'لم يتم إجراء تغيير (الحالة والفاتورة كما هي).';
                        }
                    }
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed but no invoice found to record payment.");
                if($bookingStatusActuallyChanged) $successMessage .= ' (تنبيه: لا توجد فاتورة لتسجيل الدفعة).';
            }

            // --- إرسال إشعارات تغيير حالة الحجز (إذا تغيرت الحالة فعلاً) ---
            if ($bookingStatusActuallyChanged) {
                $customer = $booking->user; // العميل صاحب الحجز
                $actor = Auth::user(); // المدير الذي قام بالتغيير

                // 1. إرسال الإشعار للعميل
                if ($customer) {
                    Log::info("AdminBookingController: Sending booking status update to CUSTOMER for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                    try {
                        if ($newStatus === Booking::STATUS_CONFIRMED) {
                            $customer->notify(new BookingConfirmedNotification($booking, $customer));
                        } else if ($newStatus === Booking::STATUS_CANCELLED_BY_ADMIN || $newStatus === Booking::STATUS_CANCELLED_BY_USER) {
                            // افترض أن لديك سبب إلغاء أو يمكنك تمرير null
                            $cancellationReason = $request->input('cancellation_reason_admin'); // إذا كان هناك حقل لسبب الإلغاء من المدير
                            $customer->notify(new \App\Notifications\BookingCancelledNotification($booking, $customer, $actor, $cancellationReason));
                        }
                        else {
                            $customer->notify(new BookingStatusChangedNotification($booking, $oldStatus, $newStatus, $customer));
                        }
                    } catch (\Exception $e) {
                        Log::error("AdminBookingController: Failed to send status update to CUSTOMER - Booking ID: {$booking->id}", ['error' => $e->getMessage()]);
                    }
                }

                // 2. إرسال الإشعار لجميع المديرين
                $allAdminUsers = User::where('is_admin', true)->get();
                if ($allAdminUsers->isNotEmpty()) {
                    foreach ($allAdminUsers as $admin) {
                        // تجنب إرسال إشعار للمدير الذي قام بالفعل بالإجراء إذا أردت
                        // if ($actor && $actor->id === $admin->id) continue;

                        Log::info("AdminBookingController: Sending booking status update to ADMIN: {$admin->email} for Booking ID: {$booking->id} (Old:{$oldStatus} -> New:{$newStatus})");
                        try {
                            if ($newStatus === Booking::STATUS_CONFIRMED) {
                                $admin->notify(new BookingConfirmedNotification($booking, $admin));
                            } else if ($newStatus === Booking::STATUS_CANCELLED_BY_ADMIN || $newStatus === Booking::STATUS_CANCELLED_BY_USER) {
                                $cancellationReason = $request->input('cancellation_reason_admin');
                                $admin->notify(new \App\Notifications\BookingCancelledNotification($booking, $admin, $actor, $cancellationReason));
                            }
                             else {
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
            return redirect()->route('admin.bookings.show', $booking)->withErrors($e->validator, 'updateStatus')->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("AdminBookingController: Failed to update booking status or record payment.", [
                'booking_id' => $booking->id, 'error' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return redirect()->route('admin.bookings.show', $booking)->with('error', 'حدث خطأ غير متوقع. يرجى مراجعة السجلات.');
        }

        return redirect()->route('admin.bookings.show', $booking)->with('success', $successMessage);
    }
}