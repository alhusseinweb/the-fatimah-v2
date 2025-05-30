<?php

// المسار: app/Services/TamaraService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting; // لاستخدام الإعدادات من قاعدة البيانات
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
// use Illuminate\Http\Request; // غير مستخدم حالياً بشكل مباشر

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
            // استخدام قيمة افتراضية من ملف الإعدادات إذا لم يتم العثور عليها في قاعدة البيانات
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

    public function initiateCheckout(Invoice $invoice, float $amountToPay, string $paymentOption = 'full'): ?array
    {
        if (!$this->isConfigured) { // التحقق من التوكن الرئيسي أيضاً
             Log::error("Tamara initiateCheckout Error: Service is not configured (API URL or Key missing from DB) for Invoice ID: {$invoice->id}");
             return null;
        }

        if ($amountToPay <= 0.009) { // استخدام حد صغير بدلاً من 0 لتجنب مشاكل الدفع بصفر
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
            
            $notificationUrlSetting = Setting::where('key', 'tamara_webhook_url')->value('value');
            $notificationUrl = !empty($notificationUrlSetting) ? $notificationUrlSetting : url('/api/tamara/Webhook');

            Log::info("Tamara URLs for Invoice ID {$invoice->id}", compact('successUrl', 'failureUrl', 'notificationUrl'));

            // --- MODIFICATION START: Clean consumer names and ensure they are not empty ---
            $rawUserName = trim($user->name ?: 'Customer Unknown'); // إزالة المسافات من البداية والنهاية
            $nameParts = explode(' ', $rawUserName, 2);
            
            $firstName = trim(str_replace(',', '', $nameParts[0]));
            $lastName = isset($nameParts[1]) ? trim(str_replace(',', '', $nameParts[1])) : '';

            if (empty($firstName)) { // إذا كان الاسم الأول فارغاً بعد التنظيف
                $firstName = 'FName'; // قيمة افتراضية
                if(empty($lastName) && $rawUserName !== 'Customer Unknown' && !empty($rawUserName) && $rawUserName !== $firstName) {
                    $lastName = $rawUserName; // استخدم الاسم الكامل كاسم عائلة إذا كان الاسم الأول فارغاً
                }
            }
            if (empty($lastName)) {
                $lastName = ($firstName !== 'FName') ? 'LName' : 'Customer'; // قيمة افتراضية لاسم العائلة
            }
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
                'region' => 'Riyadh', 'city' => 'Riyadh',
                'country_code' => 'SA', 'phone_number' => $phone
            ];
            $billingAddress = $shippingAddress;

            $descriptionPrefix = ($paymentOption === 'down_payment') ? 'دفعة أولى لحجز خدمة: ' : 'دفع قيمة خدمة: ';
            $description = Str::limit($descriptionPrefix . $serviceName . ' (فاتورة #' . $orderReferenceId . ')', 255); // حد أقصى لطول الوصف

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
                    'sku' => (string) $service->id,
                    'quantity' => 1,
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

            Log::info("Tamara Payload for Invoice ID {$invoice->id} (Amount: {$amountForTamara}): ", ['payload' => $payload]); // تسجيل الحمولة الكاملة

            $checkoutEndpoint = rtrim($this->apiUrl, '/') . '/checkout';
            $requestTimeout = (int) Setting::where('key', 'tamara_request_timeout')->value('value') ?? config('services.tamara.request_timeout', 30);

            $response = Http::withToken($this->apiKey)
                             ->timeout($requestTimeout)
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

    public function verifyWebhookSignature(Request $request): bool
    {
        // هذا المنطق يجب أن يكون في PaymentController بناءً على طلبك السابق
        // للتحقق من التوقيع بشكل شرطي.
        // إذا تم استدعاء هذه الدالة من مكان آخر، يجب التأكد من أنها تعكس المنطق الصحيح.
        Log::warning('TamaraService::verifyWebhookSignature called, but actual verification logic is in PaymentController.');
        
        // لقراءة الإعداد من قاعدة البيانات هنا أيضاً إذا لزم الأمر
        $bypassVerification = filter_var(
            Setting::where('key', 'tamara_webhook_verification_bypass')->value('value'),
            FILTER_VALIDATE_BOOLEAN
        );
        if($bypassVerification) return true; // إذا كان التجاوز مفعلاً

        $notificationToken = Setting::where('key', 'tamara_notification_token')->value('value');
        if (empty($notificationToken)) {
            Log::error('Tamara Webhook Verification in Service: Notification Token is not set.');
            return false;
        }
        try {
            $authenticator = new \Tamara\Notification\Authenticator($notificationToken);
            // $request->getContent() and $request->header('Tamara-Signature')
            // The Authenticator from Tamara SDK might expect the raw body and signature directly.
            // However, the TypeError previously suggested it wants a Request object.
            // This needs to be consistent with how PaymentController calls it.
            // For now, assuming PaymentController handles the raw parts if this is called.
            // This method seems less likely to be used directly for webhook authentication
            // if PaymentController is the entry point.
            $authenticator->authenticate($request); // أو الطريقة الصحيحة حسب SDK
            return true;
        } catch (\Exception $e) {
            Log::error('Tamara Webhook Verification in Service failed: ' . $e->getMessage());
            return false;
        }
    }
}
