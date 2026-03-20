<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaylinkService
{
    protected string $appId;
    protected string $secretKey;
    protected bool $isTest;
    protected string $baseUrl;

    public function __construct()
    {
        $settings = Setting::whereIn('key', [
            'paylink_api_id',
            'paylink_secret_key',
            'paylink_test_mode'
        ])->pluck('value', 'key');

        $this->appId = env('PAYLINK_APP_ID') ?? $settings['paylink_api_id'] ?? '';
        $this->secretKey = env('PAYLINK_SECRET_KEY') ?? $settings['paylink_secret_key'] ?? '';
        $this->isTest = env('PAYLINK_TEST_MODE') !== null 
            ? filter_var(env('PAYLINK_TEST_MODE'), FILTER_VALIDATE_BOOLEAN)
            : filter_var($settings['paylink_test_mode'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $this->baseUrl = $this->isTest 
            ? 'https://restdemo.paylink.sa/api-v2' 
            : 'https://restapi.paylink.sa/api-v2';
            
        // Current Paylink V2 API Info:
        // Test: https://restdemo.paylink.sa/api-v2
        // Production: https://restapi.paylink.sa/api-v2
        
        if ($this->isTest) {
            $this->baseUrl = 'https://restdemo.paylink.sa/api-v2';
        }

        Log::debug("PaylinkService initialized.", [
            'appIdPrefix' => substr($this->appId, 0, 8),
            'isTest' => $this->isTest,
            'baseUrl' => $this->baseUrl
        ]);
    }

    /**
     * Check if Paylink is enabled.
     */
    public function isEnabled(): bool
    {
        $envEnabled = env('PAYLINK_ENABLED');
        if ($envEnabled !== null) {
            return filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN);
        }
        return filter_var(Setting::where('key', 'paylink_enabled')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Authenticate and get a Bearer token.
     */
    protected function getToken(): ?string
    {
        try {
            $response = Http::post($this->baseUrl . '/auth', [
                'appId' => $this->appId,
                'secretKey' => $this->secretKey,
            ]);

            if ($response->successful()) {
                return $response->json('id_token');
            }

            Log::error('PaylinkService: Authentication failed.', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('PaylinkService: Exception during authentication.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Create an invoice/payment link.
     */
    public function createInvoice(Invoice $invoice): ?string
    {
        $token = $this->getToken();
        if (!$token) {
            Log::error('PaylinkService: Aborting invoice creation due to missing token.');
            return null;
        }

        $booking = $invoice->booking;
        $user = $booking->user;

        try {
            // Paylink expects amount as number
            $amount = (float) $invoice->amount;

            // تنظيف رقم الجوال: التأكد من أنه يبدأ بـ 966 أو تنسيق دولي مقبول لـ Paylink
            $mobile = $user->mobile_number;
            $mobile = preg_replace('/[^0-9]/', '', $mobile); // إزالة أي رموز غير الأرقام
            
            // إذا كان يبدأ بـ 05، نحوله إلى 9665
            if (str_starts_with($mobile, '05')) {
                $mobile = '966' . substr($mobile, 1);
            }
            // إذا كان يبدأ بـ 5 فقط وطوله 9، نفترض أنه سعودي بدون صفر
            elseif (str_starts_with($mobile, '5') && strlen($mobile) === 9) {
                $mobile = '966' . $mobile;
            }

            $payload = [
                'amount' => $amount,
                'clientEmail' => $user->email ?? 'customer@example.com',
                'clientMobile' => $mobile,
                'clientName' => $user->name,
                'orderNumber' => $invoice->invoice_number,
                'callBackUrl' => route('payment.callback', ['gateway' => 'paylink']),
                'cancelUrl' => route('booking.pending', $booking->id),
                'products' => [
                    [
                        'description' => $booking->service->name_ar ?? 'خدمة تصوير',
                        'price' => $amount,
                        'qty' => 1,
                        'title' => $booking->service->name_ar ?? 'خدمة تصوير',
                    ]
                ]
            ];

            Log::debug('PaylinkService: Sending invoice request.', ['payload' => $payload]);

            $response = Http::withToken($token)->post($this->baseUrl . '/invoice', $payload);

            if ($response->successful()) {
                $url = $response->json('url');
                $invoice->payment_url = $url;
                $invoice->payment_gateway_ref = $response->json('transactionNo');
                $invoice->save();

                Log::info("PaylinkService: Invoice created successfully for Invoice #{$invoice->id}. URL: {$url}");
                return $url;
            }

            Log::error('PaylinkService: Invoice creation failed at API level.', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'invoice_id' => $invoice->id,
                'payload' => $payload
            ]);
        } catch (\Exception $e) {
            Log::error('PaylinkService: Exception during invoice creation.', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id
            ]);
        }

        return null;
    }
}
