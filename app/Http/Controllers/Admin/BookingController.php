<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse; // إضافة نوع الإرجاع
use Illuminate\View\View; // إضافة نوع الإرجاع
use Illuminate\Validation\Rule;
use App\Notifications\BookingConfirmedNotification; // تأكد من وجود هذا الإشعار
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\PaymentSuccessNotification; // تأكد من وجود هذا الإشعار
use App\Notifications\BookingCancelledNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException; // لاستخدامها في catch
use Illuminate\Support\Facades\Validator; // لاستخدام Validator بشكل صريح
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Display a listing of the bookings.
     */
    public function index(Request $request): View
    {
        $statuses = Booking::getStatusesWithOptions();
        $query = Booking::with(['user:id,name,mobile_number,email', 'service:id,name_ar'])->latest('booking_datetime'); // الترتيب بالأحدث حسب تاريخ الحجز

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
    $allBookingStatuses = Booking::getStatusesWithOptions(); // استخدام اسم مميز

    // تحديد الحالات التي لا نريد عرضها في القائمة المنسدلة
    $statusesToRemove = [
        Booking::STATUS_NO_SHOW,
        Booking::STATUS_RESCHEDULED_BY_ADMIN,
        Booking::STATUS_RESCHEDULED_BY_USER,
    ];

    // تصفية الحالات وتخزينها في المتغير 'statuses' الذي سيتم تمريره للـ view
    $statuses = array_filter($allBookingStatuses, function ($key) use ($statusesToRemove) {
        return !in_array($key, $statusesToRemove, true);
    }, ARRAY_FILTER_USE_KEY);
    
    // خيارات تأكيد الدفع (تستخدم في JavaScript لإظهار/إخفاء الحقول)
    $paymentConfirmationOptions = [
        'deposit' => 'تأكيد استلام العربون فقط',
        'full'    => 'تأكيد استلام المبلغ الكامل/المتبقي',
        // 'none' => 'لا تغيير على حالة الدفع (تأكيد الحجز فقط)' // يمكن إضافته إذا أردت تأكيد الحجز بدون تسجيل دفعة هنا
    ];

    // جلب ترجمات حالات الفاتورة للعرض
    if (method_exists(Invoice::class, 'getStatusesWithOptions')) {
        $invoiceStatusTranslations = Invoice::getStatusesWithOptions();
    } elseif (method_exists(Invoice::class, 'statuses')) { // كـ fallback
        $invoiceStatusTranslations = Invoice::statuses();
    } else {
        $invoiceStatusTranslations = []; 
    }

    // هذا هو السطر 118 المشار إليه في الخطأ أو قريب منه
    return view('admin.bookings.show', compact(
        'booking', 
        'statuses', // المتغير $statuses الآن مُعرف ويحمل القيم المفلترة
        'invoiceStatusTranslations', 
        'paymentConfirmationOptions'
    ));
}

    /**
     * Update the status of the specified booking.
     */
    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $definedBookingStatuses = Booking::getStatusesWithOptions(); // الحالات المتاحة بشكل عام
        $confirmedStatusValue = Booking::STATUS_CONFIRMED;
        $cancellationStatusesRequiringReason = Booking::getCancellationStatusesRequiringReason(); // الحالات التي تتطلب سبب إلغاء
        $allCancellationStatusValues = [ // جميع حالات الإلغاء لتحديث الفاتورة
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_CANCELLED_BY_USER,
        ];

        // قواعد التحقق الأساسية
        $rules = [
            'status' => ['required', Rule::in(array_keys($definedBookingStatuses))],
            'payment_confirmation_type' => [
                Rule::requiredIf(fn () => $request->input('status') === $confirmedStatusValue),
                'nullable', // اسمح بأن يكون فارغًا إذا لم يكن مطلوبًا
                Rule::in(['full', 'deposit', 'none']) // أضفت 'none' كخيار محتمل
            ],
            'cancellation_reason' => [
                Rule::requiredIf(fn() => in_array($request->input('status'), $cancellationStatusesRequiringReason)),
                'nullable', // اسمح بأن يكون فارغًا إذا لم يكن مطلوبًا
                'string', 'min:5', 'max:1000'
            ],
            'deposit_amount' => [ // التحقق من مبلغ العربون
                Rule::requiredIf(function () use ($request, $confirmedStatusValue) {
                    return $request->input('status') === $confirmedStatusValue &&
                           $request->input('payment_confirmation_type') === 'deposit';
                }),
                'nullable',
                'numeric',
                'min:0.01' // يجب أن يكون أكبر من صفر
            ],
        ];

        $messages = [
            'status.required' => 'الرجاء اختيار الحالة الجديدة للحجز.',
            'status.in' => 'الحالة الجديدة المحددة غير صالحة.',
            'payment_confirmation_type.required' => 'عند تأكيد الحجز، يرجى تحديد كيفية التعامل مع الدفعة.',
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
                             ->withErrors($validator, 'updateStatus') // استخدام اسم محدد للـ error bag
                             ->withInput();
        }

        $validated = $validator->validated(); // الحصول على البيانات المتحقق منها فقط

        $newStatus = $validated['status'];
        $oldStatus = $booking->status;
        $paymentConfirmationType = $validated['payment_confirmation_type'] ?? null;
        $depositAmountFromRequest = isset($validated['deposit_amount']) ? (float) $validated['deposit_amount'] : null;
        $cancellationReason = $validated['cancellation_reason'] ?? null;

        $successMessages = []; // لتجميع رسائل النجاح
        $notificationsToSend = []; // لتجميع الإشعارات

        try {
            DB::beginTransaction();

            $bookingStatusActuallyChanged = false;
            $invoice = $booking->loadMissing('invoice.payments')->invoice; // تأكد من تحميل الدفعات مع الفاتورة

            // 1. تحديث حالة الحجز وسبب الإلغاء (إذا تغيرت)
            if ($newStatus !== $oldStatus || ($cancellationReason && $booking->cancellation_reason !== $cancellationReason) || (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason !== $booking->cancellation_reason )) {
                $booking->status = $newStatus;
                if (in_array($newStatus, $cancellationStatusesRequiringReason) && $cancellationReason) {
                    $booking->cancellation_reason = $cancellationReason;
                } elseif (!in_array($newStatus, $cancellationStatusesRequiringReason)) { // مسح السبب إذا لم تعد الحالة إلغاء يتطلب سبب
                    $booking->cancellation_reason = null;
                }
                $booking->save();
                $bookingStatusActuallyChanged = true;
                $successMessages[] = 'تم تحديث حالة الحجز بنجاح.';
                Log::info("Booking ID {$booking->id} status changed from '{$oldStatus}' to '{$newStatus}' by Admin ID: " . Auth::id());
            }

            // 2. تحديث حالة الفاتورة إذا تم إلغاء الحجز
            if ($invoice && in_array($newStatus, $allCancellationStatusValues)) {
                if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])) {
                    $invoice->status = Invoice::STATUS_CANCELLED;
                    $invoice->save();
                    Log::info("Invoice ID {$invoice->id} status updated to CANCELLED due to booking cancellation.", ['booking_id' => $booking->id]);
                    $successMessages[] = 'تم تحديث حالة الفاتورة المرتبطة إلى "ملغاة".';
                }
            }

            // 3. التعامل مع تأكيد الدفع إذا تم تأكيد الحجز
            $paymentRecordedThisAction = false;
            if ($newStatus === $confirmedStatusValue && $paymentConfirmationType && $invoice) {
                $newInvoiceStatus = $invoice->status; // الحالة الافتراضية للفاتورة هي حالتها الحالية
                $amountPaidThisTransaction = 0.0;
                $paymentGatewayType = 'manual_admin'; // نوع بوابة الدفع الافتراضي

                if ($paymentConfirmationType === 'deposit' && $depositAmountFromRequest !== null && $depositAmountFromRequest > 0) {
                    // التحقق من أن مبلغ العربون لا يتجاوز المبلغ المتبقي أو الإجمالي
                    $maxAllowedForDeposit = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                    if ($depositAmountFromRequest >= $maxAllowedForDeposit && $maxAllowedForDeposit > 0.009) {
                        DB::rollBack();
                        return back()->withErrors(['deposit_amount' => "مبلغ العربون (${depositAmountFromRequest}) لا يمكن أن يكون مساوياً أو أكبر من المبلغ المطلوب للفاتورة (${maxAllowedForDeposit}). اختر 'تأكيد استلام المبلغ الكامل' بدلاً من ذلك."], 'updateStatus')->withInput();
                    }
                    $amountPaidThisTransaction = $depositAmountFromRequest;
                    $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                    $paymentGatewayType = 'manual_admin_deposit';
                    $successMessages[] = 'تم تسجيل دفعة العربون بنجاح.';

                } elseif ($paymentConfirmationType === 'full') {
                    // الدفع الكامل يعني دفع المبلغ المتبقي، أو المبلغ الكلي إذا لم يكن هناك شيء مدفوع
                    $amountPaidThisTransaction = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : $invoice->amount;
                    if ($amountPaidThisTransaction > 0.009 || $invoice->status !== Invoice::STATUS_PAID) { // سجل دفعة فقط إذا كان هناك مبلغ للدفع أو الفاتورة ليست مدفوعة بالفعل
                        $newInvoiceStatus = Invoice::STATUS_PAID;
                        $paymentGatewayType = 'manual_admin_full';
                        $successMessages[] = 'تم تسجيل المبلغ الكامل/المتبقي بنجاح.';
                    } else if ($invoice->status === Invoice::STATUS_PAID) {
                        Log::info("Invoice {$invoice->id} already PAID. No payment recorded for 'full' confirmation.");
                        // لا تقم بتغيير رسالة النجاح إذا كانت الفاتورة مدفوعة بالفعل ولم يتغير شيء آخر
                    }
                }
                // elseif ($paymentConfirmationType === 'none') { // إذا أضفت هذا الخيار
                //     Log::info("Booking {$booking->id} confirmed without changing payment status for invoice {$invoice->id}.");
                // }


                // إنشاء سجل دفع فقط إذا كان هناك مبلغ فعلي تم "دفعه" في هذا الإجراء
                if ($amountPaidThisTransaction > 0.009) {
                    $payment = Payment::create([
                        'invoice_id' => $invoice->id,
                        'amount' => $amountPaidThisTransaction,
                        'currency' => $invoice->currency ?: 'SAR',
                        'status' => Payment::STATUS_COMPLETED,
                        'payment_gateway' => $paymentGatewayType,
                        'payment_details' => json_encode(['confirmed_by_admin_id' => Auth::id(), 'admin_name' => Auth::user()?->name, 'confirmed_at' => now()->toDateTimeString()])
                    ]);
                    $paymentRecordedThisAction = true;
                    Log::info("Admin manual payment recorded for invoice {$invoice->id}.", ['payment_id' => $payment->id, 'amount' => $amountPaidThisTransaction]);
                }

                // تحديث حالة الفاتورة إذا تغيرت أو إذا تم تسجيل دفعة جديدة
                if ($invoice->status !== $newInvoiceStatus || ($paymentRecordedThisAction && is_null($invoice->paid_at))) {
                    $invoice->status = $newInvoiceStatus;
                    if (($newInvoiceStatus === Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) && is_null($invoice->paid_at) && $amountPaidThisTransaction > 0.009) {
                        $invoice->paid_at = now();
                    }
                    $invoice->save();
                    Log::info("Invoice {$invoice->id} status updated to '{$newInvoiceStatus}' by admin action.", ['booking_id' => $booking->id]);
                }
            } elseif ($newStatus === $confirmedStatusValue && !$invoice) {
                Log::warning("Booking ID {$booking->id} confirmed by admin, but no associated invoice found to record payment.");
                $successMessages[] = 'تنبيه: تم تأكيد الحجز ولكن لا توجد فاتورة مرتبطة لتسجيل دفعة.';
            }
            
            DB::commit();

            // إرسال الإشعارات بعد نجاح كل العمليات
            $customer = $booking->user;
            $actor = Auth::user(); // المدير الذي قام بالتغيير

            if ($bookingStatusActuallyChanged && $customer) {
                if ($newStatus === Booking::STATUS_CONFIRMED && !$paymentRecordedThisAction) { // إشعار تأكيد الحجز إذا لم يتم إرسال إشعار دفع
                    $notificationsToSend[] = new BookingConfirmedNotification($booking);
                } elseif (in_array($newStatus, $allCancellationStatusValues)) {
                    $notificationsToSend[] = new BookingCancelledNotification($booking, $actor, $booking->cancellation_reason);
                } elseif ($newStatus !== Booking::STATUS_CONFIRMED) { // إشعار عام بتغيير الحالة إذا لم يكن إلغاء أو تأكيد بدون دفع
                    $notificationsToSend[] = new BookingStatusChangedNotification($booking, $oldStatus, $newStatus);
                }
            }
            if ($paymentRecordedThisAction && $customer && $invoice && $amountPaidThisTransaction > 0.009) {
                $notificationsToSend[] = new PaymentSuccessNotification($invoice, $amountPaidThisTransaction, $invoice->currency ?: 'SAR');
            }

            foreach ($notificationsToSend as $notification) {
                try {
                    $customer->notify($notification);
                    Log::info("Notification " . class_basename($notification) . " queued for customer {$customer->id} for booking {$booking->id}.");
                } catch (\Exception $e) {
                    Log::error("Failed to queue notification " . class_basename($notification) . " to customer for booking {$booking->id}.", ['error' => $e->getMessage()]);
                }
            }
            // يمكنك إضافة إشعارات للمدراء الآخرين هنا إذا أردت


            if (empty($successMessages)) {
                $successMessages[] = 'لم يتم إجراء أي تغييرات على حالة الحجز أو الدفع.';
            }
            return redirect()->route('admin.bookings.show', $booking->id)->with('update_status_success', implode(' ', $successMessages));

        } catch (ValidationException $e) { // يجب أن يكون هذا قبل Exception العامة
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

            // يمكنك إضافة منطق أكثر تفصيلاً هنا، مثلاً هل يمكن حذف حجز مؤكد ومدفوع؟
            // حاليًا، سيتم حذف الحجز وإلغاء الفاتورة إذا لم تكن مدفوعة بالكامل أو مسترجعة

            if ($invoice && !in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED, Invoice::STATUS_CANCELLED])) {
                $invoice->status = Invoice::STATUS_CANCELLED;
                $invoice->save();
                Log::info("Invoice ID {$invoice->id} status set to CANCELLED due to booking deletion.", ['booking_id' => $bookingId]);
            }
            
            // حذف الدفعات المرتبطة بالحجز (أو الفاتورة) إذا أردت ذلك
            // if($invoice) {
            //     $invoice->payments()->delete();
            // }

            $booking->delete(); // سيؤدي أيضًا إلى حذف أي علاقات معرفة مع onDelete('cascade')
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
