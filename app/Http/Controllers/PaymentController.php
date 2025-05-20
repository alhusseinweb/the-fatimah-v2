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

// مسارات SDK تمارا v2.0 - **تحقق من هذه المسارات في مجلد vendor/tamara-solution/php-sdk/src لديك**
use Tamara\Client as TamaraClient;
use Tamara\Configuration as TamaraConfiguration;
// Authenticator ليس مستخدماً في هذا الإصدار من الكود بسبب تجاوز المصادقة
// use Tamara\Notification\Authenticator as TamaraAuthenticator; 
use Tamara\Model\Order\Order as TamaraOrderModel; // تم تغيير الاسم لتجنب التعارض مع Order Model محلي
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest;
// use Tamara\Exception\ForbiddenException as TamaraForbiddenException; // لن يتم استخدامه بسبب تجاوز المصادقة
// use Tamara\Exception\RequestException as TamaraRequestException; // قد لا يتم استخدامه

// --- START: إضافة TamaraService هنا ---
use App\Services\TamaraService; // تأكد من أن هذا هو المسار الصحيح لخدمة تمارا لديك
// --- END: إضافة TamaraService هنا ---


class PaymentController extends Controller
{
    // تعريف الثوابت هنا لأننا لا نستخدم Authenticator الذي قد يوفرها
    // أو يمكنك استخدام الثوابت مباشرة من TamaraOrderModel إذا كانت public static
    const EVENT_TYPE_ORDER_APPROVED = 'order_approved';
    const EVENT_TYPE_ORDER_DECLINED = 'order_declined';
    const EVENT_TYPE_ORDER_CANCELED = 'order_canceled'; // أو 'order_cancelled' حسب ما ترسله تمارا
    const EVENT_TYPE_ORDER_EXPIRED = 'order_expired';
    const EVENT_TYPE_ORDER_AUTHORISED = 'order_authorised';

    // --- START: إضافة مُنشئ وحقن TamaraService ---
    protected TamaraService $tamaraService;

    public function __construct(TamaraService $tamaraService)
    {
        $this->tamaraService = $tamaraService;
    }
    // --- END: إضافة مُنشئ وحقن TamaraService ---


    /**
     * Handle successful payment redirection from Tamara.
     */
    public function handleTamaraSuccess(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::info("Tamara success redirect received for Invoice ID: {$invoice->id}");
        $bookingId = $invoice->booking_id;

        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara success URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $invoice->refresh(); // تحديث بيانات الفاتورة من قاعدة البيانات
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
            Log::info("Tamara success redirect, but final invoice status from DB is '{$invoice->status}'. Webhook should update it soon. Using default success message.", ['invoice_id' => $invoice->id]);
        }

        if (session()->has($sessionKey)) {
            session()->forget($sessionKey);
            Log::debug("Cleared previous status from session for invoice: " . $invoice->id);
        }

        Log::info("Redirecting user after Tamara success redirect.", ['booking_id' => $bookingId, 'invoice_id' => $invoice->id, 'invoice_status_for_redirect' => $invoice->status]);
        // توجيه المستخدم إلى صفحة تفاصيل الفاتورة أو الحجز
        return Redirect::route('customer.invoices.show', $invoice->id) 
                        ->with('success', $successMessage);
    }

    /**
     * Handle failed payment redirection from Tamara.
     */
    public function handleTamaraFailure(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::error("Tamara failure/cancel redirect received for Invoice ID: {$invoice->id}");
        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara failure URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $originalStatus = $invoice->status;
        $customer = $invoice->booking?->user;
        $reason = "فشل الدفع عبر تمارا أو تم الإلغاء من قبل العميل.";

        if (!in_array($originalStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
            Log::info("Tamara failure redirect: Invoice status '{$originalStatus}' for invoice: {$invoice->id}. No status change from redirect. Webhook will handle if truly failed.");
            
            // تأكد أن مُنشئ PaymentFailedNotification يأخذ هذه المعاملات
            // الكود الأصلي الذي قدمته كان يمرر $customer أو $admin كمعامل ثاني للإشعار
            // إذا كان PaymentFailedNotification يتوقع $recipient كمعامل ثاني، فستحتاج لتمريره
            if ($customer) {
                try {
                    $customer->notify(new PaymentFailedNotification($invoice, $reason)); // افترض أن المُنشئ يأخذ (Invoice, ?string $reason)
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
                    $admin->notify(new PaymentFailedNotification($invoice, $reason)); // افترض أن المُنشئ يأخذ (Invoice, ?string $reason)
                    Log::info("PaymentFailedNotification dispatched to admin {$admin->id} via failure redirect.");
                } catch (\Exception $e) {
                    Log::error("Failed to send PaymentFailedNotification to admin {$admin->id}", ['error' => $e->getMessage()]);
                }
            }
        } else {
            Log::info("Tamara failure redirect received, but invoice status was already '{$originalStatus}'. Not changing status or sending notifications from redirect.", ['invoice_id' => $invoice->id]);
        }

        return Redirect::route('booking.pending', ['booking' => $invoice->booking_id])
                        ->with('error', 'فشلت عملية الدفع أو تم إلغاؤها. يمكنك محاولة الدفع مرة أخرى أو التواصل معنا.');
    }

    /**
     * Handle cancelled payment redirection from Tamara.
     */
    public function handleTamaraCancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::warning("Tamara cancel redirect received for Invoice ID: {$invoice->id}");
        return $this->handleTamaraFailure($request, $invoice);
    }

    /**
     * Handles the incoming webhook notification from Tamara.
     * مع الحفاظ على تجاوز المصادقة وتصحيح منطق تحديث حالة الفاتورة.
     */
    public function handleTamaraWebhook(Request $request)
    {
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(), 'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(), 'ip_address' => $request->ip(),
            'query_parameters' => $request->query(), 'form_parameters_or_json' => $request->all(),
            'raw_content' => $request->getContent(), 'server_time' => date('Y-m-d H:i:s')
        ]);

        // --- START: تجاوز المصادقة (كما هو في الكود الذي قدمته سابقًا) ---
        Log::info("Tamara Webhook received. Processing with bypass authentication for now.");

        $rawContent = $request->getContent();
        $requestData = json_decode($rawContent, true) ?: [];
        $tamaraOrderId = $requestData['order_id'] ?? null;
        $resolved_order_reference_id = $requestData['order_reference_id'] ?? ($requestData['order_number'] ?? null);
        
        $eventType = $requestData['event_type'] ?? null;
        if (!$eventType && isset($requestData['order_status'])) {
            $eventType = match (strtolower($requestData['order_status'])) {
                'approved' => self::EVENT_TYPE_ORDER_APPROVED,
                'authorized' => self::EVENT_TYPE_ORDER_AUTHORISED,
                'canceled', 'cancelled' => self::EVENT_TYPE_ORDER_CANCELED, // التعامل مع كلا التهجئتين
                'declined' => self::EVENT_TYPE_ORDER_DECLINED,
                'expired' => self::EVENT_TYPE_ORDER_EXPIRED,
                default => null,
            };
        }

        Log::info('Tamara Webhook Data (auth bypassed):', [
            'order_id' => $tamaraOrderId,
            'order_reference_id' => $resolved_order_reference_id,
            'event_type' => $eventType,
            'raw_content_sample' => Str::limit($rawContent, 200)
        ]);
        
        if (empty($tamaraOrderId) || empty($eventType)) {
            Log::error('Tamara Webhook Error: Missing required data (order_id or event_type) for processing (auth bypassed).', ['request_data' => $requestData]);
            return response()->json(['status' => 'error_missing_data_for_logic'], 200);
        }
        // --- END: تجاوز المصادقة ---

        try {
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info('Processing Tamara order_approved event (auth bypassed).', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);

                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $request) { // أضفت $request للاحتياط
                    
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->with('booking.user', 'booking.service')
                    ->lockForUpdate() 
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
                        $tamaraAmount = $requestData['total_amount']['amount'] ?? 
                                        ($requestData['order_amount']['amount'] ?? 
                                        (isset($requestData['data']['payment_amount']['amount']) ? $requestData['data']['payment_amount']['amount'] : // التحقق من وجود data أولاً
                                        (isset($requestData['data']['total_amount']['amount']) ? $requestData['data']['total_amount']['amount'] : null)));

                        $tamaraCurrency = $requestData['total_amount']['currency'] ?? 
                                          ($requestData['order_amount']['currency'] ?? 
                                          (isset($requestData['data']['payment_amount']['currency']) ? $requestData['data']['payment_amount']['currency'] :
                                          (isset($requestData['data']['total_amount']['currency']) ? $requestData['data']['total_amount']['currency'] : $tamaraCurrency)));
                        
                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                            Log::info("Amount extracted directly from webhook payload for order_approved: {$amountPaidInThisTransaction} {$tamaraCurrency}", ['invoice_id' => $invoice->id]);
                        } else {
                            Log::warning("Could not extract amount from Tamara webhook for order_approved. Estimating based on invoice type.", ['tamara_order_id' => $tamaraOrderId, 'invoice_id' => $invoice->id, 'request_data_keys' => array_keys($requestData)]);
                            if ($invoice->payment_option === 'down_payment') {
                                $estimatedAmount = $invoice->booking->down_payment_amount > 0 
                                    ? $invoice->booking->down_payment_amount 
                                    : round($invoice->amount / 2, 0);
                                $amountPaidInThisTransaction = $estimatedAmount;
                            } else { 
                                $amountPaidInThisTransaction = $invoice->amount;
                            }
                             Log::info("Estimated amount for payment_option '{$invoice->payment_option}': {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                        }

                        if ($amountPaidInThisTransaction > 0.009) {
                            Payment::create([
                                'invoice_id' => $invoice->id,
                                'transaction_id' => $tamaraOrderId,
                                'amount' => $amountPaidInThisTransaction,
                                'currency' => $tamaraCurrency,
                                'status' => 'completed',
                                'payment_gateway' => 'tamara',
                                'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => $eventType, 'webhook_payload_received' => $requestData]) ?: null,
                            ]);
                            Log::info("Payment record CREATED via webhook for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                            $paymentActuallyCreatedInThisWebhookCall = true;
                        } else {
                             Log::info("Skipping payment creation as amount is zero or less for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                        }
                    } else {
                        Log::warning("Duplicate payment webhook (order_approved) or payment already recorded. Ignored for order_id.", [
                            'invoice_id' => $invoice->id,
                            'tamara_order_id' => $tamaraOrderId
                        ]);
                    }

                    if ($paymentActuallyCreatedInThisWebhookCall) {
                        $customer = $invoice->booking->user;
                        $originalInvoiceStatus = $invoice->status; 
                        $newInvoiceStatus = $originalInvoiceStatus;

                        // --- START: منطق تحديد حالة الفاتورة المعدل ---
                        if ($invoice->payment_option === 'down_payment') {
                            $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                            if (abs($amountPaidInThisTransaction - $invoice->amount) < 0.01 && $invoice->amount > 0) { // إذا كان العربون هو كامل المبلغ
                                $newInvoiceStatus = Invoice::STATUS_PAID;
                                Log::info("Down payment covers full amount, setting invoice to PAID.", ['invoice_id' => $invoice->id]);
                            }
                        } else { // payment_option is 'full' or unspecified
                            // إذا تم دفع المبلغ المتبقي أو كامل المبلغ
                            $totalCurrentlyPaid = $invoice->payments()->where('status', 'completed')->sum('amount'); // لا يشمل الدفعة الحالية بعد
                            if (abs(($totalCurrentlyPaid + $amountPaidInThisTransaction) - $invoice->amount) < 0.01 && $invoice->amount > 0) {
                                $newInvoiceStatus = Invoice::STATUS_PAID;
                            } elseif (($totalCurrentlyPaid + $amountPaidInThisTransaction) > 0) {
                                $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                            } else {
                                $newInvoiceStatus = Invoice::STATUS_UNPAID;
                            }
                        }
                        // --- END: منطق تحديد حالة الفاتورة المعدل ---

                        if ($newInvoiceStatus !== $originalInvoiceStatus || ($newInvoiceStatus === Invoice::STATUS_PAID && $invoice->paid_at === null) || ($newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID && $invoice->paid_at === null)) {
                            $invoice->status = $newInvoiceStatus;
                            if (($newInvoiceStatus === Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) && $invoice->paid_at === null) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newInvoiceStatus}' via order_approved webhook (after payment creation).", ['invoice_id' => $invoice->id]);
                        }

                        if ($customer) {
                            $customer->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for CUSTOMER {$customer->id} via order_approved webhook.");
                        }
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) {
                            $admin->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for ADMIN {$admin->id} via order_approved webhook.");
                        }

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
                        
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            try {
                                $apiUrlAuth = config('services.tamara.url'); $apiTokenAuth = config('services.tamara.token'); $timeoutAuth = config('services.tamara.request_timeout', 10);
                                if(!empty($apiUrlAuth) && !empty($apiTokenAuth)) {
                                    $configurationAuth = TamaraConfiguration::create($apiUrlAuth, $apiTokenAuth, $timeoutAuth);
                                    $client = TamaraClient::create($configurationAuth);
                                    $authoriseOrderRequest = new TamaraAuthoriseOrderRequest($tamaraOrderId);
                                    Log::debug("Attempting Authorise API via webhook (order_approved).", ['tamara_order_id' => $tamaraOrderId]);
                                    $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                    if ($authoriseResponse->isSuccess()) {
                                        Log::info('Tamara Authorise API successful via webhook (order_approved).', ['tamara_order_id' => $tamaraOrderId, 'response_order_id' => $authoriseResponse->getOrderId(), 'response_status' => $authoriseResponse->getOrderStatus()]);
                                    } else {
                                        Log::error('Tamara Authorise API failed via webhook (order_approved).', ['tamara_order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                    }
                                } else {
                                    Log::error('Tamara Authorise API config missing (URL or Token). Skipping Authorise call.');
                                }
                            } catch (Throwable $authError) {
                                Log::error('Exception during Tamara Authorise API call via webhook (order_approved).', ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                            }
                        }
                    }
                });

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) { // تم تعديل ليستخدم الثابت
                 Log::info('Processing Tamara order_authorised event (auth bypassed).', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                 DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) { $query->where('invoice_number', $resolved_order_reference_id); }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })->lockForUpdate()->first();

                    if (!$invoice) { Log::warning('Webhook: Invoice not found for order_authorised.', ['tamara_order_id' => $tamaraOrderId]); return; }

                    Log::info("Tamara order_authorised event received for Invoice ID {$invoice->id}. Current status: {$invoice->status}. No payment record created from this event if already handled by order_approved.");
                    if ($invoice->booking && $invoice->booking->status !== Booking::STATUS_CONFIRMED &&
                        in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                        $oldBookingStatus = $invoice->booking->status;
                        $invoice->booking->status = Booking::STATUS_CONFIRMED;
                        $invoice->booking->save();
                        Log::info('Booking status confirmed via order_authorised webhook as it was not confirmed yet.', ['booking_id' => $invoice->booking->id, 'old_status' => $oldBookingStatus]);
                    }
                });

            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                Log::warning("Processing Tamara {$eventType} event (auth bypassed).", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function() use ($eventType, $resolved_order_reference_id, $tamaraOrderId) { // تأكد من تمرير المعاملات الصحيحة
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) { $query->where('invoice_number', $resolved_order_reference_id); }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED])
                    ->with('booking.user')->first();
                    
                    if ($invoice) {
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
                                    // تأكد أن مُنشئ PaymentFailedNotification يقبل هذه المعاملات
                                    $customer->notify(new PaymentFailedNotification($invoice, $reason)); 
                                    Log::info("PaymentFailedNotification dispatched to customer {$customer->id} via {$eventType} webhook.");
                                } catch (\Exception $e) { Log::error("Failed to send PaymentFailedNotification to customer {$customer->id} via {$eventType}", ['error' => $e->getMessage()]); }

                                $admins = User::where('is_admin', true)->get();
                                foreach ($admins as $admin) {
                                    try {
                                        $admin->notify(new PaymentFailedNotification($invoice, $reason));
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
                Log::info("Received unhandled Tamara webhook event type (auth bypassed): {$eventType}", ['tamara_order_id' => $tamaraOrderId]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception during webhook processing (auth bypassed).', [
                'event_type' => $eventType ?? 'unknown', 'tamara_order_id' => $tamaraOrderId ?? 'unknown',
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_error_logged'], 200);
        }
    }

    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::debug('Entering retryTamaraPayment method.', ['invoice_id' => $invoice->id]);
        if (auth()->guest() || !$invoice->booking || $invoice->booking->user_id !== auth()->id()) {
            Log::warning('Unauthorized retry attempt.', ['invoice_id' => $invoice->id, 'user_id' => auth()->id()]);
            return Redirect::route('customer.dashboard')->with('error', 'غير مصرح لك بإجراء هذا.');
        }

        $allowedRetryStatuses = [
            Invoice::STATUS_FAILED,
            Invoice::STATUS_CANCELLED,
            Invoice::STATUS_EXPIRED,
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PENDING
        ];

        if (!in_array($invoice->status, $allowedRetryStatuses)) {
            Log::warning('Retry attempt on invoice with non-retryable status.', ['invoice_id' => $invoice->id, 'status' => $invoice->status]);
            return Redirect::route('customer.invoices.show', $invoice)->with('error', "لا يمكن إعادة محاولة الدفع لهذه الفاتورة بالحالة الحالية: " . ($invoice->status_label ?? $invoice->status));
        }

        $amountToRetry = ($invoice->status === Invoice::STATUS_PARTIALLY_PAID)
                            ? ($invoice->remaining_amount ?? 0)
                            : (float) $invoice->amount;
        $amountToRetry = round($amountToRetry, 2);

        if ($amountToRetry <= 0.009) {
            Log::info('Retry attempt skipped as remaining amount is zero or less.', ['invoice_id' => $invoice->id]);
            return Redirect::route('customer.invoices.show', $invoice)->with('info', 'الفاتورة مدفوعة بالكامل.');
        }

        Log::debug('Proceeding to initiate checkout for retry.', ['invoice_id' => $invoice->id, 'amount_to_retry' => $amountToRetry]);

        try {
            // استخدام $this->tamaraService المحقونة
            $retryPaymentOption = ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) ? 'full' : ($invoice->payment_option ?? 'full');
            Log::debug('Determined paymentOption for retry.', ['invoice_id' => $invoice->id, 'original_option' => $invoice->payment_option, 'retry_option' => $retryPaymentOption]);

            $sessionKey = 'previous_invoice_status_' . $invoice->id;
            session([$sessionKey => $invoice->status]);
            Log::debug("Stored previous status '{$invoice->status}' in session for key: {$sessionKey}");

            $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountToRetry, $retryPaymentOption);

            if ($checkoutResponse && is_array($checkoutResponse) && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['order_id'])) {
                if(empty($invoice->payment_gateway_ref) || $invoice->payment_gateway_ref !== $checkoutResponse['order_id']) {
                    $invoice->payment_gateway_ref = $checkoutResponse['order_id'];
                    $invoice->save();
                }
                Log::info('Tamara retry checkout URL obtained and invoice updated.', ['invoice_id' => $invoice->id, 'tamara_order_id' => $checkoutResponse['order_id']]);
                return Redirect::away($checkoutResponse['checkout_url']);
            } else {
                session()->forget($sessionKey);
                Log::error('Failed to get valid checkout response from TamaraService on retry.', ['invoice_id' => $invoice->id, 'response' => $checkoutResponse]);
                return Redirect::route('customer.invoices.show', $invoice)->with('error', 'فشل بدء عملية الدفع. يرجى المحاولة مرة أخرى.');
            }
        } catch (Throwable $e) {
            session()->forget('previous_invoice_status_' . $invoice->id);
            Log::error('Exception during Tamara retry payment initiation.', [
                'invoice_id' => $invoice->id, 
                'error' => $e->getMessage(), 
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(),1500)
            ]);
            return Redirect::route('customer.invoices.show', $invoice)->with('error', 'حدث خطأ غير متوقع أثناء محاولة الدفع. يرجى مراجعة السجلات.');
        }
    }
}
