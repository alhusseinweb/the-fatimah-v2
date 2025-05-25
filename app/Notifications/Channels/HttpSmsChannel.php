<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting;
use App\Models\SentSmsLog;
use App\Models\User;
use App\Notifications\SmsLimitReachedNotification; // تأكد من المسار الصحيح
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class HttpSmsChannel
{
    protected $http;
    protected $apiKey;
    protected $senderPhone;
    protected $sendApiEndpoint = 'https://api.httpsms.com/v1/messages/send';

    public function __construct()
    {
        $this->apiKey = config('services.httpsms.api_key');
        $this->senderPhone = config('services.httpsms.sender_phone');

        if (empty($this->apiKey) || empty($this->senderPhone)) {
            Log::error('HttpSmsChannel: API Key or Sender Phone is not configured.');
        }

        $this->http = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=UTF-8',
        ])->timeout(config('services.httpsms.timeout', 15));
    }

    public function send($notifiable, Notification $notification)
    {
        if (empty($this->apiKey) || empty($this->senderPhone)) {
            Log::error('HttpSmsChannel: Cannot send SMS. API Key or Sender Phone is not configured.');
            return null;
        }

        if (!method_exists($notification, 'toHttpSms')) {
            Log::warning('HttpSmsChannel: Method toHttpSms not found in notification.', ['notification' => get_class($notification)]);
            return null;
        }

        $message = $notification->toHttpSms($notifiable);
        
        $rawRecipientNumber = $message['to'] ?? null;
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
            $rawRecipientNumber = $notifiable->routes[__CLASS__];
        } elseif ($notifiable instanceof User && property_exists($notifiable, 'mobile_number')) {
             $rawRecipientNumber = $notifiable->mobile_number;
        } elseif (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
            // حالة خاصة إذا كان الإشعار نفسه يحمل رقم الجوال بشكل مباشر
            $rawRecipientNumber = $notification->mobileNumber;
        }
        
        $contentString = is_string($message['content']) ? $message['content'] : '';

        if (empty($rawRecipientNumber) || empty($contentString)) {
            Log::warning('HttpSmsChannel: Recipient number (to) or message content is missing or empty.', [
                'notification' => get_class($notification), 
                'raw_recipient' => $rawRecipientNumber,
                'content_empty' => empty($contentString)
            ]);
            $this->logSmsAttempt($notifiable, $notification, 'failed_missing_data', null, $rawRecipientNumber, $contentString);
            return null;
        }

        // --- MODIFICATION START: Format recipient number to E.164 for Saudi Arabia ---
        $formattedRecipientNumber = $rawRecipientNumber; // القيمة الافتراضية
        if (Str::startsWith($rawRecipientNumber, '05') && strlen($rawRecipientNumber) == 10) {
            $formattedRecipientNumber = '+966' . substr($rawRecipientNumber, 1);
        } elseif (Str::startsWith($rawRecipientNumber, '5') && strlen($rawRecipientNumber) == 9) {
            // إذا كان الرقم مثل 5xxxxxxxx (بدون الصفر البادئ)
            $formattedRecipientNumber = '+966' . $rawRecipientNumber;
        } elseif (Str::startsWith($rawRecipientNumber, '966') && strlen($rawRecipientNumber) == 12 && !Str::startsWith($rawRecipientNumber, '+')) {
             // إذا كان الرقم 9665xxxxxxxx (بدون +)
            $formattedRecipientNumber = '+' . $rawRecipientNumber;
        }
        // يمكنك إضافة المزيد من قواعد التنسيق هنا إذا كنت تتعامل مع أرقام من دول أخرى
        // أو استخدام مكتبة متخصصة مثل libphonenumber-for-php لتنسيق أكثر قوة
        if ($rawRecipientNumber !== $formattedRecipientNumber) {
            Log::debug("HttpSmsChannel: Formatting recipient number. Original: [{$rawRecipientNumber}], Formatted: [{$formattedRecipientNumber}]");
        }
        // --- MODIFICATION END ---


        // --- التحقق من حد الرسائل ---
        $smsMonthlyLimit = (int) (Setting::where('key', 'sms_monthly_limit')->value('value') ?? 0);
        $stopSendingOnLimit = filter_var(Setting::where('key', 'sms_stop_sending_on_limit')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if ($smsMonthlyLimit > 0) {
            $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                             ->whereMonth('sent_at', Carbon::now()->month)
                                             ->where('status', 'sent')
                                             ->count();

            if ($currentMonthSmsCount >= $smsMonthlyLimit) {
                Log::warning("SMS monthly limit reached.", [
                    'limit' => $smsMonthlyLimit, 'sent_this_month' => $currentMonthSmsCount,
                    'notification' => get_class($notification)
                ]);
                $this->notifyAdminsOfLimitReached($smsMonthlyLimit, $currentMonthSmsCount);
                if ($stopSendingOnLimit) {
                    Log::info("SMS sending stopped as 'sms_stop_sending_on_limit' is enabled.");
                    $this->logSmsAttempt($notifiable, $notification, 'failed_limit_reached', null, $formattedRecipientNumber, $contentString);
                    return null;
                }
            }
        }
        // --- نهاية التحقق من حد الرسائل ---

        $payload = [
            'to' => $formattedRecipientNumber, // استخدام الرقم المنسق
            'content' => $contentString,
            'from' => $this->senderPhone,
        ];

        Log::info('HttpSmsChannel: Attempting to send SMS.', ['notification' => get_class($notification), 'to' => $formattedRecipientNumber, 'payload_content_sample' => Str::limit($contentString, 50)]);

        $response = null;
        $status = 'failed_submission';
        $serviceMessageId = null;

        try {
            $response = $this->http->post($this->sendApiEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $status = 'sent'; 
                $serviceMessageId = $responseData['data']['id'] ?? ($responseData['message_id'] ?? Str::uuid()->toString());
                Log::info('HttpSmsChannel: SMS submitted successfully.', ['to' => $formattedRecipientNumber, 'response_id' => $serviceMessageId, 'response_data' => $responseData]);
            } else {
                $status = 'failed_api_error';
                Log::error('HttpSmsChannel: Failed to send SMS via API.', ['to' => $formattedRecipientNumber, 'status_code' => $response->status(), 'response_body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            $status = 'failed_exception';
            Log::critical('HttpSmsChannel: Exception while sending SMS.', ['to' => $formattedRecipientNumber, 'exception' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
        }

        // استخدام الرقم المنسق عند تسجيل المحاولة
        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $formattedRecipientNumber, $contentString);

        return $response;
    }

    protected function logSmsAttempt($notifiable, Notification $notification, string $status, ?string $serviceMessageId, ?string $toNumber, string $content): void
    {
        try {
            $userId = null;
            $recipientType = 'unknown';

            if ($notifiable instanceof User) {
                $userId = $notifiable->id;
                $recipientType = $notifiable->is_admin ? 'admin' : 'customer';
            } 
            elseif ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
                $phoneNumberFromNotifiable = $notifiable->routes[__CLASS__];
                 // رقم الهاتف هنا قد يكون منسقاً أو غير منسق، لكن $toNumber الذي مررناه للدالة هو المنسق
                $user = User::where('mobile_number', $toNumber) // استخدم $toNumber (المنسق) للبحث إذا أردت
                            ->orWhere('mobile_number', $phoneNumberFromNotifiable) // أو الرقم الأصلي قبل التنسيق
                            ->first();
                if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    $recipientType = 'customer_prospect';
                }
            }
            elseif (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
                $user = User::where('mobile_number', $notification->mobileNumber)->first();
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
                'to_number' => $toNumber, // الرقم الذي تم الإرسال إليه (يفضل أن يكون المنسق)
                'content' => Str::limit($content, 450), 
                'status' => $status,
                'service_message_id' => $serviceMessageId,
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log sent SMS attempt.", [
                'error' => $e->getMessage(),
                'toNumber' => $toNumber,
                'notification_class' => get_class($notification)
            ]);
        }
    }

    protected function notifyAdminsOfLimitReached(int $limit, int $count): void
    {
        $cacheKey = 'sms_limit_reached_notification_sent_today';
        if (Cache::has($cacheKey)) {
            return;
        }

        $admins = User::where('is_admin', true)->get();
        if ($admins->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($admins, new SmsLimitReachedNotification($limit, $count));
            Cache::put($cacheKey, true, Carbon::now()->endOfDay()); 
            Log::info("SmsLimitReachedNotification sent to admins.");
        }
    }
}
