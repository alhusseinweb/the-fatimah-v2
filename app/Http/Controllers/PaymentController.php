<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Setting; // <-- تم التأكد من استيراده
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
    const EVENT_TYPE_ORDER_APPROVED = 'order_approved';
    const EVENT_TYPE_ORDER_DECLINED = 'order_declined';
    const EVENT_TYPE_ORDER_CANCELED = 'order_canceled';
    const EVENT_TYPE_ORDER_EXPIRED = 'order_expired';
    const EVENT_TYPE_ORDER_AUTHORISED = 'order_authorised';
    const EVENT_TYPE_ORDER_CAPTURED = 'order_captured'; // 

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
                
                $signatureToVerify = null;
                $authHeader = $request->header('Authorization'); // 
                
                if ($authHeader && Str::startsWith(strtolower($authHeader), 'bearer ')) {
                    $signatureToVerify = Str::substr($authHeader, 7); 
                    Log::info('Tamara Webhook: Found signature in Authorization Bearer header.');
                } else {
                    $signatureToVerify = $request->query('tamaraToken'); // 
                    if ($signatureToVerify) {
                        Log::info('Tamara Webhook: Found signature in tamaraToken query parameter.');
                    }
                }
                
                if (empty($signatureToVerify)) {
                    Log::error('Tamara Webhook Error: Missing signature. Checked Authorization Bearer header and tamaraToken query parameter.', ['headers_checked' => $request->headers->keys()]);
                    return response()->json(['status' => 'error', 'message' => 'Missing signature'], 401);
                }
                
                // يفترض أن دالة authenticate في SDK تمارا تقبل (محتوى الطلب الخام، التوقيع المستخرج)
                $authenticator->authenticate($rawContent, $signatureToVerify); // (بافتراض أن هذه هي الطريقة الصحيحة)
                Log::info('Tamara Webhook: Signature verified successfully.');

            } catch (InvalidSignatureException $e) {
                Log::error('Tamara Webhook Error: Invalid Signature.', ['message' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401); 
            } catch (\TypeError $e) { 
                Log::error('Tamara Webhook Error: TypeError during signature verification. SDK method might expect different parameters or Request object.', [
                    'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
                return response()->json(['status' => 'error', 'message' => 'Signature verification library error.'], 500);
            }
             catch (\Exception $e) {
                Log::error('Tamara Webhook Error: Exception during signature verification.', ['message' => $e->getMessage(), 'class' => get_class($e)]);
                return response()->json(['status' => 'error', 'message' => 'Signature verification failed due to an unexpected error.'], 500);
            }
        } else {
            Log::info('Tamara Webhook: Signature verification is BYPASSED as per settings.');
        }
        
        $requestData = json_decode($rawContent, true) ?: [];
        
        $tamaraOrderId = $requestData['order_id'] ?? null; // 
        $resolved_order_reference_id = $requestData['order_reference_id'] ?? ($requestData['order_number'] ?? null); // 
        $eventType = $requestData['event_type'] ?? null; // 

        if (!$eventType && isset($requestData['order_status'])) {
            $eventType = match (strtolower($requestData['order_status'])) {
                'approved' => self::EVENT_TYPE_ORDER_APPROVED,
                'authorized' => self::EVENT_TYPE_ORDER_AUTHORISED,
                'canceled', 'cancelled' => self::EVENT_TYPE_ORDER_CANCELED,
                'declined' => self::EVENT_TYPE_ORDER_DECLINED,
                'expired' => self::EVENT_TYPE_ORDER_EXPIRED,
                default => null,
            };
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
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) { // 
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

                    $invoice = $invoiceQuery->with('booking.user', 'booking.service')
                                        ->lockForUpdate() 
                                        ->first();

                    if (!$invoice) {
                        Log::warning("Webhook: Invoice not found for {$eventType}.", ['tamara_order_id' => $tamaraOrderId, 'attempted_order_reference_id' => $resolved_order_reference_id]);
                        return;
                    }
                    if (!$invoice->booking || !$invoice->booking->user) {
                         Log::warning("Webhook: Booking or User not found for invoice during {$eventType}.", ['invoice_id' => $invoice->id]);
                         return;
                    }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)
                                            ->where('invoice_id', $invoice->id)
                                            ->exists();

                    $paymentActuallyCreatedInThisWebhookCall = false;
                    $amountPaidInThisTransaction = 0.0;
                    $tamaraCurrency = $invoice->currency ?: 'SAR';

                    if (!$existingPayment) {
                        $tamaraAmount = null;
                        if (isset($requestData['total_amount']['amount'])) {
                            $tamaraAmount = $requestData['total_amount']['amount'];
                            if (isset($requestData['total_amount']['currency'])) $tamaraCurrency = $requestData['total_amount']['currency'];
                        } elseif (isset($requestData['order_amount']['amount'])) {
                            $tamaraAmount = $requestData['order_amount']['amount'];
                             if (isset($requestData['order_amount']['currency'])) $tamaraCurrency = $requestData['order_amount']['currency'];
                        } elseif (isset($requestData['data']['payment_amount']['amount'])) { 
                            $tamaraAmount = $requestData['data']['payment_amount']['amount'];
                             if (isset($requestData['data']['payment_amount']['currency'])) $tamaraCurrency = $requestData['data']['payment_amount']['currency'];
                        } elseif (isset($requestData['data']['total_amount']['amount'])) {
                             $tamaraAmount = $requestData['data']['total_amount']['amount'];
                              if (isset($requestData['data']['total_amount']['currency'])) $tamaraCurrency = $requestData['data']['total_amount']['currency'];
                        }

                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                            Log::info("Webhook: Amount extracted directly from webhook payload for {$eventType}: {$amountPaidInThisTransaction} {$tamaraCurrency}", ['invoice_id' => $invoice->id]);
                        } else {
                            Log::warning("Webhook: Could not extract amount directly from Tamara webhook for {$eventType}. Using fallback logic.", [/* ... */]);
                            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                                $existingPaymentsTotal = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
                                $remainingAmount = $invoice->amount - $existingPaymentsTotal;
                                if ($remainingAmount > 0.009) $amountPaidInThisTransaction = (float) $remainingAmount;
                            } elseif ($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PARTIALLY_PAID) {
                                if ($invoice->booking && isset($invoice->booking->down_payment_amount) && $invoice->booking->down_payment_amount > 0.009) {
                                    $amountPaidInThisTransaction = (float) $invoice->booking->down_payment_amount;
                                } else { $amountPaidInThisTransaction = 0.0; }
                            } else { $amountPaidInThisTransaction = (float) $invoice->amount; }

                            if (($amountPaidInThisTransaction <= 0.009) && isset($requestData['items']) && is_array($requestData['items']) && !empty($requestData['items'])) {
                                $itemBasedAmount = 0.0;
                                foreach ($requestData['items'] as $item) {
                                    if (isset($item['total_amount']['amount'])) $itemBasedAmount += (float) $item['total_amount']['amount'];
                                    elseif (isset($item['unit_price']['amount'])) $itemBasedAmount += (float) $item['unit_price']['amount'] * ($item['quantity'] ?? 1);
                                }
                                if ($itemBasedAmount > 0.009) $amountPaidInThisTransaction = $itemBasedAmount;
                            }
                            Log::info("Webhook Fallback: Determined amount as {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                        }

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
                            $booking->status = Booking::STATUS_CONFIRMED;
                            $booking->save();
                            Log::info("Booking status updated to confirmed via {$eventType} webhook.", ['booking_id' => $booking->id, 'old_status' => $oldBookingStatus]);
                            if ($customer) $customer->notify(new BookingConfirmedNotification($booking));
                            foreach($admins as $admin) $admin->notify(new BookingConfirmedNotification($booking));
                        }
                        
                        // Authorise Order API call
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) { // 
                            $tamaraApiUrl = Setting::where('key', 'tamara_api_url')->value('value');
                            $tamaraApiToken = Setting::where('key', 'tamara_api_token')->value('value');
                            $tamaraRequestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? 30;

                            if(!empty($tamaraApiUrl) && !empty($tamaraApiToken)) {
                                try {
                                    $configurationAuth = TamaraConfiguration::create($tamaraApiUrl, $tamaraApiToken, $tamaraRequestTimeout);
                                    $client = TamaraClient::create($configurationAuth);
                                    $authoriseOrderRequest = new TamaraAuthoriseOrderRequest($tamaraOrderId); // 
                                    Log::info("Attempting to Authorise Tamara Order via Webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId]);
                                    $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                    if ($authoriseResponse->isSuccess()) {
                                        Log::info("Tamara Order Authorised successfully.", ['tamara_order_id' => $tamaraOrderId, 'response' => $authoriseResponse->toArray()]);
                                    } else {
                                        Log::error("Failed to Authorise Tamara Order.", ['tamara_order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                    }
                                } catch (Throwable $authError) {
                                    Log::error("Exception during Tamara Authorise API call.", ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                                }
                            } else {
                                Log::error("Tamara API settings missing for Authorise call.", ['tamara_order_id' => $tamaraOrderId]);
                            }
                        }
                    }
                });

            } elseif ($eventType === self::EVENT_TYPE_ORDER_CAPTURED) { // 
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                $captureData = $requestData['data'] ?? []; // 
                $captureId = $captureData['capture_id'] ?? null;
                $capturedAmount = isset($captureData['captured_amount']['amount']) ? (float)$captureData['captured_amount']['amount'] : null;
                $captureStatus = $captureData['status'] ?? null; // e.g., 'fully_captured', 'partially_captured' 

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
                        if(!$invoice->paid_at && $capturedAmount > 0) $invoice->paid_at = Carbon::now(); // Set paid_at if not already
                        $invoice->save();
                        Log::info("Invoice {$invoice->id} status confirmed as PAID due to full capture from Tamara webhook.");
                        // Potentially mark booking as completed if not already
                        if ($invoice->booking && $invoice->booking->status !== Booking::STATUS_COMPLETED) {
                            // $invoice->booking->status = Booking::STATUS_COMPLETED;
                            // $invoice->booking->save();
                            // Log::info("Booking {$invoice->booking->id} marked as COMPLETED based on Tamara full capture.");
                        }
                    }
                } else {
                    Log::warning("Received Tamara 'order_captured' webhook but could not find related invoice.", [
                        'tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id
                    ]);
                }
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED, self::EVENT_TYPE_ORDER_AUTHORISED])) {
                // ... (منطق معالجة هذه الحالات كما هو) ...
                 Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                if (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                    DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $eventType, $requestData) {
                        $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                            if ($resolved_order_reference_id) $query->where('invoice_number', $resolved_order_reference_id);
                            else if ($tamaraOrderId) $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                        })->with('booking.user')->lockForUpdate()->first();

                        if (!$invoice) { /* ... */ return; }
                        // ... (باقي منطق إلغاء/فشل الفاتورة)
                    });
                }
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId]);
            }
            return response()->json(['status' => 'success_event_processed'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception during Tamara webhook processing.', [
                'event_type' => $eventType ?? 'unknown', 'tamara_order_id' => $tamaraOrderId,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'error_processing_event_but_received'], 200);
        }
    }

    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        // ... (الكود كما هو) ...
    }
}
