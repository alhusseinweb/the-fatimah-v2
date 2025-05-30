<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Setting; 
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

// استيرادات Tamara SDK
use Tamara\Client as TamaraClient;                         
use Tamara\Configuration as TamaraConfiguration;         
use Tamara\Notification\Authenticator as TamaraAuthenticator; 
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest; 
use Tamara\Exception\InvalidSignatureException;          

use App\Services\TamaraService;


class PaymentController extends Controller
{
    const EVENT_TYPE_ORDER_APPROVED = 'order_approved';       // 
    const EVENT_TYPE_ORDER_DECLINED = 'order_declined';       // 
    const EVENT_TYPE_ORDER_CANCELED = 'order_canceled';       // 
    const EVENT_TYPE_ORDER_EXPIRED = 'order_expired';         // 
    const EVENT_TYPE_ORDER_AUTHORISED = 'order_authorised';   // 
    const EVENT_TYPE_ORDER_CAPTURED = 'order_captured';       // 

    protected TamaraService $tamaraService;

    public function __construct(TamaraService $tamaraService)
    {
        $this->tamaraService = $tamaraService;
    }

    public function handleTamaraSuccess(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::info("Tamara success redirect received for Invoice ID: {$invoice->id}");
        $bookingId = $invoice->booking_id;

        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara success URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $initialStatus = $invoice->status;
        $previousStatus = session('previous_invoice_status_' . $invoice->id, $initialStatus);
        $sessionKey = 'previous_invoice_status_' . $invoice->id;
        
        $totalPaid = Payment::where('invoice_id', $invoice->id)
                        ->where('status', 'completed')
                        ->sum('amount');
                        
        $epsilon = 0.1; 
        $isPaidInFull = abs($totalPaid - $invoice->amount) < $epsilon && $invoice->amount > 0.009;
        
        if ($isPaidInFull && $initialStatus !== Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_PAID;
            if ($invoice->paid_at === null) {
                $invoice->paid_at = Carbon::now();
            }
            $invoice->save();
            Log::info("Invoice status updated from {$initialStatus} to PAID in success redirect handler - full payment confirmed", 
                    ['invoice_id' => $invoice->id, 'total_paid' => $totalPaid, 'invoice_amount' => $invoice->amount]);
        } elseif ($totalPaid > $epsilon && !$isPaidInFull && $initialStatus !== Invoice::STATUS_PARTIALLY_PAID && $initialStatus !== Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            if ($invoice->paid_at === null) { 
                $invoice->paid_at = Carbon::now();
            }
            $invoice->save();
            Log::info("Invoice status updated from {$initialStatus} to PARTIALLY_PAID in success redirect handler.",
                    ['invoice_id' => $invoice->id, 'total_paid' => $totalPaid, 'invoice_amount' => $invoice->amount]);
        }
        
        $invoice->refresh(); 
        
        $successMessage = 'تم استلام دفعتك بنجاح!';
        if ($invoice->status === Invoice::STATUS_PAID) {
            $successMessage = ($previousStatus === Invoice::STATUS_PARTIALLY_PAID || $initialStatus === Invoice::STATUS_PARTIALLY_PAID) 
                ? "تم استلام المبلغ المتبقي للفاتورة بنجاح! شكراً لثقتكم بنا."
                : 'تم استلام المبلغ كاملاً بنجاح! تم تأكيد حجزك.';
        } elseif ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $successMessage = 'تم استلام دفعة العربون بنجاح! تم تأكيد حجزك.';
        } else {
            Log::info("Tamara success redirect, but final invoice status from DB is '{$invoice->status}'. Webhook should update it soon or might have already processed.", ['invoice_id' => $invoice->id]);
        }

        if (session()->has($sessionKey)) {
            session()->forget($sessionKey);
            Log::debug("Cleared previous status from session for invoice: " . $invoice->id);
        }

        Log::info("Redirecting user after Tamara success redirect to booking pending page.", ['booking_id' => $bookingId, 'invoice_id' => $invoice->id, 'invoice_status_for_redirect' => $invoice->status]);
        
        return Redirect::route('booking.pending', $bookingId) 
                        ->with('success', $successMessage);
    }

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
            if ($customer) {
                try {
                    $customer->notify(new PaymentFailedNotification($invoice, $reason));
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

    public function handleTamaraCancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::warning("Tamara cancel redirect received for Invoice ID: {$invoice->id}");
        return $this->handleTamaraFailure($request, $invoice);
    }

    public function handleTamaraWebhook(Request $request)
    {
        $rawContent = $request->getContent(); 
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'request_class' => get_class($request), // تسجيل نوع كائن الطلب
            'headers' => $request->headers->all(),
            'query_parameters' => $request->query(),
            'raw_content_sample' => Str::limit($rawContent, 1000),
            'server_time' => date('Y-m-d H:i:s')
        ]);

        $notificationToken = Setting::where('key', 'tamara_notification_token')->value('value');
        $bypassVerification = filter_var(
            Setting::where('key', 'tamara_webhook_verification_bypass')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$bypassVerification) {
            if (empty($notificationToken)) {
                Log::error('Tamara Webhook Error: Notification Token for Tamara is not set in DB settings. Cannot verify signature.');
                return response()->json(['status' => 'error', 'message' => 'Notification token not configured'], 500);
            }
            try {
                $authenticator = new TamaraAuthenticator($notificationToken);
                $authenticator->authenticate($request); // تمرير كائن الطلب $request مباشرة
                Log::info('Tamara Webhook: Signature verified successfully using the Request object (Tamara SDK internal handling).');

            } catch (InvalidSignatureException $e) { 
                Log::error('Tamara Webhook Error: Invalid Signature from SDK.', ['message' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401); 
            } catch (\TypeError $e) { 
                Log::error('Tamara Webhook Error: TypeError during SDK authenticate() call. SDK might expect different parameters, or there is an SDK version/compatibility issue.', [
                    'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                    'passed_object_type' => get_class($request)
                ]);
                return response()->json(['status' => 'error', 'message' => 'SDK signature verification method error. Check SDK documentation.'], 500);
            }
             catch (\Exception $e) { 
                Log::error('Tamara Webhook Error: Exception during signature verification by SDK.', ['message' => $e->getMessage(), 'class' => get_class($e)]);
                return response()->json(['status' => 'error', 'message' => 'Signature verification failed due to an unexpected SDK error.'], 500);
            }
        } else {
            Log::info('Tamara Webhook: Signature verification is BYPASSED as per settings.');
        }
        
        $requestData = json_decode($rawContent, true) ?: [];
        
        $tamaraOrderId = $requestData['order_id'] ?? null;
        $resolved_order_reference_id = $requestData['order_reference_id'] ?? ($requestData['order_number'] ?? null);
        $eventType = $requestData['event_type'] ?? null;

        if (!$eventType && isset($requestData['order_status'])) {
            $eventType = match (strtolower($requestData['order_status'])) {
                'approved' => self::EVENT_TYPE_ORDER_APPROVED,
                'authorized' => self::EVENT_TYPE_ORDER_AUTHORISED,
                'canceled', 'cancelled' => self::EVENT_TYPE_ORDER_CANCELED,
                'declined' => self::EVENT_TYPE_ORDER_DECLINED,
                'expired' => self::EVENT_TYPE_ORDER_EXPIRED,
                 //  إضافة حالة captured من التوثيق 
                'captured' => self::EVENT_TYPE_ORDER_CAPTURED, 
                default => null,
            };
            if(isset($requestData['event_type']) && $requestData['event_type'] === 'order_captured'){ // 
                $eventType = self::EVENT_TYPE_ORDER_CAPTURED;
            }
        }
        
        Log::info('Tamara Webhook Data (after potential auth):', [
            'order_id' => $tamaraOrderId,
            'order_reference_id' => $resolved_order_reference_id,
            'event_type' => $eventType,
        ]);
        
        if (empty($tamaraOrderId) || empty($eventType)) {
            Log::error('Tamara Webhook Error: Missing required data (order_id or event_type) for processing.', ['request_data_parsed' => $requestData]);
            return response()->json(['status' => 'error_missing_data_for_logic_but_received'], 200);
        }

        try {
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $eventType) { 
                    
                    $invoiceQuery = Invoice::query();
                    if ($resolved_order_reference_id) {
                        $invoiceQuery->where('invoice_number', $resolved_order_reference_id);
                    }
                    elseif ($tamaraOrderId) {
                        $invoiceQuery->where('payment_gateway_ref', $tamaraOrderId);
                    } else {
                        Log::warning("Webhook: Cannot find invoice, both order_reference_id and tamaraOrderId are missing for {$eventType}.");
                        return;
                    }
                    $invoice = $invoiceQuery->with('booking.user', 'booking.service')->lockForUpdate()->first();

                    if (!$invoice) { Log::warning("Webhook: Invoice not found for {$eventType}.", ['tamara_order_id' => $tamaraOrderId, 'ref_id' => $resolved_order_reference_id]); return; }
                    if (!$invoice->booking || !$invoice->booking->user) { Log::warning("Webhook: Booking or User not found for invoice during {$eventType}.", ['invoice_id' => $invoice->id]); return; }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)->where('invoice_id', $invoice->id)->exists();
                    $paymentActuallyCreatedInThisWebhookCall = false;
                    $amountPaidInThisTransaction = 0.0; // سيتم تحديده أدناه
                    $tamaraCurrency = $invoice->currency ?: 'SAR';

                    if (!$existingPayment) {
                        $amountPaidInThisTransaction = $this->extractAmountFromWebhook($requestData, $invoice);
                        Log::info("Webhook: Determined amount to record as {$amountPaidInThisTransaction} {$tamaraCurrency} for {$eventType}.", ['invoice_id' => $invoice->id]);

                        if ($amountPaidInThisTransaction > 0.009) {
                            Payment::create([
                                'invoice_id' => $invoice->id,
                                'transaction_id' => $tamaraOrderId,
                                'amount' => $amountPaidInThisTransaction,
                                'currency' => $tamaraCurrency,
                                'status' => 'completed',
                                'payment_gateway' => 'tamara',
                                'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => $eventType, 'webhook_payload_received' => Str::limit(json_encode($requestData),1900)]) ?: null,
                            ]);
                            Log::info("Payment record CREATED via webhook for {$eventType}.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                            $paymentActuallyCreatedInThisWebhookCall = true;
                        } else {
                             Log::info("Skipping payment creation via webhook for {$eventType} as amount is zero or less.", ['invoice_id' => $invoice->id, 'calculated_amount' => $amountPaidInThisTransaction]);
                        }
                    } else {
                        Log::warning("Duplicate payment webhook ({$eventType}) or payment already recorded for this Tamara Order ID. Ignored.", [
                            'invoice_id' => $invoice->id, 'tamara_order_id' => $tamaraOrderId
                        ]);
                        // حتى لو كانت دفعة مكررة، قد نحتاج لتشغيل Authorise/Capture إذا لم يتم سابقاً
                        // لكن هذا يعتمد على تدفق عملك. حالياً، سيتجاهل إذا كانت الدفعة موجودة.
                        // إذا كان الـ Authorise والـ Capture يجب أن يحدثا بغض النظر عن إنشاء سجل Payment جديد،
                        // يجب تعديل هذا الشرط. للتبسيط، سنفترض أن العمليات تتبع إنشاء الدفعة.
                        // إذا تم إنشاء الدفعة بالفعل بنجاح، فمن المفترض أن Authorise و Capture قد تمت محاولتهما.
                    }

                    if ($paymentActuallyCreatedInThisWebhookCall) {
                        $customer = $invoice->booking->user;
                        $originalInvoiceStatus = $invoice->status;
                        $newInvoiceStatus = $originalInvoiceStatus;

                        $totalPaid = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
                        $epsilon = 0.1;

                        if ($totalPaid >= ($invoice->amount - $epsilon) && $invoice->amount > 0.009) {
                            $newInvoiceStatus = Invoice::STATUS_PAID;
                        } elseif ($totalPaid > $epsilon && $totalPaid < ($invoice->amount - $epsilon)) {
                            $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                        } else if ($totalPaid <= $epsilon && $originalInvoiceStatus !== Invoice::STATUS_PAID) {
                             $newInvoiceStatus = $originalInvoiceStatus;
                        } else {
                            if ($totalPaid >= ($invoice->amount - $epsilon) && $invoice->amount > 0.009) {
                               $newInvoiceStatus = Invoice::STATUS_PAID;
                            } else {
                               $newInvoiceStatus = $originalInvoiceStatus;
                            }
                        }
                        Log::info("Webhook: Invoice status determination. TotalPaid: {$totalPaid}, InvoiceAmount: {$invoice->amount}, OriginalStatus: {$originalInvoiceStatus}, NewStatusCandidate: {$newInvoiceStatus}", ['invoice_id' => $invoice->id]);

                        if ($newInvoiceStatus !== $originalInvoiceStatus || (($newInvoiceStatus === Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) && $invoice->paid_at === null)) {
                            $invoice->status = $newInvoiceStatus;
                            if (($newInvoiceStatus === Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) && $invoice->paid_at === null) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated from '{$originalInvoiceStatus}' to '{$newInvoiceStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);
                        }

                        if ($customer) {
                            $customer->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                        }
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) {
                            $admin->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                        }

                        $booking = $invoice->booking;
                        if ($booking && $booking->status !== Booking::STATUS_CONFIRMED && in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            $oldBookingStatus = $booking->status;
                            $booking->status = Booking::STATUS_CONFIRMED; // تم تأكيد الحجز الآن بعد الدفع
                            $booking->save();
                            Log::info("Booking status updated to CONFIRMED via {$eventType} webhook.", ['booking_id' => $booking->id, 'old_status' => $oldBookingStatus]);
                            if ($customer) $customer->notify(new BookingConfirmedNotification($booking));
                            foreach($admins as $admin) $admin->notify(new BookingConfirmedNotification($booking));
                        }
                        
                        // استدعاء Authorise API ثم Capture API إذا لزم الأمر
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            $tamaraApiUrl = Setting::where('key', 'tamara_api_url')->value('value');
                            $tamaraApiToken = Setting::where('key', 'tamara_api_token')->value('value');
                            $tamaraRequestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? 30;

                            if(!empty($tamaraApiUrl) && !empty($tamaraApiToken)) {
                                try {
                                    $configurationAuth = TamaraConfiguration::create($tamaraApiUrl, $tamaraApiToken, $tamaraRequestTimeout);
                                    $client = TamaraClient::create($configurationAuth);
                                    $authoriseOrderRequest = new TamaraAuthoriseOrderRequest($tamaraOrderId);
                                    Log::info("Attempting to Authorise Tamara Order via Webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId]);
                                    $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                    
                                    if ($authoriseResponse->isSuccess()) {
                                        Log::info("Tamara Order Authorised successfully.", [
                                            'tamara_order_id' => $tamaraOrderId,
                                            'auth_order_id' => $authoriseResponse->getOrderId(),
                                            'auth_status' => $authoriseResponse->getStatus(),
                                            'auto_captured' => $authoriseResponse->isAutoCaptured(),
                                        ]);

                                        // إذا لم يتم الالتقاط تلقائياً، قم باستدعاء Capture API
                                        if (!$authoriseResponse->isAutoCaptured()) { // 
                                            Log::info("Tamara Order Authorised but not auto-captured. Proceeding to capture payment for Order ID: {$tamaraOrderId}");
                                            
                                            $amountToCapture = $amountPaidInThisTransaction; // المبلغ الذي تم تفويضه للتو
                                            
                                            $captureResponse = $this->tamaraService->capturePayment(
                                                $tamaraOrderId,
                                                $amountToCapture,
                                                $invoice->currency,
                                                $invoice // تمرير الفاتورة للحصول على تفاصيل البنود
                                            );

                                            if ($captureResponse && isset($captureResponse['capture_id'])) {
                                                Log::info("Tamara payment CAPTURED successfully for Order ID: {$tamaraOrderId}. Capture ID: {$captureResponse['capture_id']}");
                                                $paymentToUpdate = Payment::where('transaction_id', $tamaraOrderId)
                                                                        ->where('invoice_id', $invoice->id)
                                                                        ->first();
                                                if($paymentToUpdate){
                                                    $paymentDetails = (array) json_decode($paymentToUpdate->payment_details, true);
                                                    $paymentDetails['tamara_capture_info'] = $captureResponse; // أو $captureResponse['data']
                                                    $paymentToUpdate->payment_details = json_encode($paymentDetails);
                                                    $paymentToUpdate->save();
                                                }
                                            } else {
                                                Log::error("Failed to CAPTURE Tamara payment for Order ID: {$tamaraOrderId}. Invoice ID: {$invoice->id}", ['response' => $captureResponse]);
                                            }
                                        } else {
                                            Log::info("Tamara Order was auto-captured during authorisation for Order ID: {$tamaraOrderId}.");
                                        }
                                    } else {
                                        Log::error("Failed to Authorise Tamara Order.", ['tamara_order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                    }
                                } catch (Throwable $authError) {
                                    Log::error("Exception during Tamara Authorise/Capture API call.", ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                                }
                            } else {
                                Log::error("Tamara API settings missing for Authorise/Capture call.", ['tamara_order_id' => $tamaraOrderId]);
                            }
                        }
                    }
                });

            } elseif ($eventType === self::EVENT_TYPE_ORDER_CAPTURED) {
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                $captureData = $requestData['data'] ?? [];
                $captureId = $captureData['capture_id'] ?? null;
                $capturedAmount = isset($captureData['captured_amount']['amount']) ? (float)$captureData['captured_amount']['amount'] : null;
                $captureStatus = $captureData['status'] ?? null;

                Log::info("Tamara Order Captured Webhook Details:", [
                    'tamara_order_id' => $tamaraOrderId, 'capture_id' => $captureId,
                    'captured_amount' => $capturedAmount, 'capture_status' => $captureStatus
                ]);

                $invoice = Invoice::where('payment_gateway_ref', $tamaraOrderId)
                                ->orWhere('invoice_number', $resolved_order_reference_id)
                                ->first();
                if ($invoice) {
                    $payment = $invoice->payments()
                                    ->where('transaction_id', $tamaraOrderId)
                                    ->where('payment_gateway', 'tamara')->latest()->first();
                    if($payment){
                        $paymentDetails = (array) json_decode($payment->payment_details, true);
                        $paymentDetails['tamara_capture_info'] = array_merge($paymentDetails['tamara_capture_info'] ?? [], $captureData);
                        $payment->payment_details = json_encode($paymentDetails);
                        $payment->save();
                    }
                    
                    if ($captureStatus === 'fully_captured' && $invoice->status !== Invoice::STATUS_PAID) {
                        $invoice->status = Invoice::STATUS_PAID;
                        if(!$invoice->paid_at && $capturedAmount > 0.009) $invoice->paid_at = Carbon::now();
                        $invoice->save();
                        Log::info("Invoice {$invoice->id} status confirmed as PAID due to full capture from Tamara webhook.");
                    }
                } else {
                    Log::warning("Received Tamara 'order_captured' webhook but could not find related invoice.", [/* ... */]);
                }
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED, self::EVENT_TYPE_ORDER_AUTHORISED])) {
                 Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                if (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                    DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $eventType, $requestData) {
                        // ... (منطق معالجة هذه الحالات) ...
                    });
                } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                     Log::info("Tamara order has been authorised. Capture should follow if not auto-captured.", ['tamara_order_id' => $tamaraOrderId]);
                }
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId]);
            }
            return response()->json(['status' => 'success_event_processed'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception during Tamara webhook processing (after auth).', [
                'event_type' => $eventType ?? 'unknown', 'tamara_order_id' => $tamaraOrderId,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'error_processing_event_but_received'], 200);
        }
    }

    /**
     * Helper to extract amount from webhook payload with fallbacks.
     */
    protected function extractAmountFromWebhook(array $requestData, Invoice $invoice): float
    {
        $tamaraAmount = null;
        if (isset($requestData['total_amount']['amount'])) {
            $tamaraAmount = $requestData['total_amount']['amount'];
        } elseif (isset($requestData['order_amount']['amount'])) {
            $tamaraAmount = $requestData['order_amount']['amount'];
        } elseif (isset($requestData['data']['payment_amount']['amount'])) {
            $tamaraAmount = $requestData['data']['payment_amount']['amount'];
        } elseif (isset($requestData['data']['total_amount']['amount'])) {
            $tamaraAmount = $requestData['data']['total_amount']['amount'];
        }

        if ($tamaraAmount !== null) {
            return (float) $tamaraAmount;
        }

        // Fallback logic
        if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $existingPaymentsTotal = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
            $remainingAmount = $invoice->amount - $existingPaymentsTotal;
            return ($remainingAmount > 0.009) ? (float) $remainingAmount : 0.0;
        } elseif ($invoice->payment_option === 'down_payment' && !in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
            if ($invoice->booking && isset($invoice->booking->down_payment_amount) && $invoice->booking->down_payment_amount > 0.009) {
                return (float) $invoice->booking->down_payment_amount;
            }
            // إذا لم يتم العثور على down_payment_amount، قد نستخدم 50% من مبلغ الفاتورة كافتراضي للعربون
            Log::warning("Webhook Fallback: down_payment_amount not found on booking {$invoice->booking_id}, defaulting to 50% of invoice amount for downpayment scenario.", ['invoice_id' => $invoice->id]);
            return round((float) $invoice->amount / 2, 2); 
        } else { // للحالة الكاملة UNPAID أو حالات أخرى
            return (float) $invoice->amount;
        }
    }


    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::debug('Entering retryTamaraPayment method.', ['invoice_id' => $invoice->id, 'current_invoice_status' => $invoice->status, 'original_payment_option' => $invoice->payment_option]);

        if (auth()->guest() || !$invoice->booking || $invoice->booking->user_id !== auth()->id()) {
            Log::warning('Unauthorized retry attempt.', ['invoice_id' => $invoice->id, 'user_id' => auth()->id()]);
            return Redirect::route('customer.dashboard')->with('error', 'غير مصرح لك بإجراء هذا.');
        }

        $allowedRetryStatuses = [
            Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED, Invoice::STATUS_EXPIRED,
            Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PENDING
        ];

        if (!in_array($invoice->status, $allowedRetryStatuses)) {
            Log::warning('Retry attempt on invoice with non-retryable status.', ['invoice_id' => $invoice->id, 'status' => $invoice->status]);
            return Redirect::route('customer.invoices.show', $invoice)->with('error', "لا يمكن إعادة محاولة الدفع لهذه الفاتورة بالحالة الحالية: " . ($invoice->status_label ?? $invoice->status));
        }

        $amountToRetry = 0.0;
        $retryPaymentOption = 'full'; 

        if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $amountToRetry = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : 0.0;
        } elseif ($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PAID) { 
            $amountToRetry = $invoice->booking?->down_payment_amount > 0.009
                                ? $invoice->booking->down_payment_amount
                                : ($invoice->amount > 0.009 ? round($invoice->amount / 2, 2) : 0.0);
            $retryPaymentOption = 'down_payment';
        } else { 
            $amountToRetry = $invoice->amount;
        }
        Log::info("Retry Tamara: Determined amount to retry: {$amountToRetry} with option: {$retryPaymentOption}", ['invoice_id' => $invoice->id]);

        $amountToRetry = round((float)$amountToRetry, 2);

        if ($amountToRetry <= 0.009) {
            Log::info('Retry attempt skipped as calculated amount to retry is zero or less.', [ /* ... */ ]);
            if ($invoice->status === Invoice::STATUS_PAID) {
                return Redirect::route('customer.invoices.show', $invoice)->with('info', 'الفاتورة مدفوعة بالكامل.');
           }
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'المبلغ المطلوب دفعه غير صحيح أو أن الفاتورة لا تتطلب دفعة حالياً. يرجى التواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.');
       }

       Log::debug('Proceeding to initiate checkout for retry.', [ /* ... */ ]);

       try {
           $sessionKey = 'previous_invoice_status_' . $invoice->id;
           session([$sessionKey => $invoice->status]);
           Log::debug("Stored previous status '{$invoice->status}' in session for key: {$sessionKey}");
           
           $checkoutResponse = $this->tamaraService->initiateCheckout($invoice, $amountToRetry, $retryPaymentOption);

           if ($checkoutResponse && is_array($checkoutResponse) && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['order_id'])) {
               if(empty($invoice->payment_gateway_ref) || $invoice->payment_gateway_ref !== $checkoutResponse['order_id']) {
                   $invoice->payment_gateway_ref = $checkoutResponse['order_id']; 
                   $invoice->save();
               }
               Log::info('Tamara retry checkout URL obtained and invoice updated with new gateway ref.', ['invoice_id' => $invoice->id, 'tamara_order_id' => $checkoutResponse['order_id']]);
               return Redirect::away($checkoutResponse['checkout_url']);
           } else {
               session()->forget($sessionKey);
               Log::error('Failed to get valid checkout response from TamaraService on retry.', ['invoice_id' => $invoice->id, 'response' => $checkoutResponse]);
               return Redirect::route('customer.invoices.show', $invoice)->with('error', 'فشل بدء عملية الدفع مع تمارا. يرجى المحاولة مرة أخرى.');
           }
       } catch (Throwable $e) {
           session()->forget($sessionKey);
           Log::error('Exception during Tamara retry payment initiation.', [ /* ... */ ]);
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'حدث خطأ غير متوقع أثناء محاولة الدفع. يرجى المحاولة مرة أخرى.');
       }
   }
}
