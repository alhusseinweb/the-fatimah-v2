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
// use Tamara\Model\Order\Order as TamaraOrderModel; 
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest;
// use Tamara\Exception\ForbiddenException as TamaraForbiddenException;
// use Tamara\Exception\RequestException as TamaraRequestException;

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

    public function handleTamaraSuccess(Request $request, Invoice $invoice): RedirectResponse
    {
        Log::info("Tamara success redirect received for Invoice ID: {$invoice->id}");
        $bookingId = $invoice->booking_id;

        if (auth()->check() && $invoice->booking?->user_id !== auth()->id()) {
            Log::warning("Unauthorized access attempt on Tamara success URL.", ['invoice_id' => $invoice->id, 'auth_user_id' => auth()->id()]);
            return Redirect::route('home')->with('error', 'حدث خطأ ما.');
        }

        $invoice->refresh(); 
        $successMessage = 'تم استلام دفعتك بنجاح!';
        
        // --- تعديل رسالة النجاح لتعكس الحالة الفعلية للفاتورة ---
        if ($invoice->status === Invoice::STATUS_PAID) {
            $successMessage = 'تم دفع كامل الفاتورة بنجاح! تم تأكيد حجزك.';
        } elseif ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $remainingAmount = $invoice->remaining_amount; // افترض وجود هذا الـ accessor
            // إذا كان المتبقي صفرًا بعد تحديث الـ webhook، فهذا يعني أنها دفعت بالكامل
            if (abs($remainingAmount) < 0.01) { 
                 $successMessage = 'تم دفع كامل الفاتورة بنجاح! تم تأكيد حجزك.';
            } else {
                 $successMessage = 'تم استلام دفعة العربون بنجاح! المبلغ المتبقي: ' . number_format($remainingAmount, 2) . ' ' . ($invoice->currency ?: 'SAR') . '. تم تأكيد حجزك مبدئيًا.';
            }
        } else { 
            Log::info("Tamara success redirect, but invoice status from DB is '{$invoice->status}'. Webhook should update it. Using default success message.", ['invoice_id' => $invoice->id]);
        }
        // --- نهاية تعديل رسالة النجاح ---

        $sessionKey = 'previous_invoice_status_' . $invoice->id;
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
            Log::error('Tamara Webhook Error: Missing required data for processing (auth bypassed).', ['request_data_parsed' => $requestData]);
            return response()->json(['status' => 'error_missing_data_for_logic'], 200);
        }

        try {
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info('Processing Tamara order_approved event (auth bypassed).', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);

                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $eventType) {
                    
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) { $query->where('invoice_number', $resolved_order_reference_id); }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })->with('booking.user', 'booking.service')->lockForUpdate()->first();

                    if (!$invoice) { Log::warning('Webhook: Invoice not found for order_approved.', ['tamara_order_id' => $tamaraOrderId, 'attempted_order_reference_id' => $resolved_order_reference_id]); return; }
                    if (!$invoice->booking || !$invoice->booking->user) { Log::warning('Webhook: Booking or User not found for invoice.', ['invoice_id' => $invoice->id]); return; }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)->where('invoice_id', $invoice->id)->exists();
                    $paymentActuallyCreatedInThisWebhookCall = false;
                    $amountPaidInThisTransaction = 0;
                    $tamaraCurrency = $invoice->currency ?: 'SAR';

                    if (!$existingPayment) {
                        $tamaraAmount = $requestData['total_amount']['amount'] ?? ($requestData['order_amount']['amount'] ?? (isset($requestData['data']['payment_amount']['amount']) ? $requestData['data']['payment_amount']['amount'] : (isset($requestData['data']['total_amount']['amount']) ? $requestData['data']['total_amount']['amount'] : null)));
                        $tamaraCurrency = $requestData['total_amount']['currency'] ?? ($requestData['order_amount']['currency'] ?? (isset($requestData['data']['payment_amount']['currency']) ? $requestData['data']['payment_amount']['currency'] : (isset($requestData['data']['total_amount']['currency']) ? $requestData['data']['total_amount']['currency'] : $tamaraCurrency)));
                        
                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                        } else {
                            Log::warning("Could not extract amount from Tamara webhook for order_approved. Estimating.", ['invoice_id' => $invoice->id]);
                            if ($invoice->payment_option === 'down_payment' && !in_array($invoice->status, [Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID])) {
                                $amountPaidInThisTransaction = $invoice->booking->down_payment_amount > 0 ? $invoice->booking->down_payment_amount : round($invoice->amount / 2, 0);
                            } else { 
                                $amountPaidInThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                            }
                        }

                        if ($amountPaidInThisTransaction > 0.009) {
                            Payment::create([
                                'invoice_id' => $invoice->id, 'transaction_id' => $tamaraOrderId,
                                'amount' => $amountPaidInThisTransaction, 'currency' => $tamaraCurrency,
                                'status' => 'completed', 'payment_gateway' => 'tamara',
                                'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => $eventType, 'webhook_payload_received' => $requestData]) ?: null,
                            ]);
                            Log::info("Payment record CREATED via webhook for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);
                            $paymentActuallyCreatedInThisWebhookCall = true;
                        } else { Log::info("Skipping payment creation as amount is zero or less for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]); }
                    } else { Log::warning("Duplicate payment webhook (order_approved) or payment already recorded. Ignored for order_id.", ['invoice_id' => $invoice->id, 'tamara_order_id' => $tamaraOrderId]); }

                    if ($paymentActuallyCreatedInThisWebhookCall) {
                        $invoice->refresh(); // تحديث الفاتورة لجلب أحدث الدفعات المرتبطة بها
                        $totalPaidForInvoice = $invoice->payments()->where('status', Payment::STATUS_COMPLETED)->sum('amount');
                        
                        $originalInvoiceStatusForLog = $invoice->status; // حالة الفاتورة قبل هذا التحديث المنطقي
                        $newInvoiceStatus = $originalInvoiceStatusForLog;

                        if (abs($totalPaidForInvoice - $invoice->amount) < 0.01 && $invoice->amount > 0) {
                            $newInvoiceStatus = Invoice::STATUS_PAID;
                        } elseif ($totalPaidForInvoice > 0 && $totalPaidForInvoice < $invoice->amount) {
                            $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                        } elseif ($totalPaidForInvoice <= 0 && $invoice->amount > 0 && $originalInvoiceStatusForLog !== Invoice::STATUS_PAID) {
                            $newInvoiceStatus = Invoice::STATUS_UNPAID; // لا يجب أن يحدث إذا تم إنشاء دفعة
                        }
                        
                        Log::info("Webhook: Invoice status determination after new payment.", [
                            'invoice_id' => $invoice->id, 'status_before_logic' => $originalInvoiceStatusForLog,
                            'payment_option_original' => $invoice->payment_option, 'amount_just_paid' => $amountPaidInThisTransaction,
                            'total_paid_calculated' => $totalPaidForInvoice, 'invoice_total' => $invoice->amount,
                            'determined_new_status' => $newInvoiceStatus
                        ]);

                        if ($newInvoiceStatus !== $originalInvoiceStatusForLog || ($newInvoiceStatus === Invoice::STATUS_PAID && $invoice->paid_at === null) || ($newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID && $invoice->paid_at === null)) {
                            $invoice->status = $newInvoiceStatus;
                            if (($newInvoiceStatus === Invoice::STATUS_PAID || $newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) && $invoice->paid_at === null) {
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status explicitly updated to '{$newInvoiceStatus}' via order_approved webhook.", ['invoice_id' => $invoice->id]);
                        }

                        // ... (بقية منطق الإشعارات واستدعاء Authorise API كما هو)
                        $customer = $invoice->booking->user;
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
                            // ... (تحديث حالة الحجز وإرسال BookingConfirmedNotification)
                        }
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            // ... (استدعاء Authorise API)
                        }
                    }
                });

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                // ... (الكود كما هو)
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                // ... (الكود كما هو)
            } else {
                // ... (الكود كما هو)
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception during webhook processing (auth bypassed).', [
                'event_type' => $eventType, 'tamara_order_id' => $tamaraOrderId,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_error_logged'], 200);
        }
    }

    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        // ... (الكود كما هو، مع التعديلات السابقة لتحديد المبلغ وخيار الدفع بشكل صحيح) ...
    }
}
