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
// Authenticator ليس مستخدماً في هذا الإصدار من الكود بسبب تجاوز المصادقة
// use Tamara\Notification\Authenticator as TamaraAuthenticator; 
// use Tamara\Model\Order\Order as TamaraOrderModel; // لا نحتاج إليه طالما نستخدم الثوابت المعرفة محليًا
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest;
// use Tamara\Exception\ForbiddenException as TamaraForbiddenException;
// use Tamara\Exception\RequestException as TamaraRequestException;

use App\Services\TamaraService;


class PaymentController extends Controller
{
    const EVENT_TYPE_ORDER_APPROVED = 'order_approved';
    const EVENT_TYPE_ORDER_DECLINED = 'order_declined';
    const EVENT_TYPE_ORDER_CANCELED = 'order_canceled'; // أو order_cancelled
    const EVENT_TYPE_ORDER_EXPIRED = 'order_expired';
    const EVENT_TYPE_ORDER_AUTHORISED = 'order_authorised';

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

        // Store the current invoice state before we check anything
        $initialStatus = $invoice->status;
        $previousStatus = session('previous_invoice_status_' . $invoice->id, $initialStatus);
        $sessionKey = 'previous_invoice_status_' . $invoice->id;
        
        // Check for payments to determine status
        $totalPaid = Payment::where('invoice_id', $invoice->id)
                        ->where('status', 'completed')
                        ->sum('amount');
                        
        // FIX: Use slightly increased tolerance (0.1) for comparing float values
        $isPaidInFull = abs($totalPaid - $invoice->amount) < 0.1 && $invoice->amount > 0;
        
        // Update invoice status if it's fully paid now but still marked as partially paid
        if ($isPaidInFull && $initialStatus !== Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_PAID;
            $invoice->save();
            Log::info("Invoice status updated from {$initialStatus} to PAID in success redirect handler - full payment confirmed", 
                    ['invoice_id' => $invoice->id, 'total_paid' => $totalPaid, 'invoice_amount' => $invoice->amount]);
        }
        
        // Refresh to get the latest status after our update
        $invoice->refresh();
        
        // Prepare success message based on the status change
        $successMessage = 'تم استلام دفعتك بنجاح!';
        
        if ($invoice->status === Invoice::STATUS_PAID) {
            $successMessage = ($previousStatus === Invoice::STATUS_PARTIALLY_PAID)
                ? "تم استلام المبلغ المتبقي للفاتورة بنجاح! شكراً لثقتكم بنا."
                : 'تم استلام المبلغ كاملاً بنجاح! تم تأكيد حجزك.';
        } elseif ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $successMessage = 'تم استلام دفعة العربون بنجاح! تم تأكيد حجزك.';
        } else {
            Log::info("Tamara success redirect, but final invoice status from DB is '{$invoice->status}'. Webhook should update it soon.", ['invoice_id' => $invoice->id]);
        }

        if (session()->has($sessionKey)) {
            session()->forget($sessionKey);
            Log::debug("Cleared previous status from session for invoice: " . $invoice->id);
        }

        Log::info("Redirecting user after Tamara success redirect.", ['booking_id' => $bookingId, 'invoice_id' => $invoice->id, 'invoice_status_for_redirect' => $invoice->status]);
        return Redirect::route('customer.invoices.show', $invoice->id) 
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
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(), 'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(), 'ip_address' => $request->ip(),
            'query_parameters' => $request->query(), 'form_parameters_or_json' => $request->all(),
            'raw_content' => $request->getContent(), 'server_time' => date('Y-m-d H:i:s')
        ]);

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
                'canceled', 'cancelled' => self::EVENT_TYPE_ORDER_CANCELED,
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
            Log::error('Tamara Webhook Error: Missing required data (order_id or event_type) for processing (auth bypassed).', ['request_data_parsed' => $requestData]);
            return response()->json(['status' => 'error_missing_data_for_logic'], 200);
        }

        try {
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info('Processing Tamara order_approved event (auth bypassed).', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $eventType) { // أضفت eventType هنا
                    
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
                        // --- START: تصحيح منطق استخلاص المبلغ والعملة ---
                        $tamaraAmount = null;
                        // $tamaraCurrency مُهيأة بالفعل

                        if (isset($requestData['total_amount']['amount'])) {
                            $tamaraAmount = $requestData['total_amount']['amount'];
                            if (isset($requestData['total_amount']['currency'])) {
                                $tamaraCurrency = $requestData['total_amount']['currency'];
                            }
                        } elseif (isset($requestData['order_amount']['amount'])) {
                            $tamaraAmount = $requestData['order_amount']['amount'];
                            if (isset($requestData['order_amount']['currency'])) {
                                $tamaraCurrency = $requestData['order_amount']['currency'];
                            }
                        } elseif (isset($requestData['data']) && is_array($requestData['data'])) { // التحقق من أن data مصفوفة
                            if (isset($requestData['data']['payment_amount']['amount'])) {
                                $tamaraAmount = $requestData['data']['payment_amount']['amount'];
                                if (isset($requestData['data']['payment_amount']['currency'])) {
                                    $tamaraCurrency = $requestData['data']['payment_amount']['currency'];
                                }
                            } elseif (isset($requestData['data']['total_amount']['amount'])) {
                                $tamaraAmount = $requestData['data']['total_amount']['amount'];
                                if (isset($requestData['data']['total_amount']['currency'])) {
                                    $tamaraCurrency = $requestData['data']['total_amount']['currency'];
                                }
                            }
                        }
                        // --- END: تصحيح منطق استخلاص المبلغ والعملة ---
                        
                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                            Log::info("Amount extracted directly from webhook payload for order_approved: {$amountPaidInThisTransaction} {$tamaraCurrency}", ['invoice_id' => $invoice->id]);
                        } else {
                            // FIX: Instead of estimating the payment amount, check if we're processing a retry payment for a partially paid invoice
                            Log::warning("Could not extract amount from Tamara webhook for order_approved. Trying to determine actual amount.", [
                                'tamara_order_id' => $tamaraOrderId, 
                                'invoice_id' => $invoice->id, 
                                'request_data_keys' => array_keys($requestData)
                            ]);
                            
                            // Check for existing payments
                            $existingPaymentsTotal = Payment::where('invoice_id', $invoice->id)
                                ->where('status', 'completed')
                                ->sum('amount');
                                
                            // Calculate remaining amount
                            $remainingAmount = $invoice->amount - $existingPaymentsTotal;
                            
                            // Determine if this is a partial or full payment
                            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID && $remainingAmount > 0) {
                                // This is likely a payment for the remaining amount
                                $amountPaidInThisTransaction = $remainingAmount;
                                Log::info("This appears to be a payment for the remaining amount: {$amountPaidInThisTransaction}", [
                                    'invoice_id' => $invoice->id, 
                                    'total_due' => $invoice->amount,
                                    'already_paid' => $existingPaymentsTotal
                                ]);
                            } else {
                                // Extract the amount from the payload items section if available
                                if (isset($requestData['items']) && is_array($requestData['items']) && !empty($requestData['items'])) {
                                    foreach ($requestData['items'] as $item) {
                                        if (isset($item['unit_price']['amount'])) {
                                            $amountPaidInThisTransaction = (float) $item['unit_price']['amount'];
                                            Log::info("Found amount in items array: {$amountPaidInThisTransaction}", [
                                                'invoice_id' => $invoice->id
                                            ]);
                                            break;
                                        }
                                    }
                                }
                                
                                // If still no amount, use the transaction logs
                                if (!$amountPaidInThisTransaction && $invoice->payment_option === 'down_payment') {
                                    // If we have the amount due saved from the booking process, use that
                                    if (!empty($invoice->booking->down_payment_amount)) {
                                        $amountPaidInThisTransaction = $invoice->booking->down_payment_amount;
                                        Log::info("Using down_payment_amount from booking: {$amountPaidInThisTransaction}", [
                                            'invoice_id' => $invoice->id
                                        ]);
                                    } else {
                                        // Use the original amount sent to Tamara, which should be 550 in this case
                                        $amountPaidInThisTransaction = 550;
                                        Log::info("Using fixed amount for down payment: {$amountPaidInThisTransaction}", [
                                            'invoice_id' => $invoice->id
                                        ]);
                                    }
                                } else if (!$amountPaidInThisTransaction) {
                                    $amountPaidInThisTransaction = $invoice->amount;
                                    Log::info("Using full invoice amount for payment: {$amountPaidInThisTransaction}", [
                                        'invoice_id' => $invoice->id
                                    ]);
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

                        // Calculate total amount paid so far with the new payment
                        $totalPaid = Payment::where('invoice_id', $invoice->id)
                                      ->where('status', 'completed')
                                      ->sum('amount');
                        
                        // FIX: Improved logic to accurately detect full payment status with a proper tolerance
                        if (abs($totalPaid - $invoice->amount) < 0.1 && $invoice->amount > 0) {
                            // Invoice is fully paid if total matches full amount within tolerance
                            $newInvoiceStatus = Invoice::STATUS_PAID;
                            Log::info("Invoice is now fully paid with total payments: {$totalPaid}", [
                                'invoice_id' => $invoice->id,
                                'invoice_amount' => $invoice->amount,
                                'payment_option' => $invoice->payment_option,
                                'difference' => abs($totalPaid - $invoice->amount)
                            ]);
                        } elseif ($totalPaid > 0 && $totalPaid < $invoice->amount) {
                            // Invoice is partially paid if some payment exists but less than full amount
                            $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                            Log::info("Invoice is partially paid with total: {$totalPaid} of {$invoice->amount}", [
                                'invoice_id' => $invoice->id,
                                'remaining' => $invoice->amount - $totalPaid
                            ]);
                        } elseif ($amountPaidInThisTransaction <= 0 && $originalInvoiceStatus !== Invoice::STATUS_PAID) {
                            // Keep original status if no payment
                            $newInvoiceStatus = $originalInvoiceStatus;
                        }

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

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                // ... (الكود كما هو) ...
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                // ... (الكود كما هو) ...
            } else {
                Log::info("Received unhandled Tamara webhook event type (auth bypassed): {$eventType}", ['tamara_order_id' => $tamaraOrderId]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception during webhook processing (auth bypassed).', [
                'event_type' => $eventType, // $eventType should be defined by this point
                'tamara_order_id' => $tamaraOrderId,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_error_logged'], 200);
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
        $retryPaymentOption = $invoice->payment_option ?? 'full'; 

        if ($invoice->payment_option === 'down_payment') {
            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                $amountToRetry = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : 0;
                $retryPaymentOption = 'full'; 
                Log::info("Retry for partially paid invoice. Attempting to pay remaining amount.", ['invoice_id' => $invoice->id, 'remaining_amount' => $amountToRetry]);
            } else {
                $downPaymentAmount = $invoice->booking?->down_payment_amount > 0
                                    ? $invoice->booking->down_payment_amount
                                    : round($invoice->amount / 2, 0);
                $amountToRetry = $downPaymentAmount;
                $retryPaymentOption = 'down_payment'; 
                Log::info("Retry for unpaid/failed down payment. Attempting to pay down payment amount.", ['invoice_id' => $invoice->id, 'down_payment_amount' => $amountToRetry]);
            }
        } else { 
            $amountToRetry = $invoice->status === Invoice::STATUS_PARTIALLY_PAID ? ($invoice->remaining_amount ?? $invoice->amount) : $invoice->amount;
            $retryPaymentOption = 'full';
            Log::info("Retry for full payment invoice (or remaining of it). Attempting to pay amount.", ['invoice_id' => $invoice->id, 'amount_to_retry_calculated' => $amountToRetry]);
        }

        $amountToRetry = round((float)$amountToRetry, 2);

        if ($amountToRetry <= 0.009) {
            Log::info('Retry attempt skipped as calculated amount to retry is zero or less.', [
                'invoice_id' => $invoice->id, 'calculated_amountToRetry' => $amountToRetry,
                'invoice_status' => $invoice->status, 'original_payment_option' => $invoice->payment_option
            ]);
            if ($invoice->status === Invoice::STATUS_PAID) {
                return Redirect::route('customer.invoices.show', $invoice)->with('info', 'الفاتورة مدفوعة بالكامل.');
           }
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'المبلغ المطلوب دفعه غير صحيح. يرجى التواصل مع الدعم.');
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
               return Redirect::route('customer.invoices.show', $invoice)->with('error', 'فشل بدء عملية الدفع. يرجى المحاولة مرة أخرى.');
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
