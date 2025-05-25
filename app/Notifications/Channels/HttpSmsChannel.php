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
        ])->timeout(config('services.httpsms.timeout', 15)); // استخدام قيمة من الإعدادات أو قيمة افتراضية
    }

    public function send($notifiable, Notification $notification)
    {
        if (empty($this->apiKey) || empty($this->senderPhone)) {
            Log::error('HttpSmsChannel: Cannot send SMS. API Key or Sender Phone is not configured.');
            // لا نسجل محاولة هنا لأن الإعدادات الأساسية مفقودة
            return null;
        }

        if (!method_exists($notification, 'toHttpSms')) {
            Log::warning('HttpSmsChannel: Method toHttpSms not found in notification.', ['notification' => get_class($notification)]);
            return null;
        }

        $message = $notification->toHttpSms($notifiable);

        // التحقق من وجود رقم المستلم ومحتوى الرسالة
        // $recipientNumberForLog و $contentStringForLog يستخدمان في جميع الحالات للتسجيل
        $recipientNumberForLog = $message['to'] ?? null;
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[HttpSmsChannel::class])) {
            $recipientNumberForLog = $notifiable->routes[HttpSmsChannel::class];
        } elseif ($notifiable instanceof User) {
            $recipientNumberForLog = $notifiable->mobile_number; // أو دالة مخصصة لجلب رقم الهاتف للإشعارات
        }
        
        $contentStringForLog = is_string($message['content']) ? $message['content'] : '';

        if (empty($recipientNumberForLog) || !isset($message['content'])) {
            Log::warning('HttpSmsChannel: Recipient number (to) or message content is missing.', ['notification' => get_class($notification), 'message_data' => $message]);
            $this->logSmsAttempt($notifiable, $notification, 'failed_missing_data', null, $recipientNumberForLog, $contentStringForLog);
            return null;
        }


        // --- التحقق من حد الرسائل ---
        $smsMonthlyLimit = (int) (Setting::where('key', 'sms_monthly_limit')->value('value') ?? 0);
        $stopSendingOnLimit = filter_var(Setting::where('key', 'sms_stop_sending_on_limit')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if ($smsMonthlyLimit > 0) { // فقط إذا كان هناك حد معين
            $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                             ->whereMonth('sent_at', Carbon::now()->month)
                                             ->where('status', 'sent') // عد فقط الرسائل المرسلة بنجاح
                                             ->count();

            if ($currentMonthSmsCount >= $smsMonthlyLimit) {
                Log::warning("SMS monthly limit reached.", [
                    'limit' => $smsMonthlyLimit,
                    'sent_this_month' => $currentMonthSmsCount,
                    'notification' => get_class($notification)
                ]);

                $this->notifyAdminsOfLimitReached($smsMonthlyLimit, $currentMonthSmsCount);

                if ($stopSendingOnLimit) {
                    Log::info("SMS sending stopped as 'sms_stop_sending_on_limit' is enabled.");
                    $this->logSmsAttempt($notifiable, $notification, 'failed_limit_reached', null, $recipientNumberForLog, $contentStringForLog);
                    return null; // إيقاف الإرسال
                }
            }
        }
        // --- نهاية التحقق من حد الرسائل ---

        $payload = [
            'to' => $recipientNumberForLog, // استخدام الرقم الذي تم التحقق منه
            'content' => $contentStringForLog, // استخدام المحتوى الذي تم التحقق منه
            'from' => $this->senderPhone,
        ];

        Log::info('HttpSmsChannel: Attempting to send SMS.', ['notification' => get_class($notification), 'to' => $recipientNumberForLog]);

        $response = null;
        $status = 'failed_submission'; // حالة افتراضية
        $serviceMessageId = null;

        try {
            $response = $this->http->post($this->sendApiEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $status = 'sent'; 
                $serviceMessageId = $responseData['data']['id'] ?? ($responseData['message_id'] ?? Str::uuid()->toString()); // استخدام UUID كاحتياطي إذا لم يوجد ID
                Log::info('HttpSmsChannel: SMS submitted successfully.', ['to' => $recipientNumberForLog, 'response_id' => $serviceMessageId, 'response_data' => $responseData]);
            } else {
                $status = 'failed_api_error';
                Log::error('HttpSmsChannel: Failed to send SMS via API.', ['to' => $recipientNumberForLog, 'status_code' => $response->status(), 'response_body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            $status = 'failed_exception';
            Log::critical('HttpSmsChannel: Exception while sending SMS.', ['to' => $recipientNumberForLog, 'exception' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
        }

        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $recipientNumberForLog, $contentStringForLog);

        return $response; // أو يمكنك إرجاع true/false بناءً على النجاح
    }

    protected function logSmsAttempt($notifiable, Notification $notification, string $status, ?string $serviceMessageId, ?string $toNumber, string $content): void
    {
        try {
            $userId = null;
            $recipientType = 'unknown'; // قيمة افتراضية

            if ($notifiable instanceof User) {
                $userId = $notifiable->id;
                $recipientType = $notifiable->is_admin ? 'admin' : 'customer';
            } 
            // إذا كان notifiable هو AnonymousNotifiable (يستخدم مع Notification::route())
            // وكان يحتوي على رقم الهاتف في مسار هذا الـ channel
            elseif ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
                $phoneNumberFromNotifiable = $notifiable->routes[__CLASS__];
                // نحاول البحث عن مستخدم بهذا الرقم لتسجيل user_id إذا وجد
                $user = User::where('mobile_number', $phoneNumberFromNotifiable)->first();
                if ($user) {
                    $userId = $user->id;
                    $recipientType = $user->is_admin ? 'admin' : 'customer';
                } else {
                    // إذا لم يتم العثور على مستخدم، قد يكون OTP لعملية تسجيل جديدة
                    $recipientType = 'customer_prospect'; // أو 'unregistered_user'
                }
            }
            // إذا كان الإشعار نفسه يحمل خاصية mobileNumber (مثل SendOtpNotification)
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
