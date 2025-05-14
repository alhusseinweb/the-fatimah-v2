<?php

// المسار: app/Services/TamaraService.php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request; // Note: Request is not directly used here, but kept for consistency if needed later

class TamaraService
{
    protected string $apiUrl;
    protected string $apiKey;

    /**
     * Constructor لتحضير بيانات الربط.
     */
    public function __construct()
    {
        $this->apiUrl = Config::get('services.tamara.url', 'https://api-sandbox.tamara.co');
        $this->apiKey = Config::get('services.tamara.token');

        if (empty($this->apiUrl) || empty($this->apiKey)) {
            Log::error('Tamara Service Error: API URL or Key is missing from configuration!');
        } else {
            // Kept for debugging if needed: Log::info('TamaraService instantiated successfully.');
        }
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
        // --- بداية: إضافة فحص لقيمة amountToPay ---
        if ($amountToPay <= 0) {
             Log::error("Tamara initiateCheckout Error: Amount to pay must be positive. Received: {$amountToPay} for Invoice ID: {$invoice->id}");
             return null;
        }
        // --- نهاية: إضافة فحص لقيمة amountToPay ---

        if (empty($this->apiUrl) || empty($this->apiKey)) {
             Log::error('Tamara initiateCheckout Error: Missing API credentials for Invoice ID: ' . $invoice->id);
             return null;
        }

        try {
            // 1. تحميل البيانات المرتبطة
            $invoice->loadMissing(['booking.user', 'booking.service']);
            $booking = $invoice->booking;
            $user = $booking?->user;
            $service = $booking?->service;

            if (!$booking || !$user || !$service) {
                 Log::error("Tamara initiateCheckout Error: Missing related data (booking, user, or service) for Invoice ID: {$invoice->id}");
                 return null;
            }

            // 2. تحديد روابط إعادة التوجيه والإشعار (لا تغيير هنا)
            try {
                $successUrl = route('tamara.success', ['invoice' => $invoice->id]);
                $failureUrl = route('tamara.failure', ['invoice' => $invoice->id]);
                // تأكد من أن هذا الرابط صحيح ويمكن الوصول إليه من تمارا في بيئة الإنتاج
                $notificationUrl = 'https://new.thefatimah.com/api/tamara/Webhook';

                Log::info("Tamara URLs for Invoice ID {$invoice->id}", [
                     'success_url' => $successUrl,
                     'failure_url' => $failureUrl,
                     'notification_url' => $notificationUrl
                ]);
             } catch (\Exception $routeException) {
                 Log::error("Tamara initiateCheckout Error: Could not generate route URLs for Invoice ID: {$invoice->id}. Check route definitions.", ['message' => $routeException->getMessage()]);
                 return null;
             }

            // 3. تجهيز بيانات العميل (لا تغيير هنا)
            Log::debug("User data for Tamara for Invoice ID {$invoice->id}: ", ['user_id' => $user->id, 'name' => $user->name, 'mobile_number' => $user->mobile_number, 'email' => $user->email]);
            $userName = $user->name ?: 'Unknown Customer';
            $firstName = Str::before($userName, ' ') ?: $userName;
            $lastName = Str::after($userName, ' ') ?: 'Customer';
             if (empty(trim($lastName)) || $lastName === $firstName) { $lastName = 'Customer'; }

             $phone = $user->mobile_number;
             // Basic phone formatting/validation
             if ($phone && !str_starts_with($phone, '+')) {
                 $phone = preg_replace('/[\s-]+/', '', $phone);
                 if (str_starts_with($phone, '05')) { $phone = '+966' . substr($phone, 1); }
                 elseif (strlen($phone) == 9 && !str_starts_with($phone, '0')) { $phone = '+966' . $phone; }
                 elseif (str_starts_with($phone, '5')) { $phone = '+966' . $phone; }
             }
              if(empty($phone) || !preg_match('/^\+966\d{9}$/', $phone)){ // More strict validation
                  Log::error("Tamara Payload Error: Phone number is missing or invalid ('{$phone}') for user {$user->id} / Invoice {$invoice->id}. Cannot proceed.");
                  return null;
              }

              $email = $user->email;
              if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){ // Basic email validation
                  Log::error("Tamara Payload Error: Email is missing or invalid ('{$email}') for user {$user->id} / Invoice {$invoice->id}. Cannot proceed.");
                  return null;
              }

            // 4. تجهيز بيانات الطلب
            // --- تعديل هنا: استخدام amountToPay بدلاً من invoice->amount ---
            $amountForTamara = round($amountToPay, 2);
            // ----------------------------------------------------------
            $currency = $invoice->currency ?: 'SAR';
            // استخدام رقم الفاتورة كـ order_reference_id لسهولة الربط
            $orderReferenceId = (string) $invoice->invoice_number;
            // يمكنك إنشاء رقم طلب فريد إذا أردت، أو الاكتفاء برقم الفاتورة
            $orderNumber = $invoice->invoice_number ?: 'BOOK-' . $booking->id;
            $serviceName = $service->{'name_' . app()->getLocale()} ?? $service->name_ar; // استخدام اللغة الحالية

             $shippingAddress = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                // --- بداية: إعادة العنوان الأصلي ---
                'line1' => $booking->event_location ?: 'N/A', // استخدام مكان الحفل كعنوان الشحن
                // --- نهاية: إعادة العنوان الأصلي ---
                'line2' => null,
                'region' => 'Riyadh Region', // Consider making dynamic if needed
                'city' => 'Riyadh',       // Consider making dynamic if needed
                'country_code' => 'SA',
                'phone_number' => $phone
             ];

            // --- تعديل هنا: تحديث الوصف بناءً على paymentOption ---
             $descriptionPrefix = ($paymentOption === 'down_payment') ? 'دفعة أولى لحجز خدمة: ' : 'دفع كامل لحجز خدمة: ';
             $description = $descriptionPrefix . $serviceName . ' (فاتورة #' . $orderReferenceId . ')';
            // ----------------------------------------------------

            // 5. بناء حمولة الطلب (Payload) لـ API تمارا
            $payload = [
                'order_reference_id' => $orderReferenceId, // Use invoice number
                'order_number'=> $orderNumber, // Can be the same or different
                'total_amount' => [
                    // --- تعديل هنا: استخدام المبلغ المرسل لتمارا ---
                    'amount' => $amountForTamara,
                    // -----------------------------------------
                    'currency' => $currency
                ],
                'description' => $description, // استخدام الوصف المحدث
                 'shipping_amount' => [
                      'amount' => 0.00, // تمارا تتطلبها كـ string أحياناً, تأكد من التوثيق
                      'currency' => $currency
                  ],
                  'tax_amount' => [
                      'amount' => 0.00, // تمارا تتطلبها كـ string أحياناً
                      'currency' => $currency
                  ],
                'country_code' => 'SA',
                // يجب أن يكون payment_type و instalments متوافقين
                'payment_type' => 'PAY_BY_INSTALMENTS', // Or 'PAY_LATER'
                'instalments' => 3, // Required if payment_type is PAY_BY_INSTALMENTS
                'items' => [
                    [
                        'reference_id' => (string) $service->id,
                        'type' => 'Digital', // Or relevant type
                        'name' => $serviceName,
                        'sku' => (string) $service->id,
                        'quantity' => 1,
                         // ملاحظة: الخصم الكلي يجب أن يكون مطبقاً على الفاتورة الأصلية
                         // هنا نرسل المبلغ المطلوب دفعه الآن كوحدة واحدة
                         'discount_amount' => ['amount' => 0.00, 'currency' => $currency],
                         'tax_amount' => ['amount' => 0.00, 'currency' => $currency],
                         // --- تعديل هنا: سعر الوحدة والمبلغ الإجمالي للبند يجب أن يكونا المبلغ المرسل لتمارا ---
                        'unit_price' => ['amount' => $amountForTamara, 'currency' => $currency],
                        'total_amount' => ['amount' => $amountForTamara, 'currency' => $currency], // تمارا تتطلبها string أحياناً
                        // ----------------------------------------------------------------------------------
                    ]
                ],
                'consumer' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone_number' => $phone,
                    'email' => $email,
                ],
                 'shipping_address' => $shippingAddress,
                 'billing_address' => $shippingAddress, // Use same for billing initially

                'merchant_url' => [
                    'success' => $successUrl,
                    'failure' => $failureUrl,
                    'cancel' => $failureUrl, // Usually cancel redirects to failure
                    'notification' => $notificationUrl
                ],
                'platform' => 'FatimaAliBookingSystem-' . config('app.env'),
                'locale' => app()->getLocale() == 'ar' ? 'ar_SA' : 'en_SA', // Dynamic locale
            ];

            // 6. إرسال الطلب إلى API تمارا
            Log::info("Tamara Payload for Invoice ID {$invoice->id} (Amount: {$amountForTamara}): ", $payload);

            $checkoutEndpoint = rtrim($this->apiUrl, '/') . '/checkout';

            $response = Http::withToken($this->apiKey)
                             ->timeout(config('services.tamara.request_timeout', 60)) // Increased timeout slightly
                             ->post($checkoutEndpoint, $payload);

            // 7. معالجة الرد من تمارا
            if ($response->successful()) {
                $responseData = $response->json();
                 if (!empty($responseData['checkout_url']) && !empty($responseData['order_id'])) {
                     Log::info("Tamara checkout initiated successfully for Invoice ID: {$invoice->id}. Order ID: " . $responseData['order_id'] . ". Amount: {$amountForTamara}");
                     // إرجاع مصفوفة بالبيانات المطلوبة
                     return [
                         'checkout_url' => $responseData['checkout_url'],
                         'order_id' => $responseData['order_id'] // معرف طلب تمارا
                     ];
                 } else {
                     Log::error("Tamara initiateCheckout Error: Checkout URL or Order ID not found in successful response for Invoice ID: {$invoice->id}. Response: " . $response->body());
                     return null;
                 }
            } else {
                 // --- تحسين تسجيل الخطأ ---
                 $errorBody = $response->body();
                 $decodedError = json_decode($errorBody, true);
                 Log::error("Tamara initiateCheckout Request Failed for Invoice ID: {$invoice->id}.", [
                    'status' => $response->status(),
                    'response_body' => $errorBody,
                    'decoded_errors' => $decodedError['errors'] ?? null, // محاولة استخلاص الأخطاء إن وجدت
                    'sent_payload' => $payload // سجل الـ payload المرسل للمساعدة في التشخيص
                 ]);
                 // -------------------------
                 return null;
            }

        } catch (\Exception $e) {
             Log::error("Tamara initiateCheckout Exception for Invoice ID: {$invoice->id}. Message: " . $e->getMessage(), ['exception' => $e]);
             return null;
        }
    } // نهاية دالة initiateCheckout


    /**
     * دالة التحقق من الـ Webhook (غير مستخدمة حالياً، الاعتماد على PaymentController)
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        // ... (الكود القديم يبقى كما هو، لكنه غير مستخدم للتحقق الفعلي) ...
        Log::warning('TamaraService::verifyWebhookSignature is deprecated. Verification handled by SDK in PaymentController.');
        return false;
    }

} // نهاية الكلاس TamaraService