<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class InfobipSmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // التأكد من أن الإشعار يوفر متد toInfobipSms
        if (! method_exists($notification, 'toInfobipSms')) {
            throw new \Exception('Notification is missing the toInfobipSms method.');
        }

        // الحصول على بيانات الرسالة من الإشعار
        $messageData = $notification->toInfobipSms($notifiable);

        // التحقق من وجود رقم المستلم ومحتوى الرسالة
        if (empty($messageData['to']) || empty($messageData['text'])) {
            Log::warning('InfobipSmsChannel: Missing recipient number or message text.', ['notifiable' => $notifiable]);
            return;
        }

        $baseUrl = Config::get('services.infobip.base_url');
        $apiKey = Config::get('services.infobip.api_key');
        $senderId = Config::get('services.infobip.sender_id'); // استخدم Sender ID إذا كنت ستستخدمه

        if (empty($baseUrl) || empty($apiKey)) {
             Log::error('InfobipSmsChannel: Infobip API base URL or key not configured.');
             return; // أو يمكنك إلقاء Exception
        }

        // بناء جسم الطلب (Payload)
        $payload = [
            'messages' => [
                [
                    'destinations' => [['to' => $messageData['to']]],
                    'from' => $senderId, // استخدم Sender ID
                    'text' => $messageData['text'],
                ]
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $apiKey, // استخدام صيغة المصادقة الصحيحة
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->baseUrl($baseUrl)
              ->post('/sms/2/text/advanced', $payload);

            if ($response->successful()) {
                Log::info('Infobip SMS sent successfully via channel', [
                    'to' => $messageData['to'],
                    'response' => $response->json()
                ]);
            } else {
                Log::error('Infobip SMS sending failed via channel', [
                    'to' => $messageData['to'],
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                // يمكنك هنا رمي Exception إذا أردت أن يتم تسجيل فشل الإشعار في جدول failed_jobs
                // throw new \Exception('Infobip SMS sending failed.');
            }

        } catch (\Exception $e) {
            Log::error('Infobip SMS sending exception via channel', [
                'to' => $messageData['to'],
                'exception' => $e->getMessage()
            ]);
             // throw new \Exception('Infobip SMS sending exception.');
        }
    }
}