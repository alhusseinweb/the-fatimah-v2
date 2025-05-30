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

    protected TamaraService $tamaraService;

    public function __construct(TamaraService $tamaraService)
    {
        $this->tamaraService = $tamaraService;
    }

    // ... دوال handleTamaraSuccess, handleTamaraFailure, handleTamaraCancel تبقى كما هي ...
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
        $rawContent = $request->getContent(); // الحصول على المحتوى الخام مرة واحدة
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(),
            'raw_content_sample' => Str::limit($rawContent, 1000), // زيادة حجم العينة المسجلة
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
                // --- MODIFICATION START: Use correct parameters for Tamara SDK's authenticate method ---
                // بناءً على توثيق Tamara PHP SDK، دالة authenticate تتوقع (string $requestBody, string $signature)
                // رسالة الخطأ السابقة كانت مضللة نوعاً ما.
                $signature = $request->header('Tamara-Signature'); // أو اسم الهيدر الصحيح الذي ترسله تمارا
                if (empty($signature)) {
                    Log::error('Tamara Webhook Error: Missing Tamara-Signature header.');
                    return response()->json(['status' => 'error', 'message' => 'Missing signature header'], 400); // Bad Request
                }
                $authenticator->authenticate($rawContent, $signature); 
                // --- MODIFICATION END ---
                Log::info('Tamara Webhook: Signature verified successfully.');
            } catch (InvalidSignatureException $e) {
                Log::error('Tamara Webhook Error: Invalid Signature.', ['message' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401); // Unauthorized
            } catch (\Exception $e) {
                Log::error('Tamara Webhook Error: Exception during signature verification.', ['message' => $e->getMessage(), 'class' => get_class($e)]);
                return response()->json(['status' => 'error', 'message' => 'Signature verification failed due to an unexpected error.'], 500);
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
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $eventType) { 
                    
                    $invoiceQuery = Invoice::query();
                    if ($resolved_order_reference_id) {
                        $invoiceQuery->where('invoice_number', $resolved_order_reference_id);
                    }
                    // في حالة عدم وجود order_reference_id, يمكن محاولة الربط عبر tamaraOrderId إذا تم تخزينه سابقاً
                    // كـ payment_gateway_ref عند بدء عملية الدفع
                    elseif ($tamaraOrderId) {
                        $invoiceQuery->orWhere('payment_gateway_ref', $tamaraOrderId);
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
                        // استخلاص المبلغ من حمولة الويب هوك
                        if (isset($requestData['total_amount']['amount'])) {
                            $tamaraAmount = $requestData['total_amount']['amount'];
                            if (isset($requestData['total_amount']['currency'])) $tamaraCurrency = $requestData['total_amount']['currency'];
                        } elseif (isset($requestData['order_amount']['amount'])) {
                            $tamaraAmount = $requestData['order_amount']['amount'];
                             if (isset($requestData['order_amount']['currency'])) $tamaraCurrency = $requestData['order_amount']['currency'];
                        } elseif (isset($requestData['data']['payment_amount']['amount'])) { // من بنية بيانات تمارا أحياناً
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
                            Log::warning("Webhook: Could not extract amount directly from Tamara webhook for {$eventType}. Using fallback logic.", [
                                'tamara_order_id' => $tamaraOrderId, 'invoice_id' => $invoice->id, 
                                'current_invoice_status' => $invoice->status,
                                'invoice_payment_option' => $invoice->payment_option,
                                'request_data_keys' => array_keys($requestData)
                            ]);
                            
                            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                                $existingPaymentsTotal = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
                                $remainingAmount = $invoice->amount - $existingPaymentsTotal;
                                if ($remainingAmount > 0.009) $amountPaidInThisTransaction = (float) $remainingAmount;
                                Log::info("Webhook Fallback: Using calculated remaining for partially paid: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                            } elseif ($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PARTIALLY_PAID) {
                                if ($invoice->booking && isset($invoice->booking->down_payment_amount) && $invoice->booking->down_payment_amount > 0.009) {
                                    $amountPaidInThisTransaction = (float) $invoice->booking->down_payment_amount;
                                    Log::info("Webhook Fallback: Using down_payment_amount from booking: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                                } else {
                                    Log::error("Webhook Critical Fallback: Could not determine down_payment_amount for invoice {$invoice->id}.", ['booking_id' => $invoice->booking_id ?? null]);
                                    $amountPaidInThisTransaction = 0.0;
                                }
                            } else { 
                                $amountPaidInThisTransaction = (float) $invoice->amount;
                                Log::info("Webhook Fallback: Using full invoice amount: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                            }

                            if (($amountPaidInThisTransaction <= 0.009) && isset($requestData['items']) && is_array($requestData['items']) && !empty($requestData['items'])) {
                                $itemBasedAmount = 0.0;
                                foreach ($requestData['items'] as $item) {
                                    if (isset($item['total_amount']['amount'])) $itemBasedAmount += (float) $item['total_amount']['amount'];
                                    elseif (isset($item['unit_price']['amount'])) $itemBasedAmount += (float) $item['unit_price']['amount'] * ($item['quantity'] ?? 1);
                                }
                                if ($itemBasedAmount > 0.009) {
                                    $amountPaidInThisTransaction = $itemBasedAmount;
                                    Log::info("Webhook Fallback: Using amount from items array: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                                }
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
                        
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            $tamaraApiUrl = Setting::where('key', 'tamara_api_url')->value('value');
                            $tamaraApiToken = Setting::where('key', 'tamara_api_token')->value('value');
                            $tamaraRequestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? 30;

                            if(!empty($tamaraApiUrl) && !empty($tamaraApiToken)) {
                                try {
                                    $configurationAuth = TamaraConfiguration::create($tamaraApiUrl, $tamaraApiToken, $tamaraRequestTimeout);
                                    $client = TamaraClient::create($configurationAuth);
                                    $authoriseOrderRequest = new TamaraAuthoriseOrderRequest($tamaraOrderId);
                                    Log::debug("Attempting Tamara Authorise API via webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId]);
                                    $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                    if ($authoriseResponse->isSuccess()) {
                                        Log::info("Tamara Authorise API successful via webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId, 'response_order_id' => $authoriseResponse->getOrderId(), 'response_status' => $authoriseResponse->getOrderStatus()]);
                                    } else {
                                        Log::error("Tamara Authorise API failed via webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                    }
                                } catch (Throwable $authError) {
                                    Log::error("Exception during Tamara Authorise API call via webhook ({$eventType}).", ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                                }
                            } else {
                                Log::error("Tamara Authorise API config missing (URL or Token from DB settings). Skipping Authorise call for order {$tamaraOrderId}.");
                            }
                        }
                    }
                });

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                // يمكنك إضافة منطق هنا إذا لزم الأمر، مثلاً تحديث حالة الفاتورة إلى "قيد المعالجة"
                // حالياً، الاعتماد على order_approved لتحديث كل شيء
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                 Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $eventType, $requestData) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) $query->where('invoice_number', $resolved_order_reference_id);
                        else if ($tamaraOrderId) $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->with('booking.user')
                    ->lockForUpdate()->first();

                    if (!$invoice) {
                        Log::warning("Webhook: Invoice not found for {$eventType}.", ['tamara_order_id' => $tamaraOrderId, 'ref_id' => $resolved_order_reference_id]);
                        return;
                    }

                    $newInvoiceStatus = '';
                    $reason = "Tamara Event: {$eventType}"; 
                     if (isset($requestData['data']['failure_reason'])) $reason = $requestData['data']['failure_reason'];
                     elseif (isset($requestData['reason'])) $reason = $requestData['reason'];

                    if ($eventType === self::EVENT_TYPE_ORDER_DECLINED) $newInvoiceStatus = Invoice::STATUS_FAILED;
                    elseif ($eventType === self::EVENT_TYPE_ORDER_CANCELED) $newInvoiceStatus = Invoice::STATUS_CANCELLED;
                    elseif ($eventType === self::EVENT_TYPE_ORDER_EXPIRED) $newInvoiceStatus = Invoice::STATUS_EXPIRED;

                    if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                        if ($invoice->status !== $newInvoiceStatus && !empty($newInvoiceStatus)) {
                           $invoice->status = $newInvoiceStatus;
                           $invoice->save();
                           Log::info("Invoice status updated to '{$newInvoiceStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);
                        }

                        $customer = $invoice->booking?->user;
                        if ($customer) $customer->notify(new PaymentFailedNotification($invoice, $reason));
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) $admin->notify(new PaymentFailedNotification($invoice, $reason));
                    } else {
                        Log::info("Invoice {$invoice->id} already {$invoice->status}. No status change from Tamara {$eventType} webhook.", ['tamara_order_id' => $tamaraOrderId]);
                    }
                });
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
        $retryPaymentOption = 'full'; // افتراضياً، نحاول دفع الفاتورة بالكامل عند إعادة المحاولة

        if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $amountToRetry = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : 0.0;
            // $retryPaymentOption يبقى 'full' لأننا ندفع المتبقي من الإجمالي
        } elseif ($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PAID) { 
            // إذا كانت الفاتورة أصلاً عربون ولم تُدفع جزئياً بعد (يعني فشل دفع العربون الأول)
            $amountToRetry = $invoice->booking?->down_payment_amount > 0.009
                                ? $invoice->booking->down_payment_amount
                                : ($invoice->amount > 0.009 ? round($invoice->amount / 2, 2) : 0.0); // احتياطي إذا لم يُسجل down_payment_amount
            $retryPaymentOption = 'down_payment'; // نرسل لتمارا كـ "دفعة أولى"
        } else { // حالات أخرى (مثل UNPAID لدفعة كاملة)
            $amountToRetry = $invoice->amount; // ندفع المبلغ الإجمالي للفاتورة
        }
        Log::info("Retry Tamara: Determined amount to retry: {$amountToRetry} with option: {$retryPaymentOption}", ['invoice_id' => $invoice->id]);


        $amountToRetry = round((float)$amountToRetry, 2);

        if ($amountToRetry <= 0.009) {
            Log::info('Retry attempt skipped as calculated amount to retry is zero or less.', [
                'invoice_id' => $invoice->id, 'calculated_amountToRetry' => $amountToRetry,
                'invoice_status' => $invoice->status, 'original_payment_option' => $invoice->payment_option
            ]);
            if ($invoice->status === Invoice::STATUS_PAID) {
                return Redirect::route('customer.invoices.show', $invoice)->with('info', 'الفاتورة مدفوعة بالكامل.');
           }
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'المبلغ المطلوب دفعه غير صحيح أو أن الفاتورة لا تتطلب دفعة حالياً. يرجى التواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.');
       }

       Log::debug('Proceeding to initiate checkout for retry.', ['invoice_id' => $invoice->id, 'amount_to_retry' => $amountToRetry, 'final_retry_payment_option_to_tamara' => $retryPaymentOption]);

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
           Log::error('Exception during Tamara retry payment initiation.', [
               'invoice_id' => $invoice->id, 
               'error' => $e->getMessage(), 
               'class' => get_class($e),
               'file' => $e->getFile(), 
               'line' => $e->getLine(),
               'trace' => Str::limit($e->getTraceAsString(), 1500)
           ]);
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'حدث خطأ غير متوقع أثناء محاولة الدفع. يرجى المحاولة مرة أخرى.');
       }
   }

}
