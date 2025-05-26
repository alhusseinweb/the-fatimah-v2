<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\SentSmsLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SmsGatewayAppChannel
{
    protected string $serverUrl;    // مثال: http://192.168.1.100:8080/send
    protected ?string $deviceId;     // بعض التطبيقات تتطلب معرف للجهاز
    protected ?string $apiToken;     // بعض التطبيقات تتطلب توكن
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->serverUrl = Setting::where('key', 'smsgateway_server_url')->value('value');
        $this->deviceId = Setting::where('key', 'smsgateway_device_id')->value('value');
        $this->apiToken = Setting::where('key', 'smsgateway_api_token')->value('value');

        if (!empty($this->serverUrl)) { // على الأقل Server URL يجب أن يكون موجوداً
            $this->isConfigured = true;
        } else {
            Log::error('SmsGatewayAppChannel: Server URL is not configured in database settings (key: smsgateway_server_url). SMS sending via this channel will be disabled.');
            $this->isConfigured = false;
        }
    }

    public function send($notifiable, Notification $notification)
    {
        if (!$this->isConfigured) {
            Log::error('SmsGatewayAppChannel: Cannot send SMS. Channel is not properly configured.');
            return null;
        }

        if (!method_exists($notification, 'toSms') && !method_exists($notification, 'toSmsGatewayApp')) {
            Log::warning('SmsGatewayAppChannel: Method toSms or toSmsGatewayApp not found in notification.', ['notification' => get_class($notification)]);
            return null;
        }

        $messageData = method_exists($notification, 'toSmsGatewayApp')
                       ? $notification->toSmsGatewayApp($notifiable)
                       : $notification->toSms($notifiable);

        $rawRecipientNumber = $messageData['to'] ?? null;
        // استخلاص الرقم بنفس طريقة القنوات الأخرى
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
            $rawRecipientNumber = $notifiable->routes[__CLASS__];
        } elseif ($notifiable instanceof User && property_exists($notifiable, 'mobile_number')) {
            $rawRecipientNumber = $notifiable->mobile_number;
        } elseif (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
            $rawRecipientNumber = $notification->mobileNumber;
        }

        $contentString = is_string($messageData['content']) ? $messageData['content'] : '';

        if (empty($rawRecipientNumber) || empty($contentString)) {
            Log::warning('SmsGatewayAppChannel: Recipient number (to) or message content is missing or empty.', ['notification' => get_class($notification)]);
            $this->logSmsAttempt($notifiable, $notification, 'failed_missing_data', null, $rawRecipientNumber, $contentString);
            return null;
        }
        
        // تطبيقات بوابة الأندرويد قد لا تحتاج لتنسيق دولي صارم إذا كان الهاتف نفسه في الدولة
        // لكن من الأفضل إرسال الأرقام بشكل نظيف (بدون +966 إذا كان التطبيق لا يتوقعها)
        // أو تنسيقها كما يتطلب التطبيق. هنا سنفترض أنه يقبل الصيغة المحلية.
        $recipientForGateway = preg_replace('/[^0-9]/', '', $rawRecipientNumber); // تنظيف مبدئي
        // إذا كان رقم سعودي 05... قد يكون هو المطلوب
        if (Str::startsWith($recipientForGateway, '966') && strlen($recipientForGateway) == 12) {
            $recipientForGateway = '0' . substr($recipientForGateway, 3); // تحويل +9665... إلى 05...
        }
        Log::debug("SmsGatewayAppChannel: Number for gateway. Original: [{$rawRecipientNumber}], ForGateway: [{$recipientForGateway}]");


        // بناء الحمولة (Payload) بناءً على متطلبات API لتطبيق البوابة
        // هذا مجرد مثال، قد يختلف تماماً
        $payload = [
            'phone' => $recipientForGateway, // أو 'to' أو 'number'
            'message' => $contentString,    // أو 'text' أو 'body'
            'device' => $this->deviceId,    // إذا كان مطلوباً
            'token' => $this->apiToken,      // إذا كان مطلوباً
            // قد تحتاج لإضافة 'sim' لاختيار الشريحة إذا كان التطبيق يدعم ذلك
        ];
        
        // إزالة القيم الفارغة من الحمولة إذا كان الـ API لا يقبلها
        $payload = array_filter($payload, function($value) { return !is_null($value) && $value !== ''; });

        Log::info('SmsGatewayAppChannel: Attempting to send SMS.', ['to' => $recipientForGateway, 'payload_keys' => array_keys($payload)]);

        $response = null;
        $status = 'failed_submission';
        $serviceMessageId = null;

        try {
            // معظم تطبيقات بوابة الأندرويد تستخدم GET أو POST بسيط
            // افترض أننا نستخدم POST مع JSON، قد تحتاج لتغيير هذا إلى form_params أو GET query
            $httpClient = Http::timeout(config('services.smsgateway.timeout', 20));
            // إذا كان الـ API يتطلب توكن في الهيدر
            // if ($this->apiToken) { $httpClient = $httpClient->withToken($this->apiToken); }
            
            $response = $httpClient->post($this->serverUrl, $payload); // أو get($this->serverUrl, $payload)

            if ($response->successful() && ($response->json('status') == 'success' || $response->json('success') == true || $response->status() == 200) ) { // تحقق من استجابة النجاح الفعلية
                $status = 'sent_to_device'; // أو 'sent'
                $serviceMessageId = $response->json('message_id') ?? $response->json('data.id') ?? Str::uuid()->toString();
                Log::info('SmsGatewayAppChannel: SMS submitted successfully to gateway app.', ['to' => $recipientForGateway, 'response_id' => $serviceMessageId, 'response_body' => $response->body()]);
            } else {
                $status = 'failed_gateway_error';
                Log::error('SmsGatewayAppChannel: Failed to send SMS via gateway app.', ['to' => $recipientForGateway, 'status_code' => $response->status(), 'response_body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            $status = 'failed_exception';
            Log::critical('SmsGatewayAppChannel: Exception while sending SMS.', ['to' => $recipientForGateway, 'exception' => $e->getMessage()]);
        }

        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $recipientForGateway, $contentString);
        return $response;
    }

    // دالة logSmsAttempt (مشابهة لتلك الموجودة في القنوات الأخرى)
    protected function logSmsAttempt($notifiable, Notification $notification, string $status, ?string $serviceMessageId, ?string $toNumber, string $content): void
    {
        // نفس منطق التسجيل المستخدم في HttpSmsChannel
        // ... (يمكنك نسخها من HttpSmsChannel أو وضعها في Trait مشترك) ...
        try {
            $userId = null;
            $recipientType = 'unknown';

            if ($notifiable instanceof User) {
                $userId = $notifiable->id;
                $recipientType = $notifiable->is_admin ? 'admin' : 'customer';
            } 
            elseif ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
                $phoneNumberFromNotifiable = $notifiable->routes[__CLASS__];
                $user = User::where('mobile_number', $toNumber) 
                            ->orWhere('mobile_number', $phoneNumberFromNotifiable) 
                            ->first();
                if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    $recipientType = 'customer_prospect';
                }
            }
            elseif (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
                $user = User::where('mobile_number', $toNumber) 
                            ->orWhere('mobile_number', $notification->mobileNumber)
                            ->first();
                 if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    $recipientType = 'customer_prospect';
                }
            }

            SentSmsLog::create([
                'user_id' => $userId,
                'recipient_type' => $recipientType,
                'notification_type' => get_class($notification),
                'to_number' => $toNumber, 
                'content' => Str::limit($content, 450), 
                'status' => $status,
                'service_message_id' => $serviceMessageId,
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("SmsGatewayAppChannel: Failed to log sent SMS attempt.", [
                'error' => $e->getMessage(), 'toNumber' => $toNumber, 'notification_class' => get_class($notification)
            ]);
        }
    }
}
