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
use App\Services\TamaraService; // تفترض أنك تستخدم هذه الخدمة في مكان آخر
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // مهم لاستخدام دالة التسجيل
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Str;

// تأكد من هذه الـ namespaces بناءً على إصدار SDK تمارا الذي تستخدمه
// قد تكون مختلفة إذا كنت تستخدم tamara-solution/php-sdk v2+
use Tamara\Client; // إذا كنت تستخدمه للـ Authorise
use Tamara\Configuration; // إذا كنت تستخدمه للـ Authorise
use Tamara\Notification\NotificationService; // هذا هو المستخدم في الكود الحالي
use Tamara\Notification\Exception\ForbiddenException; // هذا هو المستخدم في الكود الحالي
use Tamara\Notification\Exception\NotificationException; // هذا هو المستخدم في الكود الحالي
// use Tamara\Notification\Authenticator; // بديل محتمل لـ NotificationService في بعض إصدارات SDK
// use Tamara\Exception\RequestException; // بديل محتمل لـ NotificationException في SDK v2+
use Tamara\Request\Order\AuthoriseOrderRequest; // إذا كنت تستخدمه للـ Authorise


class PaymentController extends Controller
{
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
        // قد ترغب في معاملة الإلغاء بشكل مختلف عن الفشل في المستقبل
        return $this->handleTamaraFailure($request, $invoice);
    }


    /**
     * Handles the incoming webhook notification from Tamara.
     */
    public function handleTamaraWebhook(Request $request)
    {
        // =================================================================
        // START: Logging the entire incoming request for diagnostics
        // =================================================================
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(),
            'content_type' => $request->getContentTypeFormat(), // Use getContentTypeFormat() for Laravel 9+
            'method' => $request->getMethod(),
            'ip_address' => $request->ip(),
            'query_parameters' => $request->query(), // Log query parameters if any
            'form_parameters_or_json' => $request->all(), // Parsed JSON or form data
            'raw_content' => $request->getContent() // Raw body content
        ]);
        // =================================================================
        // END: Logging the entire incoming request
        // =================================================================

        Log::info("Tamara Webhook received (after full log). Attempting processing..."); // رسالة السجل الأصلية

        $notificationKeyFromConfig = config('services.tamara.notification_token');
        Log::info('TAMARA_NOTIFICATION_TOKEN from config for Webhook: ' . $notificationKeyFromConfig); // رسالة السجل الأصلية

        if (empty($notificationKeyFromConfig)) {
            Log::error('Tamara Webhook Error: Missing config (services.tamara.notification_token).');
            // لا ترجع 500 هنا مباشرة إذا كان الخطأ في الإعدادات، لأنه قد يعاد إرسال الـ webhook
            // من الأفضل إرجاع 200 مع تسجيل الخطأ، لتجنب تكرار الإرسال من تمارا إذا كان الخطأ داخليًا.
            // ومع ذلك، إذا كان الخطأ في المصادقة بسبب نقص المفتاح، فـ 403/401 مناسب.
            // بما أن SDK سيفشل على الأرجح، يمكننا تركه لـ catch block.
        }

        $eventType = null; $tamaraOrderId = null; $orderReferenceId = null; $payloadData = null;

        try {
            // تأكد أنك تستخدم الـ Notification Key الصحيح هنا.
            // الكود الأصلي كان $notificationKey = config(...) ثم $notificationKey = $notificationKeyFromConfig
            // لذا سأستخدم $notificationKeyFromConfig مباشرة
            $notificationService = NotificationService::create($notificationKeyFromConfig);
            $webhookMessage = $notificationService->processWebhook(); // هذا هو المكان الذي يتم فيه التحقق من التوقيع

            Log::info('Tamara webhook processed and authenticated successfully.');

            // استخلاص البيانات من $webhookMessage - تأكد من أن هذه الدوال موجودة في إصدار SDK الخاص بك
            $eventType = method_exists($webhookMessage, 'getEventType') ? $webhookMessage->getEventType() : ($webhookMessage->get('event_type') ?? null);
            $tamaraOrderId = method_exists($webhookMessage, 'getOrderId') ? $webhookMessage->getOrderId() : ($webhookMessage->get('order_id') ?? null);
            $orderReferenceId = method_exists($webhookMessage, 'getOrderReferenceId') ? $webhookMessage->getOrderReferenceId() : ($webhookMessage->get('order_reference_id') ?? null);
            $payloadData = method_exists($webhookMessage, 'getData') ? $webhookMessage->getData() : ($webhookMessage->get('data') ?? []); // data عادة ما تكون مصفوفة

            Log::debug('Extracted Webhook Data:', [
                'event_type' => $eventType,
                'order_id' => $tamaraOrderId,
                'order_reference_id' => $orderReferenceId,
                'payload_data_sample' => Str::limit(json_encode($payloadData), 200) // عينة من البيانات لتجنب سجلات ضخمة
            ]);

            if (empty($eventType) || empty($tamaraOrderId) || empty($orderReferenceId)) {
                Log::error('Tamara Webhook Error: Missing essential data (event_type, order_id, or order_reference_id) from webhook message after authentication.');
                // لا يزال يعتبر نجاحًا من حيث الاستلام والمصادقة، لكن البيانات غير مكتملة
                return response()->json(['status' => 'success_auth_data_incomplete'], 200);
            }
        } catch (ForbiddenException $e) { // هذا الاستثناء عادةً ما يعني فشل التحقق من التوقيع
            Log::error('Tamara Webhook Auth Failed (ForbiddenException).', [
                'msg' => $e->getMessage(),
                'tamara_token_from_config' => $notificationKeyFromConfig, // لعرض التوكن المستخدم للمقارنة
                // 'request_signature_header' => $request->header('Authorization') // قد ترغب في تسجيل هذا للتحقق
            ]);
            return response()->json(['status' => 'error_auth', 'message' => 'Access denied.'], 403); // 403 Forbidden هو الأنسب
        } catch (NotificationException $e) { // أخطاء أخرى متعلقة بمعالجة الإشعار
            Log::error('Tamara Webhook Processing Error (NotificationException).', [
                'msg' => $e->getMessage()
            ]);
            // نرجع 200 هنا لتجنب إعادة إرسال تمارا للـ webhook إذا كان الخطأ في معالجتنا للبيانات وليس في الطلب نفسه
            return response()->json(['status' => 'success_but_processing_error_logged'], 200);
        }
        // } catch (RequestException $e) { // إذا كنت تستخدم SDK v2+، قد تحتاج لهذا
        //     Log::error('Tamara Webhook Request Error (SDK v2+).', ['msg' => $e->getMessage()]);
        //     return response()->json(['status' => 'error_request_sdk'], 400);
        // }
          catch (Throwable $e) { // لالتقاط أي أخطاء أخرى غير متوقعة أثناء المصادقة أو استخلاص البيانات الأولية
            Log::error('Tamara Webhook General Error during initial processing.', [
                'msg' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            // نرجع 200 هنا لتجنب إعادة إرسال تمارا إذا كان الخطأ عامًا في جانبنا
            return response()->json(['status' => 'success_but_general_error_logged'], 200);
        }

        // --- هنا يبدأ منطق معالجة أنواع الأحداث المختلفة ---
        try {
            if ($eventType === 'order_approved') {
                Log::info('Processing Tamara order_approved event.', ['order_id' => $tamaraOrderId, 'order_reference_id' => $orderReferenceId]);

                DB::transaction(function () use ($tamaraOrderId, $orderReferenceId, $payloadData) {
                    $invoice = Invoice::where('invoice_number', $orderReferenceId)
                                        ->with('booking.user', 'booking.service')
                                        ->first();

                    if (!$invoice) {
                        Log::warning('Webhook: Invoice not found for order_approved.', ['ref_id' => $orderReferenceId]);
                        return; // الخروج من الـ transaction
                    }
                    if (!$invoice->booking || !$invoice->booking->user) {
                         Log::warning('Webhook: Booking or User not found for invoice.', ['invoice_id' => $invoice->id]);
                         return; // الخروج من الـ transaction
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
                    // محاولة استخلاص المبلغ من عدة أماكن محتملة في payload تمارا
                    $tamaraAmount = $payloadData['total_amount']['amount'] ??
                                    ($payloadData['order_amount']['amount'] ??
                                    ($payloadData['captured_amount']['amount'] ?? // لبعض حالات order_approved
                                    null));
                    $tamaraCurrency = $payloadData['total_amount']['currency'] ??
                                      ($payloadData['order_amount']['currency'] ??
                                      ($payloadData['captured_amount']['currency'] ??
                                      $invoice->currency));

                    if ($tamaraAmount !== null) {
                        $amountPaidInThisTransaction = (float) $tamaraAmount;
                    } else {
                        Log::warning("Could not extract amount from Tamara webhook payload for approved order. Estimating.", ['order_id' => $tamaraOrderId, 'payload_keys' => array_keys($payloadData)]);
                        // منطق التقدير كما كان
                        if($newInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID && $originalInvoiceStatus !== Invoice::STATUS_PARTIALLY_PAID) {
                            $amountPaidInThisTransaction = $invoice->booking->down_payment_amount > 0 ? $invoice->booking->down_payment_amount : round($invoice->amount / 2, 2);
                        } elseif ($newInvoiceStatus === Invoice::STATUS_PAID && $originalInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) {
                            $amountPaidInThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : 0;
                        } elseif ($newInvoiceStatus === Invoice::STATUS_PAID && $originalInvoiceStatus !== Invoice::STATUS_PAID) {
                            $amountPaidInThisTransaction = $invoice->amount;
                        }
                    }

                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)
                                            ->where('invoice_id', $invoice->id)
                                            ->exists();

                    if (!$existingPayment && $amountPaidInThisTransaction > 0.009) {
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'transaction_id' => $tamaraOrderId,
                            'amount' => $amountPaidInThisTransaction,
                            'currency' => $tamaraCurrency,
                            'status' => 'completed', // أو 'approved'
                            'payment_gateway' => 'tamara',
                            'payment_details' => json_encode($payloadData) ?: null,
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
                        $invoice->paid_at = $invoice->paid_at ?? Carbon::now(); // تحديث وقت الدفع فقط إذا لم يكن قد تم دفعه جزئيًا من قبل
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

                    // Authorise API call - منطق جيد
                    if (!in_array($newInvoiceStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])){
                        Log::info("Skipping Authorise API call as invoice status is not PAID or PARTIALLY_PAID.", ['invoice_id' => $invoice->id, 'status' => $newInvoiceStatus]);
                    } else {
                        try {
                            $apiUrlAuth = config('services.tamara.url'); $apiTokenAuth = config('services.tamara.token'); $timeoutAuth = config('services.tamara.request_timeout', 10);
                            if(empty($apiUrlAuth) || empty($apiTokenAuth)) {
                                Log::error('Tamara Authorise API config missing (URL or Token). Skipping Authorise call.');
                            } else {
                                $configurationAuth = Configuration::create($apiUrlAuth, $apiTokenAuth, $timeoutAuth); $client = Client::create($configurationAuth);
                                $authoriseOrderRequest = new AuthoriseOrderRequest($tamaraOrderId);
                                Log::debug("Attempting Authorise API via webhook.", ['tamara_order_id' => $tamaraOrderId]);
                                $authoriseResponse = $client->authoriseOrder($authoriseOrderRequest);
                                if ($authoriseResponse->isSuccess()) {
                                    Log::info('Tamara Authorise API successful via webhook.', ['order_id' => $tamaraOrderId, 'response_order_id' => $authoriseResponse->getOrderId(), 'response_status' => $authoriseResponse->getOrderStatus()]);
                                } else {
                                    Log::error('Tamara Authorise API failed via webhook.', ['order_id' => $tamaraOrderId, 'errors' => $authoriseResponse->getErrors()]);
                                }
                            }
                        } catch (Throwable $authError) {
                            Log::error('Exception during Tamara Authorise API call via webhook.', ['order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                        }
                    }
                }); // نهاية DB::transaction

            } elseif (in_array($eventType, ['order_declined', 'order_canceled', 'order_expired'])) {
                Log::warning("Processing Tamara {$eventType} event.", ['order_id' => $tamaraOrderId, 'order_reference_id' => $orderReferenceId]);
                DB::transaction(function() use ($eventType, $orderReferenceId, $tamaraOrderId) {
                    $invoice = Invoice::where('invoice_number', $orderReferenceId)
                                        ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED]) // تجنب تحديث الفواتير المدفوعة/الملغاة بالفعل
                                        ->with('booking.user')
                                        ->first();
                    if ($invoice) {
                        $customer = $invoice->booking?->user;
                        $originalStatus = $invoice->status;
                        $newStatus = match($eventType) {
                            'order_declined', 'order_expired' => Invoice::STATUS_FAILED,
                            'order_canceled' => Invoice::STATUS_CANCELLED,
                            default => $originalStatus // لا يجب أن يحدث هذا بسبب التحقق أعلاه
                        };

                        if ($newStatus !== $originalStatus) {
                            $invoice->status = $newStatus;
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newStatus}' via {$eventType} webhook.", ['invoice_id' => $invoice->id]);

                            if ($customer && in_array($newStatus, [Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED])) {
                                $reason = "تم {$eventType} الطلب من قبل تمارا.";
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
                        Log::warning("Invoice not found or already processed/cancelled for {$eventType} webhook.", ['ref_id' => $orderReferenceId]);
                    }
                }); // نهاية DB::transaction
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['order_id' => $tamaraOrderId, 'payload_data' => $payloadData]);
            }
            return response()->json(['status' => 'success'], 200); // إرجاع نجاح إذا تمت معالجة النوع أو تم تخطيه
        } catch (Throwable $e) { // لالتقاط أي أخطاء أثناء معالجة منطق العمل بعد المصادقة
            Log::error('Unhandled exception after webhook processing logic.', [
                'event_type' => $eventType ?? 'unknown',
                'order_id' => $tamaraOrderId ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1500) // زيادة الحد قليلاً لرؤية المزيد من التتبع
            ]);
            // من المهم إرجاع 200 هنا أيضًا لتجنب إعادة إرسال الـ webhook من تمارا بسبب خطأ في منطقنا
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
            Invoice::STATUS_CANCELLED, // قد ترغب في إعادة النظر في السماح بإعادة المحاولة للفواتير الملغاة
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

        if ($amountToRetry <= 0.009) { // استخدام 0.009 لتجنب مشاكل الفاصلة العائمة الدقيقة
            Log::info('Retry attempt skipped as remaining amount is zero or less.', ['invoice_id' => $invoice->id]);
            return Redirect::route('customer.invoices.show', $invoice)->with('info', 'الفاتورة مدفوعة بالكامل.');
        }

        Log::debug('Proceeding to initiate checkout for retry.', ['invoice_id' => $invoice->id, 'amount_to_retry' => $amountToRetry]);

        try {
            $tamaraService = resolve(TamaraService::class); // تأكد من أن TamaraService مسجلة بشكل صحيح
            $retryPaymentOption = ($invoice->status === Invoice::STATUS_PARTIALLY_PAID) ? 'full' : ($invoice->payment_option ?? 'full');
            Log::debug('Determined paymentOption for retry.', ['invoice_id' => $invoice->id, 'original_option' => $invoice->payment_option, 'retry_option' => $retryPaymentOption]);

            $sessionKey = 'previous_invoice_status_' . $invoice->id;
            session([$sessionKey => $invoice->status]);
            Log::debug("Stored previous status '{$invoice->status}' in session for key: {$sessionKey}");

            $checkoutResponse = $tamaraService->initiateCheckout($invoice, $amountToRetry, $retryPaymentOption);

            if ($checkoutResponse && is_array($checkoutResponse) && isset($checkoutResponse['checkout_url']) && isset($checkoutResponse['order_id'])) {
                if(empty($invoice->payment_gateway_ref) || $invoice->payment_gateway_ref !== $checkoutResponse['order_id']) {
                    $invoice->payment_gateway_ref = $checkoutResponse['order_id']; // تحديث مرجع البوابة إذا كان جديدًا
                    $invoice->save();
                }
                Log::info('Tamara retry checkout URL obtained and invoice updated.', ['invoice_id' => $invoice->id, 'tamara_order_id' => $checkoutResponse['order_id']]);
                return Redirect::away($checkoutResponse['checkout_url']);
            } else {
                session()->forget($sessionKey); // مسح الجلسة إذا فشل البدء
                Log::error('Failed to get valid checkout response from TamaraService on retry.', ['invoice_id' => $invoice->id, 'response' => $checkoutResponse]);
                return Redirect::route('customer.invoices.show', $invoice)->with('error', 'فشل بدء عملية الدفع. يرجى المحاولة مرة أخرى.');
            }
        } catch (Throwable $e) {
            session()->forget('previous_invoice_status_' . $invoice->id); // مسح الجلسة عند حدوث خطأ
            Log::error('Exception during Tamara retry payment initiation.', ['invoice_id' => $invoice->id, 'error' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(),1000)]);
            return Redirect::route('customer.invoices.show', $invoice)->with('error', 'حدث خطأ غير متوقع أثناء محاولة الدفع.');
        }
    }
}
