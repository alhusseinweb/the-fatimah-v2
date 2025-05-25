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
use Illuminate\Support\Facades\Cache; // استيراد Cache

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
        ])->timeout(15);
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

        // --- التحقق من حد الرسائل ---
        $smsMonthlyLimit = (int) (Setting::where('key', 'sms_monthly_limit')->value('value') ?? 0); // تعديل بسيط لجلب القيمة
        $stopSendingOnLimit = filter_var(Setting::where('key', 'sms_stop_sending_on_limit')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN); // تعديل بسيط
        
        $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                         ->whereMonth('sent_at', Carbon::now()->month)
                                         ->where('status', 'sent')
                                         ->count();

        $messageDataForLog = $notification->toHttpSms($notifiable); // الحصول على بيانات الرسالة مبكراً للتسجيل
        $recipientNumberForLog = $messageDataForLog['to'] ?? ($notifiable instanceof User ? $notifiable->mobile_number : ($notifiable->routes[HttpSmsChannel::class] ?? null));
        $contentStringForLog = is_string($messageDataForLog['content']) ? $messageDataForLog['content'] : '';


        if ($smsMonthlyLimit > 0 && $currentMonthSmsCount >= $smsMonthlyLimit) {
            Log::warning("SMS monthly limit reached.", [
                'limit' => $smsMonthlyLimit,
                'sent_this_month' => $currentMonthSmsCount,
                'notification' => get_class($notification)
            ]);

            $this->notifyAdminsOfLimitReached($smsMonthlyLimit, $currentMonthSmsCount);

            if ($stopSendingOnLimit) {
                Log::info("SMS sending stopped as 'sms_stop_sending_on_limit' is enabled.");
                $this->logSmsAttempt($notifiable, $notification, 'failed_limit_reached', null, $recipientNumberForLog, $contentStringForLog);
                return null;
            }
        }
        // --- نهاية التحقق من حد الرسائل ---

        // $message = $notification->toHttpSms($notifiable); // تم استدعاؤها بالفعل أعلاه
        $message = $messageDataForLog;


        if (empty($message['to']) || !isset($message['content'])) {
            Log::warning('HttpSmsChannel: Recipient number (to) or message content is missing.', ['notification' => get_class($notification)]);
            return null;
        }
        
        $contentString = is_string($message['content']) ? $message['content'] : ''; // تم تعريفها كـ $contentStringForLog
        $recipientNumber = $message['to']; // تم تعريفها كـ $recipientNumberForLog

        $payload = [
            'to' => $recipientNumber,
            'content' => $contentString,
            'from' => $this->senderPhone,
        ];

        Log::info('HttpSmsChannel: Attempting to send SMS.', ['notification' => get_class($notification), 'to' => $recipientNumber]);

        $response = null;
        $status = 'failed_submission';
        $serviceMessageId = null;

        try {
            $response = $this->http->post($this->sendApiEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $status = 'sent';
                $serviceMessageId = $responseData['data']['id'] ?? ($responseData['message_id'] ?? null);
                Log::info('HttpSmsChannel: SMS submitted successfully.', ['to' => $recipientNumber, 'response_id' => $serviceMessageId]);
            } else {
                $status = 'failed_api_error';
                Log::error('HttpSmsChannel: Failed to send SMS via API.', ['to' => $recipientNumber, 'status_code' => $response->status(), 'response_body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            $status = 'failed_exception';
            Log::critical('HttpSmsChannel: Exception while sending SMS.', ['to' => $recipientNumber, 'exception' => $e->getMessage()]);
        }

        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $recipientNumber, $contentString);

        return $response;
    }

    protected function logSmsAttempt($notifiable, Notification $notification, string $status, ?string $serviceMessageId, ?string $toNumber, string $content): void
    {
        try {
            $userId = null;
            $recipientType = null;

            if ($notifiable instanceof User) {
                $userId = $notifiable->id;
                $recipientType = $notifiable->is_admin ? 'admin' : 'admin'; // Note: was 'admin' : 'customer'
            } 
            // Check if $notifiable is a string (could be a phone number from Notification::route())
            // And if the notification object has a specific mobileNumber property (like our SendOtpNotification)
            else if (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
                 // Attempt to find user by this mobile number if needed for logging association, otherwise log as generic
                $user = User::where('mobile_number', $notification->mobileNumber)->first();
                if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    $recipientType = 'customer_prospect'; // Or 'unregistered_user' for OTPs
                }
            }
            // Fallback for $notifiable from Notification::route('channel', $route)
            else if (is_string($notifiable)) {
                $user = User::where('mobile_number', $notifiable)->first();
                 if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    $recipientType = 'customer_prospect';
                }
            }


            SentSmsLog::create([
                // --- MODIFIED LOGIC FOR USER ID ---
                'user_id' => $userId,
                // --- END MODIFIED LOGIC ---
                'recipient_type' => $recipientType,
                'notification_type' => get_class($notification),
                'to_number' => $toNumber, // هذا هو الرقم الفعلي الذي تم الإرسال إليه
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
            // تأكد من أن كلاس الإشعار SmsLimitReachedNotification يستخدم قناة البريد فقط
            \Illuminate\Support\Facades\Notification::send($admins, new SmsLimitReachedNotification($limit, $count));
            Cache::put($cacheKey, true, Carbon::now()->endOfDay());
            Log::info("SmsLimitReachedNotification sent to admins.");
        }
    }
}
