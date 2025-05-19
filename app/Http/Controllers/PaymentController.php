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
// use App\Services\TamaraService; // إذا كنت لا تستخدمها في هذا المتحكم، يمكنك إزالتها
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Str;

// مسارات محتملة لـ SDK v2.0 - **يرجى التحقق منها في مجلد vendor لديك**
use Tamara\Client as TamaraClient; // لاستدعاءات API أخرى مثل Authorise
use Tamara\Configuration as TamaraConfiguration; // لاستدعاءات API أخرى
use Tamara\Notification\Authenticator as TamaraAuthenticator; // هذا هو الأهم للـ Webhook
use Tamara\Notification\NotificationService as TamaraNotificationService; // قد لا تحتاج لهذا إذا استخدمت Authenticator
use Tamara\Model\Order\Order as TamaraOrder; // مثال لكيفية الحصول على بيانات من الـ Webhook Message
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest; // لاستدعاءات API أخرى

// استثناءات محتملة من SDK v2.0
use Tamara\Exception\ForbiddenException as TamaraForbiddenException; // لفشل المصادقة
use Tamara\Exception\RequestException as TamaraRequestException; // للأخطاء العامة في الطلبات أو معالجة SDK
// قد يكون هناك NotificationException أيضًا، أو قد تكون مدمجة في RequestException


class PaymentController extends Controller
{
    // ... (دوال handleTamaraSuccess, handleTamaraFailure, handleTamaraCancel تبقى كما هي) ...
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
            Log::info("Tamara success redirect, but final invoice status is '{$invoice->status}'. Using default success message.", ['invoice_id' => $invoice->id]);
        }

        if (session()->has($sessionKey)) {
            session()->forget($sessionKey);
            Log::debug("Cleared previous status from session for invoice: " . $invoice->id);
        }

        Log::info("Redirecting user to booking pending page after Tamara success redirect.", ['booking_id' => $bookingId]);
        return Redirect::route('booking.pending', ['booking' => $bookingId])
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
            Log::info("Invoice status will be updated by webhook if payment truly failed. Current status '{$originalStatus}' for invoice: " . $invoice->id);

            if ($customer) {
                try {
                    $customer->notify(new PaymentFailedNotification($invoice, $customer, $reason));
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
                    $admin->notify(new PaymentFailedNotification($invoice, $admin, $reason));
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
     * تم التعديل ليتناسب مع SDK v2.0 باستخدام Authenticator
     */
    public function handleTamaraWebhook(Request $request)
    {
        // =================================================================
        // START: Logging the entire incoming request for diagnostics
        // =================================================================
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(),
            'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(),
            'ip_address' => $request->ip(),
            'query_parameters' => $request->query(),
            'form_parameters_or_json' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
        // =================================================================
        // END: Logging the entire incoming request
        // =================================================================

        Log::info("Tamara Webhook received (after full log). Attempting processing with SDK v2 Authenticator...");

        $notificationTokenFromConfig = config('services.tamara.notification_token');
        Log::info('TAMARA_NOTIFICATION_TOKEN from config for Webhook: ' . $notificationTokenFromConfig);

        if (empty($notificationTokenFromConfig)) {
            Log::error('Tamara Webhook Error: Missing config (services.tamara.notification_token). Cannot authenticate.');
            return response()->json(['status' => 'error_config_missing_token'], 403); // يجب أن يكون خطأ مصادقة
        }

        $eventType = null; $tamaraOrderId = null; $orderReferenceId = null; $payloadData = null;
        /** @var \Tamara\Model\Notification\NotificationMessage $webhookMessage */
        $webhookMessage = null;

        try {
            // استخدام TamaraAuthenticator لـ SDK v2.0
            $authenticator = new TamaraAuthenticator($notificationTokenFromConfig);
            $webhookMessage = $authenticator->authenticate(
                $request->getContent(),      // المحتوى الخام للطلب
                $request->header('Authorization') // الهيدر الخاص بالمصادقة (عادةً 'Bearer <JWT>')
            );

            Log::info('Tamara webhook authenticated successfully using Authenticator.');

            // استخلاص البيانات من $webhookMessage
            // في SDK v2، $webhookMessage قد يكون من نوع Tamara\Model\Notification\NotificationMessage
            // والبيانات مثل order_id, event_type, data تكون كـ properties أو عبر getters
            $eventType = $webhookMessage->getEventType();
            $tamaraOrderId = $webhookMessage->getOrderId();
            // order_reference_id قد لا يكون متاحًا مباشرة في NotificationMessage،
            // قد يكون داخل $webhookMessage->getData()['order_reference_id'] أو ما شابه،
            // أو قد تحتاج لاستخلاصه من الفاتورة لاحقًا.
            // سأفترض أنه موجود في $webhookMessage->getData() للآن.
            $payloadData = $webhookMessage->getData(); // تكون عادةً مصفوفة
            $orderReferenceId = $payloadData['order_reference_id'] ?? ($payloadData['order_number'] ?? null);


            Log::debug('Extracted Webhook Data from Authenticator:', [
                'event_type' => $eventType,
                'tamara_order_id' => $tamaraOrderId,
                'order_reference_id_from_payload' => $orderReferenceId, // قد تحتاج لتأكيد هذا
                'payload_data_sample' => Str::limit(json_encode($payloadData), 200)
            ]);

            if (empty($eventType) || empty($tamaraOrderId)) { // orderReferenceId قد لا يكون ضروريًا للتحقق الأولي
                Log::error('Tamara Webhook Error: Missing essential data (event_type or order_id) from webhook message after authentication.');
                return response()->json(['status' => 'success_auth_data_incomplete'], 200);
            }
        } catch (TamaraForbiddenException $e) { // هذا الاستثناء هو المتوقع لفشل المصادقة في SDK v2
            Log::error('Tamara Webhook Auth Failed (TamaraForbiddenException).', [
                'msg' => $e->getMessage(),
                'tamara_token_from_config' => $notificationTokenFromConfig,
                'authorization_header_received' => $request->header('Authorization') ? 'Present' : 'Missing or Empty',
            ]);
            return response()->json(['status' => 'error_auth', 'message' => 'Access denied.'], 403);
        } catch (TamaraRequestException $e) { // لالتقاط أخطاء أخرى من SDK تمارا
            Log::error('Tamara Webhook SDK Error (TamaraRequestException).', [
                'msg' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 500)
            ]);
            return response()->json(['status' => 'error_tamara_sdk'], 400); // خطأ في الطلب
        } catch (Throwable $e) {
            Log::error('Tamara Webhook General Error during initial processing/authentication.', [
                'msg' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return response()->json(['status' => 'success_but_general_error_logged'], 200);
        }

        // --- هنا يبدأ منطق معالجة أنواع الأحداث المختلفة (يبقى كما هو تقريبًا) ---
        // تأكد من أن $orderReferenceId يتم استخلاصه بشكل صحيح أعلاه أو من $invoice لاحقًا
        try {
            if ($eventType === TamaraOrder::EVENT_TYPE_ORDER_APPROVED) { // استخدام الثابت من SDK إذا كان متاحًا
                Log::info('Processing Tamara order_approved event.', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $orderReferenceId]);

                DB::transaction(function () use ($tamaraOrderId, $orderReferenceId, $payloadData, $webhookMessage) {
                    // إذا لم يتم استخلاص orderReferenceId من الـ payload، قد تحتاج للبحث عن الفاتورة باستخدام tamaraOrderId
                    // إذا كان payment_gateway_ref في جدول الفواتير يخزن tamaraOrderId بعد الإنشاء
                    $invoice = Invoice::where('invoice_number', $orderReferenceId) // افترض أن invoice_number هو order_reference_id
                                        ->orWhere('payment_gateway_ref', $tamaraOrderId) // أو ابحث بـ tamaraOrderId
                                        ->with('booking.user', 'booking.service')
                                        ->first();

                    if (!$invoice) {
                        Log::warning('Webhook: Invoice not found for order_approved.', [
                            'tamara_order_id' => $tamaraOrderId,
                            'attempted_order_reference_id' => $orderReferenceId
                        ]);
                        return;
                    }
                     // إذا اعتمدنا على invoice_number، نضمن أن orderReferenceId صحيح الآن
                    $currentOrderReferenceId = $invoice->invoice_number;


                    if (!$invoice->booking || !$invoice->booking->user) {
                         Log::warning('Webhook: Booking or User not found for invoice.', ['invoice_id' => $invoice->id]);
                         return;
                    }

                    $customer = $invoice->booking->user;
                    $originalInvoiceStatus = $invoice->status;
                    $newInvoiceStatus = $originalInvoiceStatus;

                    if ($originalInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) {
                        $newInvoiceStatus = Invoice::STATUS_PAID;
                    } elseif (in_array($originalInvoiceStatus, [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING, Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED, Invoice::STATUS_EXPIRED])) {
                        $paymentOptionCleaned = trim(strtolower($invoice->payment_option ?? 'full'));
                        $newInvoiceStatus = ($paymentOptionCleaned === 'down_payment')
                                            ? Invoice::STATUS_PARTIALLY_PAID
                                            : Invoice::STATUS_PAID;
                    } else {
                        Log::info("Webhook: Invoice status '{$originalInvoiceStatus}' is already suitable or no change needed from approved webhook.", ['invoice_id' => $invoice->id]);
                    }

                    $amountPaidInThisTransaction = 0;
                    $orderDataFromWebhook = $webhookMessage->getOrder(); // TamaraOrder object

                    // SDK v2 قد يوفر طرقًا مباشرة للحصول على هذه القيم من $orderDataFromWebhook
                    $tamaraAmount = $orderDataFromWebhook->getTotalAmount()->getAmount();
                    $tamaraCurrency = $orderDataFromWebhook->getTotalAmount()->getCurrency();


                    if ($tamaraAmount !== null) {
                        $amountPaidInThisTransaction = (float) $tamaraAmount;
                    } else {
                        Log::warning("Could not extract amount from Tamara webhook (SDK v2 method). Estimating.", ['tamara_order_id' => $tamaraOrderId]);
                        // منطق التقدير كما كان
                        if($newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID && $originalInvoiceStatus !== Invoice::STATUS_PARTIALLY_PAID) {
                            $amountPaidInThisTransaction = $invoice->booking->down_payment_amount > 0 ? $invoice->booking->down_payment_amount : round($invoice->amount / 2, 2);
                        } elseif ($newInvoiceStatus === Invoice::STATUS_PAID && $originalInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) {
                            $amountPaidInThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : 0;
                        } elseif ($newInvoiceStatus === Invoice::STATUS_PAID && $originalInvoiceStatus !== Invoice::STATUS_PAID) {
                            $amountPaidInThisTransaction = $invoice->amount;
                        }
                    }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId) // tamaraOrderId هو المعرف الفريد لتمارا
                                            ->where('invoice_id', $invoice->id)
                                            ->exists();

                    if (!$existingPayment && $amountPaidInThisTransaction > 0.009) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'transaction_id' => $tamaraOrderId,
                            'amount' => $amountPaidInThisTransaction,
                            'currency' => $tamaraCurrency,
                            'status' => 'completed',
                            'payment_gateway' => 'tamara',
                            'payment_details' => json_encode($payloadData) ?: null, // $payloadData هي $webhookMessage->getData()
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                        Log::info("Payment record created via webhook.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);

                        try {
                            $customer->notify(new PaymentSuccessNotification($invoice, $customer, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification dispatched to customer {$customer->id} via webhook.");
                        } catch (\Exception $e) { Log::error("Failed to send PaymentSuccessNotification to customer {$customer->id} via webhook", ['error' => $e->getMessage()]); }

                        $admins = User::where('is_admin', true)->get();
                        foreach ($admins as $admin) {
                            try {
                                $admin->notify(new PaymentSuccessNotification($invoice, $admin, $amountPaidInThisTransaction, $tamaraCurrency));
                                Log::info("PaymentSuccessNotification dispatched to admin {$admin->id} via webhook.");
                            } catch (\Exception $e) { Log::error("Failed to send PaymentSuccessNotification to admin {$admin->id} via webhook", ['error' => $e->getMessage()]); }
                        }
                    } elseif($existingPayment) {
                        Log::warning("Duplicate payment webhook or payment already recorded. Ignored for order_id.", ['tamara_order_id' => $tamaraOrderId, 'invoice_id' => $invoice->id]);
                    }


                    if ($newInvoiceStatus !== $originalInvoiceStatus) {
                        $invoice->status = $newInvoiceStatus;
                        $invoice->paid_at = $invoice->paid_at ?? Carbon::now();
                        if(empty($invoice->payment_gateway_ref)) { $invoice->payment_gateway_ref = $tamaraOrderId; }
                        $invoice->save();
                        Log::info("Invoice status updated to '{$newInvoiceStatus}' via webhook.", ['invoice_id' => $invoice->id]);
                    }

                    $booking = $invoice->booking;
                    if ($booking && $booking->status !== Booking::STATUS_CONFIRMED && in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                        $oldBookingStatus = $booking->status;
                        $booking->status = Booking::STATUS_CONFIRMED;
                        $booking->save();
                        Log::info('Booking status updated to confirmed via webhook.', ['booking_id' => $booking->id, 'old_status' => $oldBookingStatus]);

                        try {
                            $customer->notify(new BookingConfirmedNotification($booking, $customer));
                            Log::info("BookingConfirmedNotification dispatched to customer {$customer->id} via webhook.");
                        } catch (\Exception $e) { Log::error("Failed to send BookingConfirmedNotification to customer {$customer->id} via webhook", ['error' => $e->getMessage()]); }

                        $admins = User::where('is_admin', true)->get();
                        foreach ($admins as $admin) {
                             try {
                                 $admin->notify(new BookingConfirmedNotification($booking, $admin));
                                 Log::info("BookingConfirmedNotification dispatched to admin {$admin->id} via webhook.");
                             } catch (\Exception $e) { Log::error("Failed to send BookingConfirmedNotification to admin {$admin->id} via webhook", ['error' => $e->getMessage()]); }
                        }
                    }

                    if (!in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])){
                        Log::info("Skipping Authorise API call as invoice status is not PAID or PARTIALLY_PAID.", ['invoice_id' => $invoice->id, 'status' => $newInvoiceStatus]);
                    } else {
                        try {
                            // استخدام الكلاسات المعرفة مع alias
                            $apiUrlAuth = config('services.tamara.url'); $apiTokenAuth = config('services.tamara.token'); $timeoutAuth = config('services.tamara.request_timeout', 10);
                            if(empty($apiUrlAuth) || empty($apiTokenAuth)) {
                                Log::error('Tamara Authorise API config missing (URL or Token). Skipping Authorise call.');
                            } else {
                                $configurationAuth = TamaraConfiguration::create($apiUrlAuth, $apiTokenAuth, $timeoutAuth);
                                $client = TamaraClient::create($configurationAuth);
                                $authoriseOrderRequest = new TamaraAuthoriseOrderRequest($tamaraOrderId);
                                Log::debug("Attempting Authorise API via webhook.", ['tamara_order_id' => $tamaraOrderId]);
                                $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                if ($authoriseResponse->isSuccess()) {
                                    Log::info('Tamara Authorise API successful via webhook.', ['tamara_order_id' => $tamaraOrderId, 'response_order_id' => $authoriseResponse->getOrderId(), 'response_status' => $authoriseResponse->getOrderStatus()]);
                                } else {
                                    Log::error('Tamara Authorise API failed via webhook.', ['tamara_order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                }
                            }
                        } catch (Throwable $authError) {
                            Log::error('Exception during Tamara Authorise API call via webhook.', ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                        }
                    }
                }); // نهاية DB::transaction

            } elseif (in_array($eventType, [TamaraOrder::EVENT_TYPE_ORDER_DECLINED, TamaraOrder::EVENT_TYPE_ORDER_CANCELED, TamaraOrder::EVENT_TYPE_ORDER_EXPIRED])) { // استخدام ثوابت إذا متاحة
                Log::warning("Processing Tamara {$eventType} event.", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $orderReferenceId]);
                DB::transaction(function() use ($eventType, $orderReferenceId, $tamaraOrderId, $webhookMessage) { // مرر $webhookMessage إذا احتجت بيانات منه
                    $invoice = Invoice::where('invoice_number', $orderReferenceId)
                                        ->orWhere('payment_gateway_ref', $tamaraOrderId)
                                        ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED])
                                        ->with('booking.user')
                                        ->first();
                    if ($invoice) {
                        $customer = $invoice->booking?->user;
                        $originalStatus = $invoice->status;
                        $newStatus = match($eventType) {
                            TamaraOrder::EVENT_TYPE_ORDER_DECLINED, TamaraOrder::EVENT_TYPE_ORDER_EXPIRED => Invoice::STATUS_FAILED,
                            TamaraOrder::EVENT_TYPE_ORDER_CANCELED => Invoice::STATUS_CANCELLED,
                            default => $originalStatus
                        };

                        if ($newStatus !== $originalStatus) {
                            $invoice->status = $newStatus;
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);

                            if ($customer && in_array($newStatus, [Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED])) {
                                $reason = "تم " . str_replace('order_', '', $eventType) . " للطلب من قبل تمارا."; // سبب أوضح
                                try {
                                    $customer->notify(new PaymentFailedNotification($invoice, $customer, $reason));
                                    Log::info("PaymentFailedNotification dispatched to customer {$customer->id} via {$eventType} webhook.");
                                } catch (\Exception $e) { Log::error("Failed to send PaymentFailedNotification to customer {$customer->id} via {$eventType}", ['error' => $e->getMessage()]); }

                                $admins = User::where('is_admin', true)->get();
                                foreach ($admins as $admin) {
                                    try {
                                        $admin->notify(new PaymentFailedNotification($invoice, $admin, $reason));
                                        Log::info("PaymentFailedNotification dispatched to admin {$admin->id} via {$eventType} webhook.");
                                    } catch (\Exception $e) { Log::error("Failed to send PaymentFailedNotification to admin {$admin->id} via {$eventType}", ['error' => $e->getMessage()]); }
                                }
                            }
                        } else {
                            Log::info("Invoice status not changed via {$eventType} webhook, already '{$originalStatus}'.", ['invoice_id' => $invoice->id]);
                        }
                    } else {
                        Log::warning("Invoice not found or already processed/cancelled for {$eventType} webhook.", ['attempted_order_reference_id' => $orderReferenceId, 'tamara_order_id' => $tamaraOrderId]);
                    }
                }); // نهاية DB::transaction
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'payload_data' => $payloadData]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception after webhook processing logic.', [
                'event_type' => $eventType ?? 'unknown',
                'tamara_order_id' => $tamaraOrderId ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_internal_error_logged'], 200);
        }
    }


    /**
     * Retry payment for a given invoice using Tamara.
     */
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
            // تأكد من أن TamaraService مسجلة بشكل صحيح وتستخدم الكلاسات الصحيحة من SDK v2
            $tamaraService = resolve(\App\Services\TamaraService::class); // استخدام المسار الكامل إذا لم تكن هناك عبارة use
            $retryPaymentOption = ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) ? 'full' : ($invoice->payment_option ?? 'full');
            Log::debug('Determined paymentOption for retry.', ['invoice_id' => $invoice->id, 'original_option' => $invoice->payment_option, 'retry_option' => $retryPaymentOption]);

            $sessionKey = 'previous_invoice_status_' . $invoice->id;
            session([$sessionKey => $invoice->status]);
            Log::debug("Stored previous status '{$invoice->status}' in session for key: {$sessionKey}");

            $checkoutResponse = $tamaraService->initiateCheckout($invoice, $amountToRetry, $retryPaymentOption);

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
            Log::error('Exception during Tamara retry payment initiation.', ['invoice_id' => $invoice->id, 'error' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(),1000)]);
            return Redirect::route('customer.invoices.show', $invoice)->with('error', 'حدث خطأ غير متوقع أثناء محاولة الدفع.');
        }
    }

}
