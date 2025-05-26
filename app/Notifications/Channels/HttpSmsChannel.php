<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting; // تم التأكد من استيراده
use App\Models\SentSmsLog; // تم التأكد من استيراده
use App\Models\User;       // تم التأكد من استيراده
use App\Notifications\SmsLimitReachedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class HttpSmsChannel
{
    protected $http;
    protected $apiKey;
    protected $senderPhone;
    protected $sendApiEndpoint = 'https://api.httpsms.com/v1/messages/send';
    protected bool $isConfigured = false; // لمعرفة إذا تم تحميل الإعدادات بنجاح

    public function __construct()
    {
        // --- MODIFICATION START: Load settings from database ---
        $this->apiKey = Setting::where('key', 'httpsms_api_key')->value('value');
        $this->senderPhone = Setting::where('key', 'httpsms_sender_phone')->value('value');

        if (!empty($this->apiKey) && !empty($this->senderPhone)) {
            $this->isConfigured = true;
            $this->http = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=UTF-8',
            ])->timeout(config('services.httpsms.timeout', 15)); // يمكنك جعل هذا الإعداد أيضاً في قاعدة البيانات
        } else {
            Log::error('HttpSmsChannel: API Key or Sender Phone is not configured in database settings (keys: httpsms_api_key, httpsms_sender_phone). SMS sending via this channel will be disabled.');
            $this->isConfigured = false;
        }
        // --- MODIFICATION END ---
    }

    public function send($notifiable, Notification $notification)
    {
        // --- MODIFICATION START: Check if channel is configured ---
        if (!$this->isConfigured) {
            Log::error('HttpSmsChannel: Cannot send SMS. Channel is not properly configured (API Key or Sender Phone missing from DB settings).');
            // لا نسجل محاولة هنا لأن القناة نفسها غير مهيأة
            return null;
        }
        // --- MODIFICATION END ---

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

        $formattedRecipientNumber = $rawRecipientNumber; 
        if (Str::startsWith($rawRecipientNumber, '05') && strlen($rawRecipientNumber) == 10) {
            $formattedRecipientNumber = '+966' . substr($rawRecipientNumber, 1);
        } elseif (Str::startsWith($rawRecipientNumber, '5') && strlen($rawRecipientNumber) == 9) {
            $formattedRecipientNumber = '+966' . $rawRecipientNumber;
        } elseif (Str::startsWith($rawRecipientNumber, '966') && strlen($rawRecipientNumber) == 12 && !Str::startsWith($rawRecipientNumber, '+')) {
            $formattedRecipientNumber = '+' . $rawRecipientNumber;
        }
        
        if ($rawRecipientNumber !== $formattedRecipientNumber) {
            Log::debug("HttpSmsChannel: Formatting recipient number. Original: [{$rawRecipientNumber}], Formatted: [{$formattedRecipientNumber}]");
        } else {
            Log::debug("HttpSmsChannel: Recipient number not reformatted, using as is: [{$rawRecipientNumber}]");
        }

        // التحقق من حد الرسائل (يبقى كما هو)
        $smsMonthlyLimit = (int) (Setting::where('key', 'sms_monthly_limit')->value('value') ?? 0);
        $stopSendingOnLimit = filter_var(Setting::where('key', 'sms_stop_sending_on_limit')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if ($smsMonthlyLimit > 0) {
            $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                             ->whereMonth('sent_at', Carbon::now()->month)
                                             ->where('status', 'sent')
                                             ->count();
            if ($currentMonthSmsCount >= $smsMonthlyLimit) {
                Log::warning("SMS monthly limit reached.", ['limit' => $smsMonthlyLimit, 'sent_this_month' => $currentMonthSmsCount, 'notification' => get_class($notification)]);
                $this->notifyAdminsOfLimitReached($smsMonthlyLimit, $currentMonthSmsCount);
                if ($stopSendingOnLimit) {
                    Log::info("SMS sending stopped as 'sms_stop_sending_on_limit' is enabled.");
                    $this->logSmsAttempt($notifiable, $notification, 'failed_limit_reached', null, $formattedRecipientNumber, $contentString);
                    return null;
                }
            }
        }

        $payload = [
            'to' => $formattedRecipientNumber,
            'content' => $contentString,
            'from' => $this->senderPhone,
        ];

        Log::info('HttpSmsChannel: Attempting to send SMS.', ['notification' => get_class($notification), 'to' => $formattedRecipientNumber, 'payload_content_sample' => Str::limit($contentString, 50)]);

        $response = null;
        $status = 'failed_submission';
        $serviceMessageId = null;

        try {
            // تأكد أن $this->http مُهيأ قبل استخدامه
            if (!$this->http) throw new \Exception("HTTP client not initialized for HttpSmsChannel.");
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

        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $formattedRecipientNumber, $contentString);
        return $response;
    }

    // دالة logSmsAttempt تبقى كما هي من الرد السابق
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
            Log::error("Failed to log sent SMS attempt.", [
                'error' => $e->getMessage(),
                'toNumber' => $toNumber,
                'notification_class' => get_class($notification)
            ]);
        }
    }

    // دالة notifyAdminsOfLimitReached تبقى كما هي
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
