<?php

// المسار: app/Services/TamaraService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// use Tamara\Request\Order\CaptureOrderRequest; // بناءً على SDK، قد يكون هذا هو الاسم الصحيح
use Tamara\Request\Payment\CaptureRequest; // أو هذا، تحقق من SDK

class TamaraService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected bool $isConfigured = false;
    protected int $requestTimeout;


    public function __construct()
    {
        $this->apiUrl = Setting::where('key', 'tamara_api_url')->value('value');
        $this->apiKey = Setting::where('key', 'tamara_api_token')->value('value');
        $this->requestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? config('services.tamara.request_timeout', 30);


        if (empty($this->apiUrl)) {
            $this->apiUrl = config('services.tamara.url', 'https://api-sandbox.tamara.co');
            Log::warning("TamaraService: 'tamara_api_url' not found in database settings. Using fallback: " . $this->apiUrl);
        }

        if (empty($this->apiKey)) {
            Log::error('TamaraService Error: API Key (tamara_api_token) is missing from database settings. Tamara integration will likely fail.');
            $this->isConfigured = false;
        } else {
            $this->isConfigured = true;
            Log::info('TamaraService instantiated. API URL: ' . $this->apiUrl . '. API Key Loaded: YES');
        }
    }

    // ... دالة initiateCheckout تبقى كما هي ...
    public function initiateCheckout(Invoice $invoice, float $amountToPay, string $paymentOption = 'full'): ?array
    {
        if (!$this->isConfigured) {
             Log::error("Tamara initiateCheckout Error: Service is not configured (API URL or Key missing from DB) for Invoice ID: {$invoice->id}");
             return null;
        }

        if ($amountToPay <= 0.009) {
             Log::error("Tamara initiateCheckout Error: Amount to pay must be positive. Received: {$amountToPay} for Invoice ID: {$invoice->id}");
             return null;
        }

        try {
            $invoice->loadMissing(['booking.user', 'booking.service']);
            $booking = $invoice->booking;
            $user = $booking?->user;
            $service = $booking?->service;

            if (!$booking || !$user || !$service) {
                 Log::error("Tamara initiateCheckout Error: Missing related data for Invoice ID: {$invoice->id}");
                 return null;
            }

            $successUrl = route('tamara.success', ['invoice' => $invoice->id]);
            $failureUrl = route('tamara.failure', ['invoice' => $invoice->id]);
            
            $notificationUrlSetting = Setting::where('key', 'tamara_webhook_url')->value('value');
            $notificationUrl = !empty($notificationUrlSetting) ? $notificationUrlSetting : url('/api/tamara/Webhook');

            Log::info("Tamara URLs for Invoice ID {$invoice->id}", compact('successUrl', 'failureUrl', 'notificationUrl'));

            $rawUserName = trim($user->name ?: 'Customer Unknown');
            $nameParts = explode(' ', $rawUserName, 2);
            
            $firstName = trim(str_replace(',', '', $nameParts[0]));
            $lastName = isset($nameParts[1]) ? trim(str_replace(',', '', $nameParts[1])) : '';

            if (empty($firstName)) { 
                $firstName = 'FName'; 
                if(empty($lastName) && $rawUserName !== 'Customer Unknown' && !empty($rawUserName) && $rawUserName !== $firstName) {
                    $lastName = $rawUserName; 
                }
            }
            if (empty($lastName)) {
                $lastName = ($firstName !== 'FName') ? 'LName' : 'Customer';
            }

            $phone = $user->mobile_number;
            if ($phone && !str_starts_with($phone, '+')) {
                $phone = preg_replace('/[\s-]+/', '', $phone);
                if (str_starts_with($phone, '05') && strlen($phone) == 10) { $phone = '+966' . substr($phone, 1); }
                elseif (strlen($phone) == 9 && str_starts_with($phone, '5')) { $phone = '+966' . $phone; }
            }
            if(empty($phone) || !preg_match('/^\+966\d{9}$/', $phone)){
                Log::error("Tamara Payload Error: Phone number invalid ('{$phone}') for user {$user->id} / Invoice {$invoice->id}.");
                return null;
            }

            $email = $user->email;
            if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
                Log::error("Tamara Payload Error: Email invalid ('{$email}') for user {$user->id} / Invoice {$invoice->id}.");
                return null;
            }

            $amountForTamara = round($amountToPay, 2);
            $currency = $invoice->currency ?: 'SAR';
            $orderReferenceId = (string) $invoice->invoice_number;
            $orderNumber = $invoice->invoice_number ?: 'BOOK-' . $booking->id . '-' . time();
            $serviceName = $service->{'name_' . app()->getLocale()} ?? $service->name_ar;

            $shippingAddress = [
                'first_name' => $firstName, 'last_name' => $lastName,
                'line1' => $booking->event_location ?: 'N/A', 'line2' => null,
                'region' => 'Riyadh', 'city' => 'Riyadh',
                'country_code' => 'SA', 'phone_number' => $phone
            ];
            $billingAddress = $shippingAddress;

            $descriptionPrefix = ($paymentOption === 'down_payment') ? 'دفعة أولى لحجز خدمة: ' : 'دفع قيمة خدمة: ';
            $description = Str::limit($descriptionPrefix . $serviceName . ' (فاتورة #' . $orderReferenceId . ')', 255);

            $payload = [
                'order_reference_id' => $orderReferenceId,
                'order_number'=> $orderNumber,
                'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency],
                'description' => $description,
                'country_code' => 'SA',
                'payment_type' => 'PAY_BY_INSTALMENTS',
                'instalments' => 3,
                'items' => [[
                    'reference_id' => (string) $service->id,
                    'type' => 'Digital',
                    'name' => Str::limit($serviceName, 128),
                    'sku' => (string) $service->id, 'quantity' => 1,
                    'unit_price' => ['amount' => $amountForTamara, 'currency' => $currency],
                    'discount_amount' => ['amount' => 0.00, 'currency' => $currency],
                    'tax_amount' => ['amount' => 0.00, 'currency' => $currency],
                    'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency],
                ]],
                'consumer' => [
                    'first_name' => $firstName, 'last_name' => $lastName,
                    'phone_number' => $phone, 'email' => $email,
                ],
                'shipping_address' => $shippingAddress,
                'billing_address' => $billingAddress,
                'merchant_url' => [
                    'success' => $successUrl, 'failure' => $failureUrl,
                    'cancel' => $failureUrl, 'notification' => $notificationUrl
                ],
                'platform' => 'FatimaAliBookingSystem-' . config('app.env'),
                'locale' => app()->getLocale() == 'ar' ? 'ar_SA' : 'en_SA',
                'shipping_amount' => ['amount' => 0.00, 'currency' => $currency],
                'tax_amount' => ['amount' => 0.00, 'currency' => $currency],
            ];

            Log::info("Tamara Payload for Invoice ID {$invoice->id} (Amount: {$amountForTamara}): ", ['payload' => $payload]);

            $checkoutEndpoint = rtrim($this->apiUrl, '/') . '/checkout';
            
            $response = Http::withToken($this->apiKey)
                             ->timeout($this->requestTimeout)
                             ->post($checkoutEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                 if (!empty($responseData['checkout_url']) && !empty($responseData['order_id'])) {
                     Log::info("Tamara checkout initiated successfully for Invoice ID: {$invoice->id}.", [
                        'tamara_order_id' => $responseData['order_id'],
                        'tamara_checkout_id' => $responseData['checkout_id'] ?? null,
                        'tamara_initial_status' => $responseData['status'] ?? null,
                        'checkout_url_received' => !empty($responseData['checkout_url']),
                        'amount_for_tamara' => $amountForTamara
                    ]);
                     return [
                         'checkout_url' => $responseData['checkout_url'],
                         'order_id' => $responseData['order_id']
                     ];
                 } else {
                     Log::error("Tamara initiateCheckout Error: Checkout URL or Order ID not found in successful response for Invoice ID: {$invoice->id}. Response: " . $response->body(), ['response_data' => $responseData]);
                     return null;
                 }
            } else {
                 $errorBody = $response->body();
                 $decodedError = json_decode($errorBody, true);
                 Log::error("Tamara initiateCheckout Request Failed for Invoice ID: {$invoice->id}.", [
                    'status' => $response->status(),
                    'response_body' => $errorBody,
                    'decoded_errors' => $decodedError['errors'] ?? ($decodedError['message'] ?? null),
                    'sent_payload_summary' => [
                        'order_reference_id' => $payload['order_reference_id'],
                        'total_amount' => $payload['total_amount'],
                        'consumer_email' => $payload['consumer']['email'] ?? null,
                        'consumer_name_sent' => $firstName . ' ' . $lastName
                    ]
                 ]);
                 return null;
            }
        } catch (\Exception $e) {
             Log::error("Tamara initiateCheckout Exception for Invoice ID: {$invoice->id}. Message: " . $e->getMessage(), ['exception_class' => get_class($e), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
             return null;
        }
    }


    // --- MODIFICATION START: Add capturePayment method ---
    /**
     * Capture an authorized Tamara payment.
     *
     * @param string $tamaraOrderId The Tamara order ID.
     * @param float $captureAmount The amount to capture.
     * @param string $currency The currency (e.g., "SAR").
     * @param array $items Array of items being captured (optional, structure depends on Tamara's requirements for capture).
     * @param array $shippingInfo Shipping information (optional).
     * @return array|null Response from Tamara API or null on failure.
     */
    public function capturePayment(string $tamaraOrderId, float $captureAmount, string $currency = 'SAR', array $items = [], array $shippingInfo = []): ?array
    {
        if (!$this->isConfigured) {
            Log::error("Tamara capturePayment Error: Service is not configured (API URL or Key missing). Tamara Order ID: {$tamaraOrderId}");
            return null;
        }

        if ($captureAmount <= 0) {
            Log::error("Tamara capturePayment Error: Capture amount must be positive. Received: {$captureAmount} for Tamara Order ID: {$tamaraOrderId}");
            return null;
        }

        $captureEndpoint = rtrim($this->apiUrl, '/') . '/payments/capture'; // 

        // بناء الحمولة بناءً على توثيق تمارا لـ Capture API
        // قد تحتاج إلى تفاصيل إضافية مثل shipping_info, discount_amount على مستوى الطلب, ইত্যাদি.
        // التوثيق الذي أرفقته يظهر بنية مشابهة لـ checkout مع بعض الاختلافات.
        $payload = [
            'order_id' => $tamaraOrderId,
            'total_amount' => [
                'amount' => round($captureAmount, 2),
                'currency' => $currency,
            ],
            // 'items' => $items, // قد تكون اختيارية أو مطلوبة بتفاصيل محددة (اسم، كمية، سعر وحدة، إلخ)
            // 'shipping_amount' => ['amount' => 0.00, 'currency' => $currency], // إذا كان هناك مبلغ شحن محدد لهذه العملية
            // 'tax_amount' => ['amount' => 0.00, 'currency' => $currency],      // إذا كان هناك مبلغ ضريبة محدد لهذه العملية
            // 'discount_amount' => ['amount' => 0.00, 'currency' => $currency], // إذا كان هناك خصم محدد لهذه العملية
            // 'shipping_info' => $shippingInfo, // مثل: ['shipped_at' => now()->toIso8601String(), 'shipping_company' => 'InHouse/Self']
        ];
        
        // إذا كانت $items فارغة، قد تحتاج لإرسال تفاصيل البند الرئيسي على الأقل كما في initiateCheckout
        // أو كما يتطلب Capture API. التوثيق يظهر 'items' في مثال الطلب. 
        // يجب التأكد من أن بنية items المرسلة هنا تتوافق مع ما يتوقعه Capture API.
        // إذا كانت نفس بنية items من checkout، يمكنك إعادة استخدامها أو جزء منها.
        // للمثال، سنفترض إرسال حمولة مبسطة إذا لم يتم توفير items:
        if (empty($items)) {
             // قد تحتاج للحصول على تفاصيل الخدمة المرتبطة بالـ $tamaraOrderId (عبر الفاتورة/الحجز)
             // لتعبئة اسم المنتج ونوعه. هنا مثال مبسط:
            $payload['items'] = [[
                'name' => 'Service Fulfillment', // اسم عام
                'reference_id' => 'capture_item_generic',
                'sku' => 'CAP-GEN',
                'type' => 'Digital', // أو 'Physical'
                'quantity' => 1,
                'total_amount' => ['amount' => round($captureAmount, 2), 'currency' => $currency],
                // قد تحتاج لإضافة unit_price, discount_amount, tax_amount لكل بند إذا لزم الأمر
            ]];
        } else {
            $payload['items'] = $items;
        }
        
        // مثال لإضافة shipping_info إذا لزم الأمر
        if (empty($shippingInfo)) {
            $payload['shipping_info'] = [
                'shipped_at' => Carbon::now()->toIso8601String(),
                'shipping_company' => 'Fulfilled In Place', // أو اسم شركة الشحن
                // 'tracking_number' => null,
                // 'tracking_url' => null,
            ];
        } else {
            $payload['shipping_info'] = $shippingInfo;
        }


        Log::info("Tamara Capture Payment Request for Order ID {$tamaraOrderId}: ", ['payload' => $payload]);

        try {
            $response = Http::withToken($this->apiKey)
                             ->timeout($this->requestTimeout)
                             ->post($captureEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Tamara Capture Payment successful for Order ID: {$tamaraOrderId}", ['response_data' => $responseData]);
                return $responseData; // يحتوي على capture_id, status, etc.
            } else {
                $errorBody = $response->body();
                $decodedError = json_decode($errorBody, true);
                Log::error("Tamara Capture Payment Request Failed for Order ID: {$tamaraOrderId}", [
                    'status' => $response->status(),
                    'response_body' => $errorBody,
                    'decoded_errors' => $decodedError['errors'] ?? ($decodedError['message'] ?? null),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Tamara Capture Payment Exception for Order ID: {$tamaraOrderId}. Message: " . $e->getMessage(), ['exception_class' => get_class($e)]);
            return null;
        }
    }
    // --- MODIFICATION END ---

    public function verifyWebhookSignature(Request $request): bool
    {
        Log::warning('TamaraService::verifyWebhookSignature called, but actual verification logic is in PaymentController.');
        $bypassVerification = filter_var(
            Setting::where('key', 'tamara_webhook_verification_bypass')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );
        if($bypassVerification) return true;

        $notificationToken = Setting::where('key', 'tamara_notification_token')->value('value');
        if (empty($notificationToken)) {
            Log::error('Tamara Webhook Verification in Service: Notification Token is not set.');
            return false;
        }
        try {
            $authenticator = new \Tamara\Notification\Authenticator($notificationToken);
            $signature = $request->header('Authorization');
            if ($signature && Str::startsWith(strtolower($signature), 'bearer ')) {
                $signature = Str::substr($signature, 7);
            } else {
                $signature = $request->query('tamaraToken');
            }
            if(empty($signature)) return false;

            $authenticator->authenticate($request->getContent(), $signature);
            return true;
        } catch (\Exception $e) {
            Log::error('Tamara Webhook Verification in Service failed: ' . $e->getMessage());
            return false;
        }
    }
}
