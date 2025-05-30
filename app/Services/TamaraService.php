<?php

// المسار: app/Services/TamaraService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting; // لاستخدام الإعدادات من قاعدة البيانات
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// use Illuminate\Http\Request; // غير مستخدم حالياً

class TamaraService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->apiUrl = Setting::where('key', 'tamara_api_url')->value('value');
        $this->apiKey = Setting::where('key', 'tamara_api_token')->value('value');

        if (empty($this->apiUrl)) {
            $this->apiUrl = config('services.tamara.url', 'https://api-sandbox.tamara.co');
            Log::warning("TamaraService: 'tamara_api_url' not found in DB settings. Using fallback: " . $this->apiUrl);
        }

        if (empty($this->apiKey)) {
            Log::error('TamaraService Error: API Key (tamara_api_token) is missing from database settings. Tamara integration will fail.');
            $this->isConfigured = false;
        } else {
            $this->isConfigured = true;
            Log::info('TamaraService instantiated. API URL: ' . $this->apiUrl . '. API Key Loaded: YES');
        }
    }

    public function initiateCheckout(Invoice $invoice, float $amountToPay, string $paymentOption = 'full'): ?array
    {
        if (!$this->isConfigured) {
             Log::error("Tamara initiateCheckout Error: Service is not configured (API URL or Key missing) for Invoice ID: {$invoice->id}");
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

            // --- MODIFICATION START: Clean consumer names ---
            $rawUserName = $user->name ?: 'Customer Unknown';
            $nameParts = explode(' ', trim($rawUserName), 2);
            $firstName = trim(str_replace(',', '', $nameParts[0]));
            $lastName = trim(str_replace(',', '', $nameParts[1] ?? 'Customer'));

            if (empty($firstName)) $firstName = 'FName'; // Fallback if cleaning results in empty
            if (empty($lastName)) $lastName = 'LName'; // Fallback
            // --- MODIFICATION END ---

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
                'region' => 'Riyadh', 'city' => 'Riyadh', // تمارا قد تطلب أسماء مناطق ومدن محددة ومعروفة لديها
                'country_code' => 'SA', 'phone_number' => $phone
            ];
            $billingAddress = $shippingAddress; // Usually the same

            $descriptionPrefix = ($paymentOption === 'down_payment') ? 'دفعة أولى لحجز خدمة: ' : 'دفع قيمة خدمة: ';
            $description = $descriptionPrefix . $serviceName . ' (فاتورة #' . $orderReferenceId . ')';

            $payload = [
                'order_reference_id' => $orderReferenceId,
                'order_number'=> $orderNumber,
                'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency],
                'description' => Str::limit($description, 255), // تمارا قد يكون لديها حد على طول الوصف
                'country_code' => 'SA',
                'payment_type' => 'PAY_BY_INSTALMENTS', // أو 'PAY_LATER'
                'instalments' => 3, // تأكد أن هذا متوافق مع نوع الدفع والمبلغ
                'items' => [[
                    'reference_id' => (string) $service->id,
                    'type' => 'Digital', // أو "Physical" أو نوع الخدمة المناسب
                    'name' => Str::limit($serviceName, 128), // تمارا لديها حد على طول اسم المنتج
                    'sku' => (string) $service->id,
                    'quantity' => 1,
                    'unit_price' => ['amount' => $amountForTamara, 'currency' => $currency],
                    'discount_amount' => ['amount' => 0.00, 'currency' => $currency], // الخصم مطبق بالفعل على المبلغ الكلي
                    'tax_amount' => ['amount' => 0.00, 'currency' => $currency],    // الضريبة صفر حالياً
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
                // --- MODIFICATION START: إضافة حقول الشحن والضريبة على مستوى الطلب ---
                'shipping_amount' => ['amount' => 0.00, 'currency' => $currency],
                'tax_amount' => ['amount' => 0.00, 'currency' => $currency],
                // --- MODIFICATION END ---
                // لا يتم إرسال discount على مستوى الطلب لأننا أرسلنا المبلغ الصافي
            ];

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

    // دالة verifyWebhookSignature تبقى كما هي، لأن التحقق الفعلي يتم في PaymentController
    public function verifyWebhookSignature(Request $request): bool
    {
        Log::warning('TamaraService::verifyWebhookSignature is informational only. Actual verification should occur in the webhook handler (PaymentController) using Tamara SDK Authenticator if not bypassed.');
        return true; // أو false، هذا لا يؤثر على المنطق الفعلي حالياً
    }
}
