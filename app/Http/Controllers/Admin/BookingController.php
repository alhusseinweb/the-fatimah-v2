<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
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
    public function index(Request $request): View
    {
        $statuses = Booking::getStatusesWithOptions();
        $query = Booking::with(['user:id,name,mobile_number,email', 'service:id,name_ar'])->latest('booking_datetime');

        if ($request->filled('status') && array_key_exists($request->status, $statuses)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search_term')) {
            $searchTerm = $request->search_term;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'like', "%{$searchTerm}%")
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%")
                                ->orWhere('mobile_number', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('service', function ($serviceQuery) use ($searchTerm) {
                      $serviceQuery->where('name_ar', 'like', "%{$searchTerm}%")
                                   ->orWhere('name_en', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('invoice', function ($invoiceQuery) use ($searchTerm) {
                        $invoiceQuery->where('invoice_number', 'like', "%{$searchTerm}%");
                  });
            });
        }
        
        if ($request->filled('date_from')) {
            try {
                $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                $query->where('booking_datetime', '>=', $dateFrom);
            } catch (\Exception $e) {
                Log::warning('Admin Booking Index: Invalid date_from format.', ['date_from' => $request->date_from]);
            }
        }
        if ($request->filled('date_to')) {
             try {
                $dateTo = Carbon::parse($request->date_to)->endOfDay();
                $query->where('booking_datetime', '<=', $dateTo);
            } catch (\Exception $e) {
                Log::warning('Admin Booking Index: Invalid date_to format.', ['date_to' => $request->date_to]);
            }
        }

        $bookings = $query->paginate(15)->withQueryString();
        return view('admin.bookings.index', compact('bookings', 'statuses'));
    }

    /**
     * Display the specified booking details.
     */
    public function show(Booking $booking): View
    {
        $booking->load(['user', 'service', 'discountCode', 'invoice.payments']);

        // جلب جميع الحالات الأصلية
        $allBookingStatusesOriginal = Booking::getStatusesWithOptions(); // استخدام اسم مميز لتجنب الالتباس

        // تحديد الحالات التي لا نريد عرضها في القائمة المنسدلة
        $statusesToRemove = [
            Booking::STATUS_NO_SHOW,
            Booking::STATUS_RESCHEDULED_BY_ADMIN,
            Booking::STATUS_RESCHEDULED_BY_USER,
        ];

        // تصفية الحالات وتخزينها في المتغير 'statuses' الذي سيتم تمريره للـ view
        $statuses = array_filter($allBookingStatusesOriginal, function ($key) use ($statusesToRemove) {
            return !in_array($key, $statusesToRemove, true);
        }, ARRAY_FILTER_USE_KEY);
        
        $paymentConfirmationOptions = [];
        $invoice = $booking->invoice;

        // عرض خيارات تأكيد الدفع فقط إذا كانت الفاتورة ليست مدفوعة بالكامل
        // وإذا كان الحجز ليس في حالة مكتمل أو ملغي بالفعل
        if ($invoice && $invoice->status !== Invoice::STATUS_PAID && 
            !in_array($booking->status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED_BY_ADMIN, Booking::STATUS_CANCELLED_BY_USER])) {
            
            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                $paymentConfirmationOptions['full'] = 'تأكيد استلام المبلغ المتبقي';
            } 
            elseif (in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING_CONFIRMATION, Invoice::STATUS_PENDING, Invoice::STATUS_FAILED])) {
                // إذا كانت الفاتورة غير مدفوعة أو في انتظار التأكيد، يمكن تأكيد العربون أو المبلغ الكامل
                if ($booking->down_payment_amount > 0 || $invoice->payment_option === 'down_payment') { // عرض خيار العربون إذا كان متاحًا
                    $paymentConfirmationOptions['deposit'] = 'تأكيد استلام العربون فقط';
                }
                $paymentConfirmationOptions['full']    = 'تأكيد استلام المبلغ الكامل';
            }
            // $paymentConfirmationOptions['none'] = 'لا تغيير على حالة الدفع (تأكيد الحجز فقط)'; // يمكن إضافته إذا أردت
        }


        if (method_exists(Invoice::class, 'getStatusesWithOptions')) {
            $invoiceStatusTranslations = Invoice::getStatusesWithOptions();
        } elseif (method_exists(Invoice::class, 'statuses')) { 
            $invoiceStatusTranslations = Invoice::statuses();
        } else {
            $invoiceStatusTranslations = []; 
        }

        return view('admin.bookings.show', compact(
            'booking', 
            'statuses', 
            'invoiceStatusTranslations', 
            'paymentConfirmationOptions'
        ));
    }

    /**
     * Update the status of the specified booking.
     */
    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $definedBookingStatuses = Booking::getStatusesWithOptions(); 
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;
        $cancellationStatusesRequiringReason = Booking::getCancellationStatusesRequiringReason();
        $allCancellationStatusValues = [ 
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_CANCELLED_BY_USER,
        ];

        $rules = [
            'status' => ['required', Rule::in(array_keys($definedBookingStatuses))],
            'payment_confirmation_type' => [
                Rule::requiredIf(fn () => $request->input('status') === $confirmedStatusValue && $booking->invoice && $booking->invoice->status !== Invoice::STATUS_PAID), // مطلوب فقط إذا الفاتورة ليست مدفوعة بالكامل
                'nullable',
                Rule::in(['full', 'deposit', 'none']) 
            ],
            'cancellation_reason' => [
                Rule::requiredIf(fn() => in_array($request->input('status'), $cancellationStatusesRequiringReason)),
                'nullable',
                'string', 'min:5', 'max:1000'
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
            'status.required' => 'الرجاء اختيار الحالة الجديدة للحجز.',
            'status.in' => 'الحالة الجديدة المحددة غير صالحة.',
            'payment_confirmation_type.required' => 'عند تأكيد الحجز والفاتورة غير مدفوعة، يرجى تحديد كيفية التعامل مع الدفعة.',
            'payment_confirmation_type.in' => 'خيار تأكيد الدفعة المحدد غير صالح.',
            'cancellation_reason.required' => 'سبب الإلغاء مطلوب عند اختيار هذه الحالة.',
            'cancellation_reason.min' => 'سبب الإلغاء يجب أن يكون ٥ أحرف على الأقل.',
            'deposit_amount.required' => 'عند اختيار تأكيد استلام العربون، يجب إدخال مبلغ العربون.',
            'deposit_amount.numeric' => 'مبلغ العربون يجب أن يكون رقماً صحيحاً أو عشرياً.',
            'deposit_amount.min' => 'مبلغ العربون يجب أن يكون أكبر من صفر.',
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

        $successMessages = [];
        $notificationsToSend = [];

        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false;
            $invoice = $booking->loadMissing('invoice.payments')->invoice; 
            $paymentRecordedThisAction = false;
            $amountPaidThisTransaction = 0.0;

            if ($newStatus !== $oldStatus || ($cancellationReason && $booking->cancellation_reason !== $cancellationReason) || (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason !== $booking->cancellation_reason )) {
                $booking->status = $newStatus;
                if (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason) {
                    $booking->cancellation_reason = $cancellationReason;
                } elseif (!in_array($newStatus, $cancellationStatusesRequiringReason)) {
                    $booking->cancellation_reason = null;
                }
                $booking->save();
                $bookingStatusActuallyChanged = true;
                $successMessages[] = 'تم تحديث حالة الحجز بنجاح.';
                Log::info("Booking ID {$booking->id} status changed from '{$oldStatus}' to '{$newStatus}' by Admin ID: " . Auth::id());
            }

            if ($invoice && in_array($newStatus, $allCancellationStatusValues)) {
                if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                    $invoice->status = Invoice::STATUS_CANCELLED;
                    $invoice->save();
                    Log::info("Invoice ID {$invoice->id} status updated to CANCELLED due to booking cancellation.", ['booking_id' => $booking->id]);
                    $successMessages[] = 'تم تحديث حالة الفاتورة المرتبطة إلى "ملغاة".';
                }
            }

            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice && $invoice->status !== Invoice::STATUS_PAID) {
                if ($paymentConfirmationType !== 'none') {
                    $newInvoiceStatusAfterPayment = $invoice->status; 
                    $paymentGatewayType = 'manual_admin';

                    if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest !== null && $depositAmountFromRequest > 0) {
                        $maxAllowedForDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        if ($depositAmountFromRequest >= $maxAllowedForDeposit && $maxAllowedForDeposit > 0.009) {
                            DB::rollBack();
                            return back()->withErrors(['deposit_amount' => "مبلغ العربون ({$depositAmountFromRequest}) لا يمكن أن يكون مساوياً أو أكبر من المبلغ المطلوب للفاتورة ({$maxAllowedForDeposit}). اختر 'تأكيد استلام المبلغ الكامل' بدلاً من ذلك."], 'updateStatus')->withInput();
                        }
                        $amountPaidThisTransaction = $depositAmountFromRequest;
                        $paymentGatewayType = 'manual_admin_deposit';
                        $successMessages[] = 'تم تسجيل دفعة العربون بنجاح.';

                    } elseif ($paymentConfirmationType === 'full') {
                        $amountPaidThisTransaction = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : $invoice->amount;
                        if ($amountPaidThisTransaction > 0.009) { // فقط إذا كان هناك مبلغ فعلي للدفع
                             $paymentGatewayType = 'manual_admin_full';
                             $successMessages[] = 'تم تسجيل المبلغ الكامل/المتبقي بنجاح.';
                        } else {
                            $amountPaidThisTransaction = 0; // لا يوجد مبلغ للدفع فعليًا
                             Log::info("Invoice {$invoice->id} full payment confirmation, but remaining amount is zero or less. No payment record created.");
                        }
                    }

                    if ($amountPaidThisTransaction > 0.009) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'amount' => $amountPaidThisTransaction,
                            'currency' => $invoice->currency ?: 'SAR',
                            'status' => Payment::STATUS_COMPLETED,
                            'payment_gateway' => $paymentGatewayType,
                            'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name, 'confirmed_at' => now()->toDateTimeString()])
                        ]);
                        $paymentRecordedThisAction = true;
                        Log::info("Admin manual payment recorded for invoice {$invoice->id}.", ['amount' => $amountPaidThisTransaction]);
                    }

                    // إعادة حساب إجمالي المدفوعات وتحديث حالة الفاتورة
                    $currentTotalPaid = (float) $invoice->payments()->where('status', Payment::STATUS_COMPLETED)->sum('amount');
                    if ($paymentRecordedThisAction) {
                        $currentTotalPaid += $amountPaidThisTransaction; // إضافة الدفعة الحالية للحساب
                    }

                    if ($currentTotalPaid >= ((float)$invoice->amount - 0.01)) { // استخدام هامش صغير
                        $newInvoiceStatusAfterPayment = Invoice::STATUS_PAID;
                    } elseif ($currentTotalPaid > 0.009) {
                        $newInvoiceStatusAfterPayment = Invoice::STATUS_PARTIALLY_PAID;
                    } else {
                        $newInvoiceStatusAfterPayment = Invoice::STATUS_UNPAID;
                    }
                    
                    if ($invoice->status !== $newInvoiceStatusAfterPayment || ($paymentRecordedThisAction && is_null($invoice->paid_at))) {
                        $invoice->status = $newInvoiceStatusAfterPayment;
                        if (($newInvoiceStatusAfterPayment === Invoice::STATUS_PAID || $newInvoiceStatusAfterPayment === Invoice::STATUS_PARTIALLY_PAID) && is_null($invoice->paid_at) && $currentTotalPaid > 0.009) {
                            $invoice->paid_at = now();
                        }
                        $invoice->save();
                        Log::info("Invoice {$invoice->id} status updated to '{$invoice->status}' by admin action.", ['booking_id' => $booking->id]);
                    }
                } else { // payment_confirmation_type === 'none'
                     Log::info("Booking {$booking->id} confirmed (status: {$newStatus}). Payment confirmation type was 'none'. Invoice status remains '{$invoice->status}'.");
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed by admin, but no associated invoice found.");
                $successMessages[] = 'تنبيه: تم تأكيد الحجز ولكن لا توجد فاتورة مرتبطة.';
            }
            
            DB::commit();

            $customer = $booking->user;
            $actor = Auth::user();

            if ($customer) {
                if ($bookingStatusActuallyChanged) {
                    if ($newStatus === Booking::STATUS_CONFIRMED) {
                        $notificationsToSend[] = new BookingConfirmedNotification($booking);
                    } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                        $notificationsToSend[] = new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason);
                    } else { 
                        $notificationsToSend[] = new BookingStatusChangedNotification($booking, $oldStatus, $newStatus);
                    }
                }
                
                if ($paymentRecordedThisAction && $invoice && $amountPaidThisTransaction > 0.009) {
                    $alreadySentBookingConfirmed = false;
                    foreach($notificationsToSend as $notif){
                        if($notif instanceof BookingConfirmedNotification){
                            $alreadySentBookingConfirmed = true;
                            break;
                        }
                    }
                    // لا ترسل إشعار نجاح الدفع إذا تم إرسال إشعار تأكيد الحجز بالفعل
                    // إلا إذا كان منطق عملك يتطلب ذلك (مثلاً محتوى مختلف جدًا)
                    // حاليًا، إذا تم تأكيد الحجز (وتم إرسال إشعار تأكيد)، فلن نرسل إشعار نجاح دفع منفصل
                    if (!$alreadySentBookingConfirmed || $newStatus !== Booking::STATUS_CONFIRMED) {
                         $notificationsToSend[] = new PaymentSuccessNotification($invoice, $amountPaidThisTransaction, $invoice->currency ?: 'SAR');
                    }
                }

                $uniqueNotifications = [];
                $notificationClassNames = [];
                foreach($notificationsToSend as $notification) {
                    $className = get_class($notification);
                    if(!in_array($className, $notificationClassNames)){
                        $uniqueNotifications[] = $notification;
                        $notificationClassNames[] = $className;
                    }
                }
                $notificationsToSend = $uniqueNotifications;

                foreach ($notificationsToSend as $notification) {
                    try {
                        $customer->notify($notification);
                        Log::info("Notification " . class_basename($notification) . " queued for CUSTOMER {$customer->id} for booking {$booking->id}.");
                    } catch (\Exception $e) {
                        Log::error("Failed to queue notification " . class_basename($notification) . " to CUSTOMER for booking {$booking->id}.", ['error' => $e->getMessage()]);
                    }
                }

                $adminUsersForNotification = User::where('is_admin', true)->where('id', '!=', Auth::id())->get();
                foreach ($adminUsersForNotification as $adminUser) {
                    foreach ($notificationsToSend as $notification) {
                         try {
                            $adminUser->notify(clone $notification);
                            Log::info("Notification " . class_basename($notification) . " queued for ADMIN {$adminUser->id} for booking {$booking->id}.");
                        } catch (\Exception $e) {
                            Log::error("Failed to queue notification " . class_basename($notification) . " to ADMIN {$adminUser->id} for booking {$booking->id}.", ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            if (empty($successMessages) && !$bookingStatusActuallyChanged && !$paymentRecordedThisAction) {
                $successMessages[] = 'لم يتم إجراء أي تغييرات.';
            }
            return redirect()->route('admin.bookings.show', $booking->id)->with('update_status_success', implode(' ', $successMessages));

        } catch (ValidationException $e) {
            DB::rollBack();
            Log::warning("AdminBookingController@updateStatus: Validation failed.", ['booking_id' => $booking->id, 'errors' => $e->errors()]);
            return redirect()->route('admin.bookings.show', $booking->id)->withErrors($e->validator, 'updateStatus')->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("AdminBookingController@updateStatus: Error updating booking status.", [
                'booking_id' => $booking->id, 
                'error' => $e->getMessage(), 
                'trace' => Str::limit($e->getTraceAsString(), 2000)
            ]);
            return redirect()->route('admin.bookings.show', $booking->id)->with('update_status_error', 'حدث خطأ غير متوقع أثناء تحديث حالة الحجز.')->withInput();
        }
    }
    
    public function destroy(Booking $booking): RedirectResponse
    {
        try {
            DB::beginTransaction();
            $bookingId = $booking->id;
            $invoice = $booking->invoice;

            if ($invoice && !in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED, Invoice::STATUS_CANCELLED])) {
                $invoice->status = Invoice::STATUS_CANCELLED;
                $invoice->save();
                Log::info("Invoice ID {$invoice->id} status set to CANCELLED due to booking deletion.", ['booking_id' => $bookingId]);
            }
            
            $booking->delete();
            Log::info("Booking ID {$bookingId} deleted by admin ID: " . Auth::id());
            DB::commit();
            return redirect()->route('admin.bookings.index')
                             ->with('success', 'تم حذف الحجز بنجاح (وتم إلغاء الفاتورة المرتبطة إذا كانت مؤهلة).');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting booking ID {$booking->id}: {$e->getMessage()}");
            return redirect()->route('admin.bookings.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الحجز.');
        }
    }
}
