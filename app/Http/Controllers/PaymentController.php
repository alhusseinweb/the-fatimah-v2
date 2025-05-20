<?php

// المسار: app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Str;

use Tamara\Client as TamaraClient;
use Tamara\Configuration as TamaraConfiguration;
use Tamara\Notification\Authenticator as TamaraAuthenticator;
use Tamara\Model\Order\Order as TamaraOrder; // للتأكد من استخدام الثوابت
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest;
use Tamara\Exception\ForbiddenException as TamaraForbiddenException;
use Tamara\Exception\RequestException as TamaraRequestException;

class PaymentController extends Controller
{
    // Define constants for event types IF TamaraOrder constants are not accessible or suitable
    const EVENT_TYPE_ORDER_APPROVED = 'order_approved';
    const EVENT_TYPE_ORDER_DECLINED = 'order_declined';
    const EVENT_TYPE_ORDER_CANCELED = 'order_canceled'; // Tamara SDK might use 'order_cancelled'
    const EVENT_TYPE_ORDER_EXPIRED = 'order_expired';
    const EVENT_TYPE_ORDER_AUTHORISED = 'order_authorised';


    // ... (دوال handleTamaraSuccess, handleTamaraFailure, handleTamaraCancel كما هي) ...
    public function handleTamaraSuccess(Request $request, Invoice $invoice): RedirectResponse
    { /* ... كما في الردود السابقة ... */ 
        Log::info("Tamara success redirect received for Invoice ID: {$invoice->id}");
        $bookingId = $invoice->booking_id;

        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara success URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $invoice->refresh();
        $successMessage = 'تم استلام دفعتك بنجاح!';
        $sessionKey = 'previous_invoice_status_' . $invoice->id;
        $previousStatus = session($sessionKey);

        if ($invoice->status === Invoice::STATUS_PAID) {
            $successMessage = ($previousStatus === Invoice::STATUS_PARTIALLY_PAID)
                ? "تم استلام المبلغ المتبقي للفاتورة بنجاح! شكراً لثقتكم بنا."
                : 'تم استلام المبلغ كاملاً بنجاح! تم تأكيد حجزك.';
        } elseif ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $successMessage = 'تم استلام دفعة العربون بنجاح! تم تأكيد حجزك.';
        } else {
            // قد تكون الحالة لا تزال unpaid إذا كان الـ webhook لم يعمل بعد، أو فشل
            Log::info("Tamara success redirect, but final invoice status is '{$invoice->status}'. Using default success message.", ['invoice_id' => $invoice->id]);
        }

        if (session()->has($sessionKey)) {
            session()->forget($sessionKey);
            Log::debug("Cleared previous status from session for invoice: " . $invoice->id);
        }

        // بدلاً من pending، ربما توجه إلى صفحة تفاصيل الفاتورة أو الحجز
        return Redirect::route('customer.invoices.show', $invoice->id) // أو booking.pending إذا كنت تفضل
                        ->with('success', $successMessage);
    }

    public function handleTamaraFailure(Request $request, Invoice $invoice): RedirectResponse
    { /* ... كما في الردود السابقة ... */
        Log::error("Tamara failure/cancel redirect received for Invoice ID: {$invoice->id}");
        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara failure URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $originalStatus = $invoice->status;
        $customer = $invoice->booking?->user;
        $reason = "فشل الدفع عبر تمارا أو تم الإلغاء من قبل العميل.";

        if (!in_array($originalStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
            Log::info("Invoice status will be updated by webhook if payment truly failed. Current status '{$originalStatus}' for invoice: " . $invoice->id);

            if ($customer) {
                try {
                    // تأكد أن مُنشئ PaymentFailedNotification يأخذ هذه المعاملات
                    $customer->notify(new PaymentFailedNotification($invoice, $reason));
                    Log::info("PaymentFailedNotification dispatched to customer {$customer->id} via failure redirect.");
                } catch (\Exception $e) {
                    Log::error("Failed to send PaymentFailedNotification to customer {$customer->id}", ['error' => $e->getMessage()]);
                }
            } else {
                 Log::warning("Customer not found for invoice {$invoice->id}, cannot send PaymentFailedNotification to customer.");
            }

            $admins = User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new PaymentFailedNotification($invoice, $reason));
                    Log::info("PaymentFailedNotification dispatched to admin {$admin->id} via failure redirect.");
                } catch (\Exception $e) {
                    Log::error("Failed to send PaymentFailedNotification to admin {$admin->id}", ['error' => $e->getMessage()]);
                }
            }
        } else {
            Log::info("Tamara failure redirect received, but invoice status was already '{$originalStatus}'. Not changing status or sending notifications from redirect.", ['invoice_id' => $invoice->id]);
        }

        return Redirect::route('booking.pending', ['booking' => $invoice->booking_id]) // أو customer.invoices.show
                        ->with('error', 'فشلت عملية الدفع أو تم إلغاؤها. يمكنك محاولة الدفع مرة أخرى أو التواصل معنا.');
    }
    
    public function handleTamaraCancel(Request $request, Invoice $invoice): RedirectResponse
    { /* ... كما في الردود السابقة ... */ 
        Log::warning("Tamara cancel redirect received for Invoice ID: {$invoice->id}");
        return $this->handleTamaraFailure($request, $invoice);
    }


    /**
     * Handles the incoming webhook notification from Tamara.
     */
    public function handleTamaraWebhook(Request $request)
    {
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(), 'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(), 'ip_address' => $request->ip(),
            'query_parameters' => $request->query(), 'form_parameters_or_json' => $request->all(),
            'raw_content' => $request->getContent(), 'server_time' => date('Y-m-d H:i:s')
        ]);

        // --- START: المصادقة الفعلية (يجب تفعيل هذا في الإنتاج) ---
        $notificationTokenFromConfig = config('services.tamara.notification_token');
        // Log::info('TAMARA_NOTIFICATION_TOKEN from config for Webhook: ' . $notificationTokenFromConfig); // يمكنك إلغاء التعليق لهذا إذا احتجت

        if (empty($notificationTokenFromConfig)) {
            Log::error('Tamara Webhook Error: Missing config (services.tamara.notification_token). Cannot authenticate.');
            return response()->json(['status' => 'error_config_missing_token'], 403);
        }

        $eventType = null; $tamaraOrderId = null; $resolved_order_reference_id = null; $payloadData = null;
        /** @var \Tamara\Model\Notification\NotificationMessage $webhookMessage */
        $webhookMessage = null;

        // -- بدلاً من تجاوز المصادقة، قم بتفعيلها --
        try {
            $authenticator = new TamaraAuthenticator($notificationTokenFromConfig);
            $webhookMessage = $authenticator->authenticate($request);
            Log::info('Tamara webhook authenticated successfully using Authenticator.');

            $eventType = $webhookMessage->getEventType();
            $tamaraOrderId = $webhookMessage->getOrderId();
            $payloadData = $webhookMessage->getData(); // هذه قد تكون فارغة بناءً على سجلاتك

            // استخلاص order_reference_id بشكل أفضل
            $resolved_order_reference_id = $request->input('order_reference_id'); // جرب من الطلب مباشرة
            if (!$resolved_order_reference_id && isset($payloadData['order_reference_id'])) {
                $resolved_order_reference_id = $payloadData['order_reference_id'];
            }
            if (!$resolved_order_reference_id && isset($payloadData['order_number'])) {
                $resolved_order_reference_id = $payloadData['order_number'];
            }
            // إذا كان $webhookMessage يحتوي على دالة مباشرة، استخدمها (أفضل)
            if (method_exists($webhookMessage, 'getOrderReferenceId') && $webhookMessage->getOrderReferenceId()) {
                 $resolved_order_reference_id = $webhookMessage->getOrderReferenceId();
            }


            Log::debug('Extracted Webhook Data from Authenticator:', [
                'event_type' => $eventType, 'tamara_order_id' => $tamaraOrderId,
                'resolved_order_reference_id' => $resolved_order_reference_id,
                'payload_data_sample' => Str::limit(json_encode($payloadData), 200)
            ]);

            if (empty($eventType) || empty($tamaraOrderId)) {
                Log::error('Tamara Webhook Error: Missing essential data (event_type or order_id) after authentication.');
                return response()->json(['status' => 'success_auth_data_incomplete'], 200);
            }
        } catch (TamaraForbiddenException $e) {
            Log::error('Tamara Webhook Auth Failed (TamaraForbiddenException).', [
                'msg' => $e->getMessage(), 'tamara_token_from_config' => $notificationTokenFromConfig,
                'authorization_header_checked' => $request->header('Authorization') ?: 'Not Present',
                'tamara_token_query_param_available' => $request->query('tamaraToken') ?: 'Not Present'
            ]);
            return response()->json(['status' => 'error_auth', 'message' => 'Access denied.'], 403);
        } catch (TamaraRequestException $e) { // أخطاء SDK أخرى
            Log::error('Tamara Webhook SDK Error (TamaraRequestException).', ['msg' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
            return response()->json(['status' => 'error_tamara_sdk'], 400);
        } catch (Throwable $e) {
            Log::error('Tamara Webhook General Error during initial processing/authentication.', [
                'msg' => $e->getMessage(), 'class' => get_class($e),
                'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return response()->json(['status' => 'success_but_general_error_logged'], 200);
        }
        // --- END: المصادقة الفعلية ---

        try {
            // استخدم الثوابت المعرفة في الكلاس أو من TamaraOrder إذا كانت عامة
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info('Processing Tamara order_approved event.', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);

                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $payloadData, $webhookMessage, $request) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->with('booking.user', 'booking.service')
                    ->lockForUpdate() // <--- إضافة القفل هنا
                    ->first();

                    if (!$invoice) {
                        Log::warning('Webhook: Invoice not found for order_approved.', ['tamara_order_id' => $tamaraOrderId, 'attempted_order_reference_id' => $resolved_order_reference_id]);
                        return;
                    }
                    if (!$invoice->booking || !$invoice->booking->user) {
                         Log::warning('Webhook: Booking or User not found for invoice.', ['invoice_id' => $invoice->id]);
                         return;
                    }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)
                                            ->where('invoice_id', $invoice->id)
                                            ->exists();

                    $paymentActuallyCreatedInThisWebhookCall = false;
                    $amountPaidInThisTransaction = 0;
                    $tamaraCurrency = $invoice->currency ?: 'SAR';

                    if (!$existingPayment) {
                        $tamaraAmount = null;
                        /** @var \Tamara\Model\Order\Order $orderDataFromWebhook */
                        // $webhookMessage قد يكون null إذا كنت تتجاوز المصادقة وتعتمد على $requestData
                        if ($webhookMessage && $webhookMessage->getOrder() && method_exists($webhookMessage->getOrder(), 'getTotalAmount') && $webhookMessage->getOrder()->getTotalAmount()) {
                            $tamaraAmountObj = $webhookMessage->getOrder()->getTotalAmount();
                            if ($tamaraAmountObj && method_exists($tamaraAmountObj, 'getAmount')) { $tamaraAmount = $tamaraAmountObj->getAmount(); }
                            if ($tamaraAmountObj && method_exists($tamaraAmountObj, 'getCurrency')) { $tamaraCurrency = $tamaraAmountObj->getCurrency(); }
                        }
                        
                        if ($tamaraAmount === null) { // Fallback to $payloadData or $requestData
                            $sourceDataForAmount = $payloadData ?: ($request->all()['data'] ?? $request->all()); // $payloadData قد تكون فارغة
                            $tamaraAmount = $sourceDataForAmount['total_amount']['amount'] ?? ($sourceDataForAmount['order_amount']['amount'] ?? ($sourceDataForAmount['amount']['value'] ?? null)); // إضافة 'amount.value' كاحتمال
                            $tamaraCurrency = $sourceDataForAmount['total_amount']['currency'] ?? ($sourceDataForAmount['order_amount']['currency'] ?? ($sourceDataForAmount['amount']['currency'] ?? $tamaraCurrency));
                        }


                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                        } else {
                            Log::warning("Could not extract amount from Tamara webhook for order_approved. Estimating.", ['tamara_order_id' => $tamaraOrderId, 'invoice_id' => $invoice->id, 'payload_data_keys' => array_keys($payloadData ?: []) ]);
                            if($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PARTIALLY_PAID && $invoice->status !== Invoice::STATUS_PAID) {
                                $amountPaidInThisTransaction = $invoice->booking->down_payment_amount > 0 ? $invoice->booking->down_payment_amount : round($invoice->amount / 2, 0);
                            } else {
                                $amountPaidInThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                            }
                        }

                        if ($amountPaidInThisTransaction > 0.009) {
                            Payment::create([
                                'invoice_id' => $invoice->id,
                                'transaction_id' => $tamaraOrderId,
                                'amount' => $amountPaidInThisTransaction,
                                'currency' => $tamaraCurrency,
                                'status' => 'completed',
                                'payment_gateway' => 'tamara',
                                'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => $eventType, 'webhook_payload' => $payloadData ?: $request->all()]) ?: null,
                            ]);
                            Log::info("Payment record CREATED via webhook for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                            $paymentActuallyCreatedInThisWebhookCall = true;
                        } else {
                             Log::info("Skipping payment creation as amount is zero or less for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                        }
                    } else {
                        Log::warning("Duplicate payment webhook (or payment already recorded) detected by exists() check AFTER lock. Ignored for order_id.", [
                            'invoice_id' => $invoice->id,
                            'tamara_order_id' => $tamaraOrderId
                        ]);
                    }

                    // --- نفذ التحديثات والإشعارات فقط إذا تم إنشاء دفعة في هذا الاستدعاء ---
                    if ($paymentActuallyCreatedInThisWebhookCall) {
                        $customer = $invoice->booking->user;
                        $originalInvoiceStatus = $invoice->status; // هذه هي الحالة قبل أي تحديث في هذا الـ Webhook Call
                        $newInvoiceStatus = $originalInvoiceStatus;

                        // تحديد حالة الفاتورة الجديدة بناءً على خيار الدفع والمبلغ المدفوع
                        if ($invoice->payment_option === 'down_payment') {
                            // إذا كان عربونًا، يجب أن تصبح الحالة مدفوعة جزئيًا
                            $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                        } elseif ($invoice->payment_option === 'full') {
                            // إذا كان دفعًا كاملاً
                            $newInvoiceStatus = Invoice::STATUS_PAID;
                        } else {
                            // حالة افتراضية إذا لم يكن payment_option محددًا بشكل صحيح
                            // قد تحتاج لمراجعة هذا بناءً على المبلغ المدفوع مقابل إجمالي الفاتورة
                            if (abs($amountPaidInThisTransaction - $invoice->amount) < 0.01) { // مقارنة الأرقام العشرية
                                $newInvoiceStatus = Invoice::STATUS_PAID;
                            } else if ($amountPaidInThisTransaction < $invoice->amount) {
                                $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                            }
                        }


                        if ($newInvoiceStatus !== $originalInvoiceStatus || $invoice->paid_at === null) {
                            $invoice->status = $newInvoiceStatus;
                            if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newInvoiceStatus}' via order_approved webhook (after payment creation).", ['invoice_id' => $invoice->id]);
                        }

                        // إرسال إشعارات نجاح الدفع
                        if ($customer) {
                            $customer->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for CUSTOMER {$customer->id} via order_approved webhook.");
                        }
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) {
                            $admin->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for ADMIN {$admin->id} via order_approved webhook.");
                        }

                        // تحديث حالة الحجز وإرسال إشعار تأكيد الحجز
                        $booking = $invoice->booking;
                        if ($booking && $booking->status !== Booking::STATUS_CONFIRMED && in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            $oldBookingStatus = $booking->status;
                            $booking->status = Booking::STATUS_CONFIRMED;
                            $booking->save();
                            Log::info('Booking status updated to confirmed via order_approved webhook.', ['booking_id' => $booking->id, 'old_status' => $oldBookingStatus]);
                            if ($customer) {
                                $customer->notify(new BookingConfirmedNotification($booking));
                                Log::info("BookingConfirmedNotification queued for CUSTOMER {$customer->id} via order_approved webhook.");
                            }
                            foreach($admins as $admin) {
                                $admin->notify(new BookingConfirmedNotification($booking));
                                Log::info("BookingConfirmedNotification queued for ADMIN {$admin->id} via order_approved webhook.");
                            }
                        }
                        // ... (استدعاء Authorise API كما كان) ...
                        try {
                            // ... (منطق Authorise API) ...
                        } catch (Throwable $authError) {
                            // ...
                        }
                    } // نهاية if ($paymentActuallyCreatedInThisWebhookCall)
                }); // نهاية DB::transaction

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                Log::info('Processing Tamara order_authorised event.', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                 DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) { $query->where('invoice_number', $resolved_order_reference_id); }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })->lockForUpdate()->first();

                    if (!$invoice) { Log::warning('Webhook: Invoice not found for order_authorised.', ['tamara_order_id' => $tamaraOrderId]); return; }

                    Log::info("Tamara order_authorised event received for Invoice ID {$invoice->id}. Current status: {$invoice->status}. No payment record created from this event if already handled by order_approved.");
                    // يمكنك التأكد هنا من أن حالة الفاتورة صحيحة، مثلاً إذا كانت لا تزال unpaid بشكل غير متوقع
                    // ولكن تجنب إرسال إشعارات مكررة
                    if ($invoice->booking && $invoice->booking->status !== Booking::STATUS_CONFIRMED &&
                        in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                        $oldBookingStatus = $invoice->booking->status;
                        $invoice->booking->status = Booking::STATUS_CONFIRMED;
                        $invoice->booking->save();
                        Log::info('Booking status confirmed via order_authorised webhook as it was not confirmed yet.', ['booking_id' => $invoice->booking->id, 'old_status' => $oldBookingStatus]);
                    }
                });

            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                Log::warning("Processing Tamara {$eventType} event.", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function() use ($eventType, $resolved_order_reference_id, $tamaraOrderId) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) { $query->where('invoice_number', $resolved_order_reference_id); }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED])
                    ->with('booking.user')->first();
                    
                    if ($invoice) {
                        // ... (بقية منطق معالجة الإلغاء/الرفض كما كان)
                        $customer = $invoice->booking?->user;
                        $originalStatus = $invoice->status;
                        $newStatus = ($eventType === self::EVENT_TYPE_ORDER_DECLINED || $eventType === self::EVENT_TYPE_ORDER_EXPIRED)
                            ? Invoice::STATUS_FAILED
                            : Invoice::STATUS_CANCELLED;

                        if ($newStatus !== $originalStatus) {
                            $invoice->status = $newStatus;
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);

                            if ($customer && in_array($newStatus, [Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED])) {
                                $reason = "تم " . str_replace('_', ' ', str_replace('order_', '', $eventType)) . " للطلب من قبل تمارا.";
                                try {
                                    // تأكد أن مُنشئ PaymentFailedNotification يقبل $customer كمعامل ثاني إذا كان هذا هو القصد
                                    $customer->notify(new PaymentFailedNotification($invoice, $reason)); // تم تعديل الاستدعاء إذا كان يأخذ سبب فقط
                                    Log::info("PaymentFailedNotification dispatched to customer {$customer->id} via {$eventType} webhook.");
                                } catch (\Exception $e) { Log::error("Failed to send PaymentFailedNotification to customer {$customer->id} via {$eventType}", ['error' => $e->getMessage()]); }

                                $admins = User::where('is_admin', true)->get();
                                foreach ($admins as $admin) {
                                    try {
                                        $admin->notify(new PaymentFailedNotification($invoice, $reason)); // تم تعديل الاستدعاء
                                        Log::info("PaymentFailedNotification dispatched to admin {$admin->id} via {$eventType} webhook.");
                                    } catch (\Exception $e) { Log::error("Failed to send PaymentFailedNotification to admin {$admin->id} via {$eventType}", ['error' => $e->getMessage()]); }
                                }
                            }
                        }
                    } else {
                        Log::warning("Invoice not found or already processed/cancelled for {$eventType} webhook.", ['attempted_order_reference_id' => $resolved_order_reference_id, 'tamara_order_id' => $tamaraOrderId]);
                    }
                });
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'payload_data' => $payloadData]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception after webhook processing logic.', [
                'event_type' => $eventType ?? 'unknown', 'tamara_order_id' => $tamaraOrderId ?? 'unknown',
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_internal_error_logged'], 200);
        }
    }

    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        // ... (الكود كما هو)
    }
}
