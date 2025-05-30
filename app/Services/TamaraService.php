<?php

// المسار: app/Services/TamaraService.php

namespace App\Services;

use App\Models\Invoice;
// --- MODIFICATION START: Import Setting model ---
use App\Models\Setting;
// --- MODIFICATION END ---
use Illuminate\Support\Facades\Config; // سيبقى مؤقتاً كمرجع للقيم الافتراضية إذا لم توجد في DB
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// use Illuminate\Http\Request; // غير مستخدم مباشرة هنا

class TamaraService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected bool $isConfigured = false; // لمعرفة إذا تم تحميل الإعدادات بنجاح

    /**
     * Constructor لتحضير بيانات الربط.
     */
    public function __construct()
    {
        // --- MODIFICATION START: Load settings from database ---
        $this->apiUrl = Setting::where('key', 'tamara_api_url')->value('value');
        $this->apiKey = Setting::where('key', 'tamara_api_token')->value('value');

        // استخدام قيم افتراضية إذا لم يتم العثور على الإعدادات في قاعدة البيانات
        // أو إذا كانت فارغة، مع تسجيل تحذير.
        if (empty($this->apiUrl)) {
            $this->apiUrl = config('services.tamara.url', 'https://api-sandbox.tamara.co'); // قيمة افتراضية لـ sandbox
            Log::warning("TamaraService: 'tamara_api_url' not found in database settings. Using default or config fallback: " . $this->apiUrl);
        }

        if (empty($this->apiKey)) {
            // لا يوجد قيمة افتراضية آمنة لـ API Key، يجب أن يكون معيناً
            Log::error('TamaraService Error: API Key (tamara_api_token) is missing from database settings AND config/services.php! Tamara integration will likely fail.');
            $this->isConfigured = false;
        } else {
            $this->isConfigured = true;
            Log::info('TamaraService instantiated. API URL: ' . $this->apiUrl . ', API Key Loaded: ' . (empty($this->apiKey) ? 'NO' : 'YES'));
        }
        // --- MODIFICATION END ---
    }

    /**
     * يبدأ عملية الدفع مع تمارا للمبلغ المحدد ويُرجع بيانات الاستجابة أو null عند الفشل.
     *
     * @param Invoice $invoice الفاتورة المرتبطة.
     * @param float $amountToPay المبلغ الفعلي المطلوب دفعه في هذه العملية (كامل أو عربون).
     * @param string $paymentOption نوع الدفعة ('full' أو 'down_payment') لتحديث الوصف.
     * @return array|null مصفوفة ['checkout_url' => string, 'order_id' => string] أو null.
     */
    public function initiateCheckout(Invoice $invoice, float $amountToPay, string $paymentOption = 'full'): ?array
    {
        if (!$this->isConfigured || empty($this->apiKey) || empty($this->apiUrl)) {
             Log::error("Tamara initiateCheckout Error: Service is not configured (API URL or Key missing) for Invoice ID: {$invoice->id}");
             return null;
        }

        if ($amountToPay <= 0.009) { // استخدام حد صغير بدلاً من 0
             Log::error("Tamara initiateCheckout Error: Amount to pay must be positive. Received: {$amountToPay} for Invoice ID: {$invoice->id}");
             return null;
        }

        try {
            $invoice->loadMissing(['booking.user', 'booking.service']);
            $booking = $invoice->booking;
            $user = $booking?->user;
            $service = $booking?->service;

            if (!$booking || !$user || !$service) {
                 Log::error("Tamara initiateCheckout Error: Missing related data (booking, user, or service) for Invoice ID: {$invoice->id}");
                 return null;
            }

            $successUrl = route('tamara.success', ['invoice' => $invoice->id]);
            $failureUrl = route('tamara.failure', ['invoice' => $invoice->id]);
            
            // قراءة رابط الـ Webhook من الإعدادات أو استخدام قيمة ثابتة إذا لم يتغير
            $notificationUrlSetting = Setting::where('key', 'tamara_webhook_url')->value('value'); // افترض أن هذا مفتاح جديد إذا أردت جعله ديناميكياً
            $notificationUrl = !empty($notificationUrlSetting) ? $notificationUrlSetting : url('/api/tamara/Webhook'); // استخدام helper url() لضمان الرابط الصحيح

            Log::info("Tamara URLs for Invoice ID {$invoice->id}", [
                 'success_url' => $successUrl,
                 'failure_url' => $failureUrl,
                 'notification_url' => $notificationUrl
            ]);

            $userName = $user->name ?: 'Unknown Customer';
            $firstName = Str::before($userName, ' ') ?: $userName;
            $lastName = Str::after($userName, ' ') ?: ($firstName !== $userName ? $userName : 'Customer'); // تحسين بسيط لاسم العائلة
            if (empty(trim($lastName)) || $lastName === $firstName) { $lastName = 'Customer'; }

            $phone = $user->mobile_number;
            if ($phone && !str_starts_with($phone, '+')) {
                $phone = preg_replace('/[\s-]+/', '', $phone);
                if (str_starts_with($phone, '05') && strlen($phone) == 10) { $phone = '+966' . substr($phone, 1); }
                elseif (strlen($phone) == 9 && str_starts_with($phone, '5')) { $phone = '+966' . $phone; }
                // أضف المزيد من قواعد التنسيق إذا لزم الأمر أو استخدم مكتبة
            }
            if(empty($phone) || !preg_match('/^\+966\d{9}$/', $phone)){
                Log::error("Tamara Payload Error: Phone number is missing or invalid ('{$phone}') for user {$user->id} / Invoice {$invoice->id}. Cannot proceed.");
                return null;
            }

            $email = $user->email;
            if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                Log::error("Tamara Payload Error: Email is missing or invalid ('{$email}') for user {$user->id} / Invoice {$invoice->id}. Cannot proceed.");
                return null;
            }

            $amountForTamara = round($amountToPay, 2);
            $currency = $invoice->currency ?: 'SAR';
            $orderReferenceId = (string) $invoice->invoice_number;
            $orderNumber = $invoice->invoice_number ?: 'BOOK-' . $booking->id . '-' . time(); // إضافة time لجعله فريداً أكثر
            $serviceName = $service->{'name_' . app()->getLocale()} ?? $service->name_ar;

            $shippingAddress = [
                'first_name' => $firstName, 'last_name' => $lastName,
                'line1' => $booking->event_location ?: 'N/A', 'line2' => null,
                'region' => 'Riyadh Region', 'city' => 'Riyadh', // جعلها ديناميكية من إعدادات الموقع أو بيانات العميل إذا أمكن
                'country_code' => 'SA', 'phone_number' => $phone
            ];

            $descriptionPrefix = ($paymentOption === 'down_payment') ? 'دفعة أولى لحجز خدمة: ' : 'دفع قيمة خدمة: ';
            $description = $descriptionPrefix . $serviceName . ' (فاتورة #' . $orderReferenceId . ')';

            $payload = [
                'order_reference_id' => $orderReferenceId,
                'order_number'=> $orderNumber,
                'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency],
                'description' => $description,
                'country_code' => 'SA',
                'payment_type' => 'PAY_BY_INSTALMENTS', // أو PAY_LATER حسب ما تدعمه تمارا وما تفضله
                'instalments' => 3, // إذا كان PAY_BY_INSTALMENTS، هذا الحقل مطلوب عادةً
                'items' => [[
                    'reference_id' => (string) $service->id,
                    'type' => 'Digital', 'name' => $serviceName,
                    'sku' => (string) $service->id, 'quantity' => 1,
                    'unit_price' => ['amount' => $amountForTamara, 'currency' => $currency],
                    'discount_amount' => ['amount' => 0.00, 'currency' => $currency], // الخصم يجب أن يكون مطبقاً على $amountForTamara بالفعل
                    'tax_amount' => ['amount' => 0.00, 'currency' => $currency],
                    'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency],
                ]],
                'consumer' => [
                    'first_name' => $firstName, 'last_name' => $lastName,
                    'phone_number' => $phone, 'email' => $email,
                ],
                'shipping_address' => $shippingAddress,
                'billing_address' => $shippingAddress, // استخدام نفس العنوان للفواتير مبدئياً
                'merchant_url' => [
                    'success' => $successUrl, 'failure' => $failureUrl,
                    'cancel' => $failureUrl, 'notification' => $notificationUrl
                ],
                'platform' => 'FatimaAliBookingSystem-' . config('app.env'),
                'locale' => app()->getLocale() == 'ar' ? 'ar_SA' : 'en_SA',
                 // يمكن إضافة حقول إضافية مثل risk_assessment إذا كانت تمارا تطلبها
            ];
            
            // إزالة الحقول التي قيمتها null إذا كان الـ API لا يقبلها
            // $payload['shipping_address'] = array_filter($payload['shipping_address']);
            // $payload['billing_address'] = array_filter($payload['billing_address']);
            // $payload['consumer'] = array_filter($payload['consumer']);


            Log::info("Tamara Payload for Invoice ID {$invoice->id} (Amount: {$amountForTamara}): ", $payload);

            $checkoutEndpoint = rtrim($this->apiUrl, '/') . '/checkout';
            $requestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? config('services.tamara.request_timeout', 30);


            $response = Http::withToken($this->apiKey)
                             ->timeout($requestTimeout)
                             ->post($checkoutEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                 if (!empty($responseData['checkout_url']) && !empty($responseData['order_id'])) {
                     Log::info("Tamara checkout initiated successfully for Invoice ID: {$invoice->id}. Order ID: " . $responseData['order_id'] . ". Amount: {$amountForTamara}");
                     return [
                         'checkout_url' => $responseData['checkout_url'],
                         'order_id' => $responseData['order_id']
                     ];
                 } else {
                     Log::error("Tamara initiateCheckout Error: Checkout URL or Order ID not found in successful response for Invoice ID: {$invoice->id}. Response: " . $response->body());
                     return null;
                 }
            } else {
                 $errorBody = $response->body();
                 $decodedError = json_decode($errorBody, true);
                 Log::error("Tamara initiateCheckout Request Failed for Invoice ID: {$invoice->id}.", [
                    'status' => $response->status(),
                    'response_body' => $errorBody,
                    'decoded_errors' => $decodedError['errors'] ?? ($decodedError['message'] ?? null),
                    'sent_payload_summary' => [ // لتجنب تسجيل بيانات حساسة كثيرة
                        'order_reference_id' => $payload['order_reference_id'],
                        'total_amount' => $payload['total_amount'],
                        'consumer_email' => $payload['consumer']['email'] ?? null
                    ]
                 ]);
                 return null;
            }

        } catch (\Exception $e) {
             Log::error("Tamara initiateCheckout Exception for Invoice ID: {$invoice->id}. Message: " . $e->getMessage(), ['exception_class' => get_class($e), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
             return null;
        }
    }


    /**
     * دالة التحقق من الـ Webhook (غير مستخدمة حالياً للتحقق الفعلي، يتم في PaymentController)
     * يمكن استخدامها كمرجع إذا أردت نقل منطق التحقق إلى هنا لاحقاً.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        Log::warning('TamaraService::verifyWebhookSignature is informational only. Actual verification should occur in the webhook handler (PaymentController) using Tamara SDK Authenticator if not bypassed.');
        
        // المنطق الفعلي للتحقق باستخدام Tamara SDK Authenticator يجب أن يكون في PaymentController
        // أو يتم استدعاؤه من هناك.
        // هذا مجرد هيكل توضيحي:
        /*
        $notificationToken = Setting::where('key', 'tamara_notification_token')->value('value');
        if (empty($notificationToken)) {
            Log::error('Tamara Webhook Verification: Notification Token is not set in DB settings.');
            return false; // لا يمكن التحقق بدون توكن
        }
        try {
            $authenticator = new \Tamara\Notification\Authenticator($notificationToken);
            // $request->getContent() للحصول على الجسم الخام للطلب
            // $request->header('TAMARA-SIGNATURE') للحصول على التوقيع من الهيدر
            $authenticator->authenticate($request->getContent(), $request->header('TAMARA-SIGNATURE'));
            Log::info('Tamara Webhook: Signature verified successfully.');
            return true;
        } catch (\Tamara\Exception\InvalidSignatureException $e) {
            Log::error('Tamara Webhook: Invalid signature.', ['message' => $e->getMessage()]);
            return false;
        } catch (\Exception $e) {
            Log::error('Tamara Webhook: Error during signature verification.', ['message' => $e->getMessage()]);
            return false;
        }
        */
        return true; // إذا كان التجاوز مفعلاً، دائماً أرجع true هنا
    }

}
