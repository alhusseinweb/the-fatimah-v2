<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting; // <-- استيراد
use App\Models\SentSmsLog; // <-- استيراد
use App\Models\User; // <-- استيراد
use App\Notifications\SmsLimitReachedNotification; // <-- إشعار جديد للمدير
use Carbon\Carbon; // <-- استيراد

class HttpSmsChannel
{
    // ... (الخصائص والدالة __construct كما هي) ...
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

        // --- *** التحقق من حد الرسائل *** ---
        $smsMonthlyLimit = (int) (Setting::where('key', 'sms_monthly_limit')->first()->value ?? 0);
        $stopSendingOnLimit = filter_var(Setting::where('key', 'sms_stop_sending_on_limit')->first()->value ?? false, FILTER_VALIDATE_BOOLEAN);
        $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                         ->whereMonth('sent_at', Carbon::now()->month)
                                         ->where('status', 'sent') // عد فقط الرسائل المرسلة بنجاح
                                         ->count();

        if ($smsMonthlyLimit > 0 && $currentMonthSmsCount >= $smsMonthlyLimit) {
            Log::warning("SMS monthly limit reached.", [
                'limit' => $smsMonthlyLimit,
                'sent_this_month' => $currentMonthSmsCount,
                'notification' => get_class($notification)
            ]);

            // إرسال إشعار للمديرين (مرة واحدة في اليوم مثلاً لتجنب الإزعاج)
            $this->notifyAdminsOfLimitReached($smsMonthlyLimit, $currentMonthSmsCount);

            if ($stopSendingOnLimit) {
                Log_info("SMS sending stopped as 'sms_stop_sending_on_limit' is enabled.");
                // يمكنك اختيارياً تسجيل محاولة الإرسال الفاشلة بسبب الحد
                $this->logSmsAttempt($notifiable, $notification, 'failed_limit_reached', null, $message['to'] ?? null, $message['content'] ?? '');
                return null; // إيقاف الإرسال
            }
        }
        // --- *** نهاية التحقق من حد الرسائل *** ---

        $message = $notification->toHttpSms($notifiable);

        if (empty($message['to']) || !isset($message['content'])) {
            Log::warning('HttpSmsChannel: Recipient number (to) or message content is missing.', ['notification' => get_class($notification)]);
            return null;
        }

        $contentString = is_string($message['content']) ? $message['content'] : '';
        $recipientNumber = $message['to']; // تم استخدامه لاحقاً في التسجيل

        $payload = [
            'to' => $recipientNumber,
            'content' => $contentString,
            'from' => $this->senderPhone,
            // 'request_id' => (string) Str::uuid(), // إضافة ID فريد لكل طلب إذا كانت الخدمة تدعمه لتجنب التكرار
        ];

        Log::info('HttpSmsChannel: Attempting to send SMS.', ['notification' => get_class($notification), 'to' => $recipientNumber]);

        $response = null;
        $status = 'failed_submission'; // حالة افتراضية
        $serviceMessageId = null;

        try {
            $response = $this->http->post($this->sendApiEndpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                $status = 'sent'; // أو 'pending' إذا كانت الخدمة تعيد حالة مبدئية
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

        // --- *** تسجيل محاولة الإرسال في قاعدة البيانات *** ---
        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $recipientNumber, $contentString);
        // --- *** نهاية التسجيل *** ---

        return $response; // أو يمكنك إرجاع true/false بناءً على النجاح
    }

    /**
     * Helper to log SMS attempt.
     */
    protected function logSmsAttempt($notifiable, Notification $notification, string $status, ?string $serviceMessageId, ?string $toNumber, string $content): void
    {
        try {
            SentSmsLog::create([
                'user_id' => ($notifiable instanceof User && !$notifiable->is_admin && isset($notification->booking) && $notification->booking->user_id) ? $notification->booking->user_id : ($notifiable instanceof User ? $notifiable->id : null),
                'recipient_type' => ($notifiable instanceof User) ? ($notifiable->is_admin ? 'admin' : 'customer') : null,
                'notification_type' => get_class($notification), // أو اسم مخصص من الإشعار
                'to_number' => $toNumber,
                'content' => Str::limit($content, 450), // حد لتجنب الأخطاء إذا كان المحتوى طويلاً جداً
                'status' => $status,
                'service_message_id' => $serviceMessageId,
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log sent SMS attempt.", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify admins if SMS limit is reached.
     */
    protected function notifyAdminsOfLimitReached(int $limit, int $count): void
    {
        // إرسال إشعار مرة واحدة في اليوم لتجنب الإزعاج
        $cacheKey = 'sms_limit_reached_notification_sent_today';
        if (Cache::has($cacheKey)) {
            return;
        }

        $admins = User::where('is_admin', true)->get();
        if ($admins->isNotEmpty()) {
            // تأكد من إنشاء كلاس الإشعار SmsLimitReachedNotification
            // هذا الكلاس يجب أن يرسل بريداً إلكترونياً فقط للمديرين
            \Illuminate\Support\Facades\Notification::send($admins, new SmsLimitReachedNotification($limit, $count));
            Cache::put($cacheKey, true, Carbon::now()->endOfDay()); // وضع الكاش حتى نهاية اليوم
            Log::info("SmsLimitReachedNotification sent to admins.");
        }
    }
}