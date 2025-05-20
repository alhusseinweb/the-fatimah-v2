<?php

// المسار: app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\PaymentFailedNotification; // تأكد أن هذا مستخدم أو احذفه إذا لم يكن كذلك
// use App\Services\TamaraService; // إذا كنت تستخدمه
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
use Tamara\Notification\Authenticator as TamaraAuthenticator;
use Tamara\Model\Order\Order as TamaraOrder;
use Tamara\Request\Order\AuthoriseOrderRequest as TamaraAuthoriseOrderRequest;
use Tamara\Exception\ForbiddenException as TamaraForbiddenException;
use Tamara\Exception\RequestException as TamaraRequestException;

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
                    $customer->notify(new PaymentFailedNotification($invoice, $reason)); // تم تعديل المُنشئ إذا كان يأخذ سبب فقط
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
                    $admin->notify(new PaymentFailedNotification($invoice, $reason));  // تم تعديل المُنشئ
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
     * يتضمن الآن lockForUpdate لمنع تكرار الدفعات.
     */
    public function handleTamaraWebhook(Request $request)
    {
        Log::channel('daily')->info('Tamara Webhook Received - Full Request Details:', [
            'headers' => $request->headers->all(),
            'content_type' => $request->getContentTypeFormat(),
            'method' => $request->getMethod(),
            'ip_address' => $request->ip(),
            'query_parameters' => $request->query(),
            'form_parameters_or_json' => $request->all(),
            'raw_content' => $request->getContent()
        ]);

        // --- START: تجاوز المصادقة (يجب إزالته في الإنتاج واستخدام المصادقة الفعلية) ---
        // Log::info("Tamara Webhook received. Processing with bypass authentication for now.");
        // $notificationTokenFromConfig = config('services.tamara.notification_token');
        // Log::info('TAMARA_NOTIFICATION_TOKEN from config: ' . $notificationTokenFromConfig);
        // $jwtFromHeader = $request->header('Authorization');
        // $jwtFromQuery = $request->query('tamaraToken');
        // Log::debug('Tamara Auth Details for Webhook:', [
        //     'authorization_header' => $jwtFromHeader ?: 'Not Present',
        //     'tamara_token_query_param' => $jwtFromQuery ?: 'Not Present'
        // ]);
        // // استخلاص البيانات مباشرة من الطلب (لأغراض تجاوز المصادقة فقط)
        // $webhookData = $request->all();
        // $eventType = $webhookData['event_type'] ?? null;
        // $tamaraOrderId = $webhookData['order_id'] ?? null;
        // $resolved_order_reference_id = $webhookData['order_reference_id'] ?? ($webhookData['order_number'] ?? null);
        // $payloadData = $webhookData['data'] ?? []; // افترض أن data قد تكون موجودة أو فارغة
        // $webhookMessage = null; // لا يوجد كائن $webhookMessage حقيقي في وضع التجاوز الكامل
        // --- END: تجاوز المصادقة ---

        // --- START: المصادقة الفعلية (يجب تفعيل هذا في الإنتاج) ---
        $notificationTokenFromConfig = config('services.tamara.notification_token');
        Log::info('TAMARA_NOTIFICATION_TOKEN from config for Webhook: ' . $notificationTokenFromConfig);

        $jwtFromHeader = $request->header('Authorization');
        $jwtFromQuery = $request->query('tamaraToken');
        Log::debug('Tamara Auth Details for Webhook:', [
            'authorization_header' => $jwtFromHeader ?: 'Not Present',
            'tamara_token_query_param' => $jwtFromQuery ?: 'Not Present'
        ]);

        if (empty($notificationTokenFromConfig)) {
            Log::error('Tamara Webhook Error: Missing config (services.tamara.notification_token). Cannot authenticate.');
            return response()->json(['status' => 'error_config_missing_token'], 403);
        }

        $eventType = null; $tamaraOrderId = null; $resolved_order_reference_id = null; $payloadData = null;
        /** @var \Tamara\Model\Notification\NotificationMessage $webhookMessage */
        $webhookMessage = null;

        try {
            $authenticator = new TamaraAuthenticator($notificationTokenFromConfig);
            $webhookMessage = $authenticator->authenticate($request);

            Log::info('Tamara webhook authenticated successfully using Authenticator.');

            $eventType = $webhookMessage->getEventType();
            $tamaraOrderId = $webhookMessage->getOrderId();
            $payloadData = $webhookMessage->getData();

            $resolved_order_reference_id = null;
            if (method_exists($webhookMessage, 'getOrderReferenceId')) {
                $refIdFromMethod = $webhookMessage->getOrderReferenceId();
                if ($refIdFromMethod !== null) { $resolved_order_reference_id = $refIdFromMethod; }
            }
            if ($resolved_order_reference_id === null) {
                $refIdFromInput = $request->input('order_reference_id');
                if ($refIdFromInput !== null) { $resolved_order_reference_id = $refIdFromInput; }
            }
            if ($resolved_order_reference_id === null && isset($payloadData['order_reference_id'])) {
                $resolved_order_reference_id = $payloadData['order_reference_id'];
            }
            if ($resolved_order_reference_id === null && isset($payloadData['order_number'])) {
                $resolved_order_reference_id = $payloadData['order_number'];
            }

            Log::debug('Extracted Webhook Data from Authenticator:', [
                'event_type' => $eventType, 'tamara_order_id' => $tamaraOrderId,
                'resolved_order_reference_id' => $resolved_order_reference_id,
                'payload_data_sample' => Str::limit(json_encode($payloadData), 200)
            ]);

            if (empty($eventType) || empty($tamaraOrderId)) {
                Log::error('Tamara Webhook Error: Missing essential data (event_type or order_id) from webhook message after authentication.');
                return response()->json(['status' => 'success_auth_data_incomplete'], 200);
            }
        } catch (TamaraForbiddenException $e) {
            Log::error('Tamara Webhook Auth Failed (TamaraForbiddenException).', [
                'msg' => $e->getMessage(), 'tamara_token_from_config' => $notificationTokenFromConfig,
                'authorization_header_checked' => $jwtFromHeader ?: 'Not Present',
                'tamara_token_query_param_available' => $jwtFromQuery ?: 'Not Present'
            ]);
            return response()->json(['status' => 'error_auth', 'message' => 'Access denied.'], 403);
        } catch (TamaraRequestException $e) {
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


        // --- Business logic processing starts here ---
        try {
            // تأكد من أن $eventType و $tamaraOrderId و $resolved_order_reference_id لديها قيم
            if (empty($eventType) || empty($tamaraOrderId)) {
                 Log::error("Tamara Webhook: Cannot process business logic due to missing event_type or tamara_order_id even after (bypassed/successful) auth.", [
                    'event_type' => $eventType, 'tamara_order_id' => $tamaraOrderId
                 ]);
                 return response()->json(['status' => 'error_missing_data_for_logic'], 400); // أو 200 إذا كنت لا تريد إعادة المحاولة
            }


            if ($eventType === TamaraOrder::EVENT_TYPE_ORDER_APPROVED) {
                Log::info('Processing Tamara order_approved event.', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);

                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id, $payloadData, $webhookMessage, $request) { // أضفت $request للمعاملات إذا احتجت لـ input
                    
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->with('booking.user', 'booking.service') // جلب العلاقات المطلوبة
                    ->lockForUpdate() // <---  إضافة القفل هنا ---
                    ->first();

                    if (!$invoice) {
                        Log::warning('Webhook: Invoice not found for order_approved.', [
                            'tamara_order_id' => $tamaraOrderId,
                            'attempted_order_reference_id' => $resolved_order_reference_id
                        ]);
                        return;
                    }
                    
                    // التحقق من وجود دفعة مكررة بعد القفل
                    $existingPayment = Payment::where('transaction_id', $tamaraOrderId)
                                            ->where('invoice_id', $invoice->id)
                                            ->exists();

                    if ($existingPayment) {
                        Log::warning("Duplicate payment webhook (or payment already recorded) detected by exists() check AFTER lock. Ignored for order_id.", [
                            'invoice_id' => $invoice->id,
                            'tamara_order_id' => $tamaraOrderId
                        ]);
                        // يمكنك اختيار إرجاع شيء ما أو فقط تسجيل وعدم المتابعة لإنشاء دفعة
                        return; // لا تقم بإنشاء دفعة أو إرسال إشعارات مرة أخرى
                    }

                    // استخلاص المبلغ بشكل آمن
                    $amountPaidInThisTransaction = 0;
                    $tamaraAmount = null;
                    $tamaraCurrency = $invoice->currency ?: 'SAR';

                    if ($webhookMessage && $webhookMessage->getOrder() && method_exists($webhookMessage->getOrder(), 'getTotalAmount') && $webhookMessage->getOrder()->getTotalAmount()) {
                        $tamaraAmountObj = $webhookMessage->getOrder()->getTotalAmount();
                        if ($tamaraAmountObj && method_exists($tamaraAmountObj, 'getAmount')) { $tamaraAmount = $tamaraAmountObj->getAmount(); }
                        if ($tamaraAmountObj && method_exists($tamaraAmountObj, 'getCurrency')) { $tamaraCurrency = $tamaraAmountObj->getCurrency(); }
                    }
                    
                    if ($tamaraAmount === null && isset($payloadData['total_amount']['amount'])) { // تجاوز المصادقة قد يجعل $payloadData هو المصدر
                        $tamaraAmount = $payloadData['total_amount']['amount'];
                        $tamaraCurrency = $payloadData['total_amount']['currency'] ?? $tamaraCurrency;
                    } elseif ($tamaraAmount === null && isset($payloadData['order_amount']['amount'])) {
                        $tamaraAmount = $payloadData['order_amount']['amount'];
                        $tamaraCurrency = $payloadData['order_amount']['currency'] ?? $tamaraCurrency;
                    }

                    if ($tamaraAmount !== null) {
                        $amountPaidInThisTransaction = (float) $tamaraAmount;
                    } else {
                        Log::warning("Could not extract amount from Tamara webhook. Estimating based on invoice status.", ['tamara_order_id' => $tamaraOrderId, 'invoice_id' => $invoice->id]);
                        // منطق التقدير (يجب مراجعته ليكون أكثر دقة بناءً على $invoice->payment_option)
                        if($invoice->payment_option === 'down_payment' && $invoice->status !== Invoice::STATUS_PARTIALLY_PAID && $invoice->status !== Invoice::STATUS_PAID) {
                            $estimatedAmount = $invoice->booking->down_payment_amount > 0 ? $invoice->booking->down_payment_amount : round($invoice->amount / 2, 0);
                             $amountPaidInThisTransaction = $estimatedAmount;
                        } else { // افترض أنه دفع كامل إذا لم يكن عربونًا أو إذا كان العربون مدفوعًا بالفعل
                             $amountPaidInThisTransaction = $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount;
                        }
                    }
                    
                    if ($amountPaidInThisTransaction <= 0.009 && $invoice->status !== Invoice::STATUS_PAID) {
                        Log::warning("Webhook order_approved: Calculated amount to pay is zero or less, but invoice not fully paid. Skipping payment creation to avoid issues.", [
                            'invoice_id' => $invoice->id, 'calculated_amount' => $amountPaidInThisTransaction, 'invoice_status' => $invoice->status
                        ]);
                        // لا تقم بإنشاء دفعة صفر إذا لم تكن الفاتورة مدفوعة بالكامل بالفعل
                    } else {
                         Payment::create([
                            'invoice_id' => $invoice->id,
                            'transaction_id' => $tamaraOrderId,
                            'amount' => $amountPaidInThisTransaction,
                            'currency' => $tamaraCurrency,
                            'status' => 'completed',
                            'payment_gateway' => 'tamara',
                            'payment_details' => json_encode(['tamara_order_id' => $tamaraOrderId, 'event_type' => 'order_approved', 'webhook_payload' => $payloadData]) ?: null,
                        ]);
                        Log::info("Payment record CREATED via webhook for order_approved.", ['invoice_id' => $invoice->id, 'amount' => $amountPaidInThisTransaction]);

                        // تحديث حالة الفاتورة والحجز والإشعارات
                        // ... (الكود من الردود السابقة لتحديث invoice->status, booking->status وإرسال PaymentSuccess و BookingConfirmed) ...
                        // تأكد من أن هذا المنطق يحدث فقط إذا تم إنشاء الدفعة بنجاح
                        $customer = $invoice->booking->user;
                        $originalInvoiceStatus = $invoice->status; // احصل عليها قبل التغيير
                        $newInvoiceStatus = $originalInvoiceStatus;

                        if ($originalInvoiceStatus === Invoice::STATUS_PARTIALLY_PAID) {
                             $newInvoiceStatus = Invoice::STATUS_PAID;
                        } elseif (in_array($originalInvoiceStatus, [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING, Invoice::STATUS_FAILED, Invoice::STATUS_CANCELLED, Invoice::STATUS_EXPIRED])) {
                             $paymentOptionCleaned = trim(strtolower($invoice->payment_option ?? 'full'));
                             $newInvoiceStatus = ($paymentOptionCleaned === 'down_payment') ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_PAID;
                        }

                        if ($newInvoiceStatus !== $originalInvoiceStatus || $invoice->paid_at === null) {
                            $invoice->status = $newInvoiceStatus;
                            if ($invoice->paid_at === null || $newInvoiceStatus === Invoice::STATUS_PAID) { // ضع تاريخ الدفع إذا لم يكن موجودًا أو إذا أصبحت الفاتورة مدفوعة بالكامل
                                $invoice->paid_at = Carbon::now();
                            }
                            $invoice->save();
                            Log::info("Invoice status updated to '{$newInvoiceStatus}' via order_approved webhook.", ['invoice_id' => $invoice->id]);
                        }

                        // إرسال إشعارات نجاح الدفع
                        if ($customer) {
                            $customer->notify(new PaymentSuccessNotification($invoice, $amountPaidInThisTransaction, $tamaraCurrency));
                            Log::info("PaymentSuccessNotification queued for CUSTOMER {$customer->id} via order_approved webhook.");
                        }
                        $admins = User::where('is_admin', true)->get(); // لا تستثني المدير الحالي هنا، الإشعار نفسه قد يقرر
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
                        // ... نهاية منطق الإشعارات ...

                        // استدعاء Authorise API
                        try {
                            $apiUrlAuth = config('services.tamara.url'); $apiTokenAuth = config('services.tamara.token'); $timeoutAuth = config('services.tamara.request_timeout', 10);
                            if(empty($apiUrlAuth) || empty($apiTokenAuth)) {
                                Log::error('Tamara Authorise API config missing (URL or Token). Skipping Authorise call.');
                            } else {
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
                            }
                        } catch (Throwable $authError) {
                            Log::error('Exception during Tamara Authorise API call via webhook (order_approved).', ['tamara_order_id' => $tamaraOrderId, 'error' => $authError->getMessage()]);
                        }
                    }


                }); // نهاية DB::transaction

            } elseif ($eventType === 'order_authorised') { // <--- إضافة معالجة لـ order_authorised
                Log::info('Processing Tamara order_authorised event.', ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                // هنا، الطلب تمت المصادقة عليه من قبل تمارا.
                // قد لا تحتاج إلى إنشاء سجل دفعة آخر إذا كان order_approved قد قام بذلك بالفعل.
                // ولكن يمكنك تحديث حالة الفاتورة/الحجز إذا لزم الأمر، أو فقط تسجيل الحدث.
                DB::transaction(function () use ($tamaraOrderId, $resolved_order_reference_id) {
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->lockForUpdate() // قفل الفاتورة
                    ->first();

                    if (!$invoice) {
                        Log::warning('Webhook: Invoice not found for order_authorised.', ['tamara_order_id' => $tamaraOrderId]);
                        return;
                    }

                    Log::info("Tamara order_authorised event received for Invoice ID {$invoice->id}. Current status: {$invoice->status}. No payment record created from this event if already handled by order_approved.");

                    // يمكنك هنا التأكد من أن الفاتورة في حالة مناسبة (مثلاً paid أو partially_paid)
                    // وأن الحجز confirmed إذا لم يكن كذلك بالفعل.
                    // ولكن تجنب تكرار الإشعارات إذا تم إرسالها من order_approved.

                    // مثال: تأكيد حالة الحجز إذا لم تكن مؤكدة والفاتورة مدفوعة جزئيًا/كليًا
                    if ($invoice->booking && $invoice->booking->status !== Booking::STATUS_CONFIRMED &&
                        in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                        $oldBookingStatus = $invoice->booking->status;
                        $invoice->booking->status = Booking::STATUS_CONFIRMED;
                        $invoice->booking->save();
                        Log::info('Booking status confirmed via order_authorised webhook as it was not confirmed yet.', ['booking_id' => $invoice->booking->id, 'old_status' => $oldBookingStatus]);
                        // فكر جيدًا ما إذا كنت تريد إرسال إشعار BookingConfirmed من هنا إذا لم يتم إرساله من order_approved
                    }
                });


            } elseif (in_array($eventType, [TamaraOrder::EVENT_TYPE_ORDER_DECLINED, TamaraOrder::EVENT_TYPE_ORDER_CANCELED, TamaraOrder::EVENT_TYPE_ORDER_EXPIRED])) {
                // ... (منطق معالجة هذه الأحداث كما كان، مع التأكد من استخدام $resolved_order_reference_id) ...
                 Log::warning("Processing Tamara {$eventType} event.", ['tamara_order_id' => $tamaraOrderId, 'order_reference_id' => $resolved_order_reference_id]);
                DB::transaction(function() use ($eventType, $resolved_order_reference_id, $tamaraOrderId) { // مرر $resolved_order_reference_id
                    $invoice = Invoice::where(function ($query) use ($resolved_order_reference_id, $tamaraOrderId) {
                        if ($resolved_order_reference_id) {
                            $query->where('invoice_number', $resolved_order_reference_id);
                        }
                        $query->orWhere('payment_gateway_ref', $tamaraOrderId);
                    })
                    ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_CANCELLED])
                    ->with('booking.user')
                    ->first();
                    // ... (بقية المنطق كما كان)
                });
            } else {
                Log::info("Received unhandled Tamara webhook event type: {$eventType}", ['tamara_order_id' => $tamaraOrderId, 'payload_data' => $payloadData]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (Throwable $e) {
            Log::error('Unhandled exception after webhook processing logic.', [
                'event_type' => $eventType ?? 'unknown',
                'tamara_order_id' => $tamaraOrderId ?? 'unknown',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), 1500)
            ]);
            return response()->json(['status' => 'success_but_internal_error_logged'], 200);
        }
    }

    // ... (بقية دوال الكلاس مثل retryTamaraPayment) ...
    public function retryTamaraPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        // ... (الكود كما هو)
    }
}
