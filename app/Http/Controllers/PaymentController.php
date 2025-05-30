<?php

// المسار: app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Setting; // <-- *** إضافة: استيراد موديل Setting ***
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

// --- MODIFICATION START: Tamara SDK specific imports ---
use Tamara\Client as TamaraClient; // قد تكون موجودة بالفعل
use Tamara\Configuration as TamaraConfiguration; // قد تكون موجودة بالفعل
use Tamara\Notification\Authenticator as TamaraAuthenticator; // <-- *** إضافة: لاستخدامه في التحقق ***
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest; // قد تكون موجودة بالفعل
use Tamara\Exception\InvalidSignatureException; // <-- *** إضافة: لمعالجة خطأ التوقيع ***
// --- MODIFICATION END ---

use App\Services\TamaraService; // قد تكون موجودة بالفعل


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
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(), 'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(), 'ip_address' => $request->ip(),
            'query_parameters' => $request->query(), 'form_parameters_or_json' => $request->all(),
            'raw_content_sample' => Str::limit($request->getContent(), 500), // سجل عينة أكبر من المحتوى الخام
            'server_time' => date('Y-m-d H:i:s')
        ]);

        // --- MODIFICATION START: Fetch Tamara settings and handle webhook verification ---
        $notificationToken = Setting::where('key', 'tamara_notification_token')->value('value');
        $bypassVerification = filter_var(
            Setting::where('key', 'tamara_webhook_verification_bypass')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$bypassVerification) {
            if (empty($notificationToken)) {
                Log::error('Tamara Webhook Error: Notification Token for Tamara is not set in DB settings. Cannot verify signature.');
                // يمكنك إرجاع 400 Bad Request أو 500 Server Error هنا لتمارا
                return response()->json(['status' => 'error', 'message' => 'Notification token not configured'], 500);
            }
            try {
                $authenticator = new TamaraAuthenticator($notificationToken);
                $authenticator->authenticate($request->getContent(), $request->header('Tamara-Signature')); // تأكد من اسم الهيدر الصحيح لـ Tamara
                Log::info('Tamara Webhook: Signature verified successfully.');
            } catch (InvalidSignatureException $e) {
                Log::error('Tamara Webhook Error: Invalid Signature.', ['message' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401); // Unauthorized
            } catch (\Exception $e) {
                Log::error('Tamara Webhook Error: Exception during signature verification.', ['message' => $e->getMessage()]);
                return response()->json(['status' => 'error', 'message' => 'Signature verification failed'], 500);
            }
        } else {
            Log::info('Tamara Webhook: Signature verification is BYPASSED as per settings.');
        }
        // --- MODIFICATION END ---

        $rawContent = $request->getContent(); // احصل عليه مرة أخرى إذا كنت قد استهلكته في Authenticator
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
            // 'raw_content_sample' => Str::limit($rawContent, 200) // تم تسجيله سابقاً بشكل كامل
        ]);
        
        if (empty($tamaraOrderId) || empty($eventType)) {
            Log::error('Tamara Webhook Error: Missing required data (order_id or event_type) for processing.', ['request_data_parsed' => $requestData]);
            // إرجاع 200 لتمارا حتى لو كانت البيانات ناقصة لمنع الإعادة المفرطة، لكن سجل الخطأ.
            return response()->json(['status' => 'error_missing_data_for_logic_but_received'], 200);
        }

        try {
            if ($eventType === self::EVENT_TYPE_ORDER_APPROVED) {
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $requestData, $eventType) { 
                    
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        // إضافة شرط للبحث بـ payment_gateway_ref إذا كان order_reference_id غير موجود أو لم يطابق
                        // ولكن يجب أن يكون order_reference_id (الذي هو رقم فاتورتنا) كافياً
                        // $query->orWhere('payment_gateway_ref', $tamaraOrderId); // هذا قد يسبب تضارب إذا كان الطلب لتمارا مختلف لنفس الفاتورة
                    })
                    ->when(empty($resolved_order_reference_id) && !empty($tamaraOrderId), function ($query) use ($tamaraOrderId) {
                        // إذا لم يكن هناك order_reference_id (رقم فاتورتنا)، حاول البحث بـ tamaraOrderId في payment_gateway_ref
                        // هذا أقل مثالية ولكنه قد يكون مفيداً كاحتياطي إذا لم ترسل تمارا order_reference_id دائماً
                        return $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->with('booking.user', 'booking.service')
                    ->lockForUpdate() 
                    ->first();

                    if (!$invoice) {
                        Log::warning("Webhook: Invoice not found for {$eventType}.", ['tamara_order_id' => $tamaraOrderId, 'attempted_order_reference_id' => $resolved_order_reference_id]);
                        return; // الخروج من الـ transaction
                    }
                    if (!$invoice->booking || !$invoice->booking->user) {
                         Log::warning("Webhook: Booking or User not found for invoice during {$eventType}.", ['invoice_id' => $invoice->id]);
                         return; // الخروج من الـ transaction
                    }

                    // التحقق مما إذا كانت هذه الدفعة (بمعرف طلب تمارا) قد سُجلت بالفعل لهذه الفاتورة
                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId) // tamaraOrderId هو معرف طلب تمارا
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
                        } elseif (isset($requestData['data']) && is_array($requestData['data'])) {
                            if (isset($requestData['data']['payment_amount']['amount'])) {
                                $tamaraAmount = $requestData['data']['payment_amount']['amount'];
                                if (isset($requestData['data']['payment_amount']['currency'])) $tamaraCurrency = $requestData['data']['payment_amount']['currency'];
                            } elseif (isset($requestData['data']['total_amount']['amount'])) {
                                $tamaraAmount = $requestData['data']['total_amount']['amount'];
                                if (isset($requestData['data']['total_amount']['currency'])) $tamaraCurrency = $requestData['data']['total_amount']['currency'];
                            }
                        }

                        if ($tamaraAmount !== null) {
                            $amountPaidInThisTransaction = (float) $tamaraAmount;
                            Log::info("Webhook: Amount extracted directly from webhook payload for {$eventType}: {$amountPaidInThisTransaction} {$tamaraCurrency}", ['invoice_id' => $invoice->id]);
                        } else {
                            Log::warning("Webhook: Could not extract amount directly from Tamara webhook for {$eventType}. Using fallback logic.", [/* ... */]);
                            // --- المنطق الاحتياطي لتحديد المبلغ ---
                            if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
                                $existingPaymentsTotal = Payment::where('invoice_id', $invoice->id)->where('status', 'completed')->sum('amount');
                                $remainingAmount = $invoice->amount - $existingPaymentsTotal;
                                if ($remainingAmount > 0.009) $amountPaidInThisTransaction = (float) $remainingAmount;
                                Log::info("Webhook Fallback: Using calculated remaining for partially paid: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                            } elseif ($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PARTIALLY_PAID) { // الدفعة الأولى للعربون
                                if ($invoice->booking && isset($invoice->booking->down_payment_amount) && $invoice->booking->down_payment_amount > 0.009) {
                                    $amountPaidInThisTransaction = (float) $invoice->booking->down_payment_amount;
                                    Log::info("Webhook Fallback: Using down_payment_amount from booking: {$amountPaidInThisTransaction}", ['invoice_id' => $invoice->id]);
                                } else {
                                    Log::error("Webhook Critical Fallback: Could not determine down_payment_amount for invoice {$invoice->id}.", [/* ... */]);
                                    $amountPaidInThisTransaction = 0.0;
                                }
                            } else { // دفعة كاملة أو حالة أخرى
                                $amountPaidInThisTransaction = (float) $invoice->amount; // افترض المبلغ الإجمالي للفاتورة
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
                            // --- نهاية المنطق الاحتياطي ---
                        }

                        if ($amountPaidInThisTransaction > 0.009) {
                            Payment::create([
                                'invoice_id' => $invoice->id,
                                'transaction_id' => $tamaraOrderId,
                                'amount' => $amountPaidInThisTransaction,
                                'currency' => $tamaraCurrency,
                                'status' => 'completed',
                                'payment_gateway' => 'tamara',
                                'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => $eventType, 'webhook_payload_received' => Str::limit(json_encode($requestData),1500)]) ?: null,
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
                        // ... (منطق تحديث حالة الفاتورة والحجز وإرسال الإشعارات كما هو في ردك السابق) ...
                        // هذا الجزء مهم جداً ويجب أن يكون مطابقاً لما يعمل لديك بشكل جيد
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
                            Log::info("PaymentSuccessNotification queued for CUSTOMER {$customer->id} via {$eventType} webhook.");
                        }
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) {
                            $admin->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for ADMIN {$admin->id} via {$eventType} webhook.");
                        }

                        $booking = $invoice->booking;
                        if ($booking && $booking->status !== Booking::STATUS_CONFIRMED && in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            $oldBookingStatus = $booking->status;
                            $booking->status = Booking::STATUS_CONFIRMED;
                            $booking->save();
                            Log::info("Booking status updated to confirmed via {$eventType} webhook.", ['booking_id' => $booking->id, 'old_status' => $oldBookingStatus]);
                            if ($customer) {
                                $customer->notify(new BookingConfirmedNotification($booking));
                                Log::info("BookingConfirmedNotification queued for CUSTOMER {$customer->id} via {$eventType} webhook.");
                            }
                            foreach($admins as $admin) {
                                $admin->notify(new BookingConfirmedNotification($booking));
                                Log::info("BookingConfirmedNotification queued for ADMIN {$admin->id} via {$eventType} webhook.");
                            }
                        }
                        
                        // Authorise order with Tamara
                        if (in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                            // --- MODIFICATION START: Fetch Tamara API URL and Token from DB settings ---
                            $tamaraApiUrl = Setting::where('key', 'tamara_api_url')->value('value');
                            $tamaraApiToken = Setting::where('key', 'tamara_api_token')->value('value');
                            $tamaraRequestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? 30;
                            // --- MODIFICATION END ---

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
                    } // نهاية if ($paymentActuallyCreatedInThisWebhookCall)
                }); // نهاية DB::transaction لـ order_approved

            } elseif ($eventType === self::EVENT_TYPE_ORDER_AUTHORISED) {
                // ... (منطق order_authorised كما هو، قد لا تحتاج لتغييرات كبيرة هنا إذا كان order_approved هو الأساسي) ...
                Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                // ...
            } elseif (in_array($eventType, [self::EVENT_TYPE_ORDER_DECLINED, self::EVENT_TYPE_ORDER_CANCELED, self::EVENT_TYPE_ORDER_EXPIRED])) {
                 Log::info("Processing Tamara event: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $eventType, $requestData) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) $query->where('invoice_number', $resolved_order_reference_id);
                        else if ($tamaraOrderId) $query->orWhere('payment_gateway_ref', $tamaraOrderId); // Fallback
                    })
                    ->with('booking.user')
                    ->lockForUpdate()->first();

                    if (!$invoice) {
                        Log::warning("Webhook: Invoice not found for {$eventType}.", ['tamara_order_id' => $tamaraOrderId, 'ref_id' => $resolved_order_reference_id]);
                        return;
                    }

                    $newInvoiceStatus = '';
                    $reason = "Tamara: {$eventType}";
                     if (isset($requestData['data']) && is_array($requestData['data']) && isset($requestData['data']['failure_reason'])) {
                        $reason = $requestData['data']['failure_reason'];
                    } elseif (isset($requestData['reason'])) {
                        $reason = $requestData['reason'];
                    }


                    if ($eventType === self::EVENT_TYPE_ORDER_DECLINED) $newInvoiceStatus = Invoice::STATUS_FAILED;
                    elseif ($eventType === self::EVENT_TYPE_ORDER_CANCELED) $newInvoiceStatus = Invoice::STATUS_CANCELLED;
                    elseif ($eventType === self::EVENT_TYPE_ORDER_EXPIRED) $newInvoiceStatus = Invoice::STATUS_EXPIRED;

                    if (!in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) { // لا تغير حالة الفواتير المدفوعة بالفعل
                        if ($invoice->status !== $newInvoiceStatus && !empty($newInvoiceStatus)) {
                           $invoice->status = $newInvoiceStatus;
                           $invoice->save();
                           Log::info("Invoice status updated to '{$newInvoiceStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);
                        }

                        $customer = $invoice->booking?->user;
                        if ($customer) {
                            $customer->notify(new PaymentFailedNotification($invoice, $reason));
                             Log::info("PaymentFailedNotification queued for CUSTOMER {$customer->id} via {$eventType} webhook (Reason: {$reason}).");
                        }
                        $admins = User::where('is_admin', true)->get();
                        foreach($admins as $admin) {
                            $admin->notify(new PaymentFailedNotification($invoice, $reason));
                            Log::info("PaymentFailedNotification queued for ADMIN {$admin->id} via {$eventType} webhook (Reason: {$reason}).");
                        }
                    } else {
                        Log::info("Invoice {$invoice->id} already {$invoice->status}. No status change from Tamara {$eventType} webhook.", ['tamara_order_id' => $tamaraOrderId]);
                    }
                });
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId]);
            }
            return response()->json(['status' => 'success_event_processed'], 200); // دائماً أرجع 200 لتمارا إذا تم استلام الحدث
        } catch (Throwable $e) {
            Log::error('Unhandled exception during Tamara webhook processing.', [
                'event_type' => $eventType ?? 'unknown',
                'tamara_order_id' => $tamaraOrderId,
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            // حتى في حالة الخطأ، أرجع 200 لتمارا لمنع الإعادة المفرطة، الخطأ تم تسجيله.
            return response()->json(['status' => 'error_processing_event_but_received'], 200);
        }
    } // نهاية handleTamaraWebhook

    // ... دالة retryTamaraPayment تبقى كما هي ...
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

        if ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) {
            $amountToRetry = $invoice->remaining_amount > 0.009 ? $invoice->remaining_amount : 0.0;
            $retryPaymentOption = 'full'; 
            Log::info("Retry for partially paid invoice. Attempting to pay remaining full amount for invoice.", ['invoice_id' => $invoice->id, 'remaining_amount' => $amountToRetry]);
        } elseif ($invoice->payment_option === 'down_payment') {
            $downPaymentAmount = $invoice->booking?->down_payment_amount > 0.009
                                ? $invoice->booking->down_payment_amount
                                : 0.0; 
            if ($downPaymentAmount <= 0.009 && $invoice->amount > 0.009 && $invoice->booking?->service?->price_sar > 0.009) {
                $originalServicePrice = $invoice->booking->service->price_sar;
                $discountApplied = $originalServicePrice - $invoice->amount; // تقدير قيمة الخصم
                $priceAfterDiscount = $invoice->amount;
                $calculatedDownpayment = round($priceAfterDiscount / 2, 0);
                Log::warning("down_payment_amount not found or zero on booking {$invoice->booking_id} for invoice {$invoice->id}. Attempting to calculate 50% of invoice amount. Stored: {$downPaymentAmount}, Calculated: {$calculatedDownpayment}", ['invoice_total' => $invoice->amount]);
                $downPaymentAmount = $calculatedDownpayment > 0.009 ? $calculatedDownpayment : 0.0;
            }
            $amountToRetry = $downPaymentAmount;
            $retryPaymentOption = 'down_payment'; 
            Log::info("Retry for initial down payment. Attempting to pay down payment amount.", ['invoice_id' => $invoice->id, 'down_payment_amount_to_retry' => $amountToRetry]);
        } else { 
            $amountToRetry = $invoice->amount;
            $retryPaymentOption = 'full';
            Log::info("Retry for full payment invoice. Attempting to pay full amount.", ['invoice_id' => $invoice->id, 'amount_to_retry_calculated' => $amountToRetry]);
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
           return Redirect::route('customer.invoices.show', $invoice)->with('error', 'المبلغ المطلوب دفعه غير صحيح أو أن الفاتورة لا تتطلب دفعة حالياً. يرجى التواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.');
       }

       Log::debug('Proceeding to initiate checkout for retry.', ['invoice_id' => $invoice->id, 'amount_to_retry' => $amountToRetry, 'final_retry_payment_option_to_tamara' => $retryPaymentOption]);

       try {
           $sessionKey = 'previous_invoice_status_' . $invoice->id;
           session([$sessionKey => $invoice->status]);
           Log::debug("Stored previous status '{$invoice->status}' in session for key: {$sessionKey}");
           
           // تمارا سيرفيس سيقرأ إعداداته من قاعدة البيانات الآن
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
