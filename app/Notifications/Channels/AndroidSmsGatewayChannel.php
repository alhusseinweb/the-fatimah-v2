<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;
use Exception; // تم تغييرها إلى Exception الأعم لالتقاط أخطاء المكتبة أيضًا
use Illuminate\Support\Facades\Log; // لاستخدام اللوج
use Throwable; // لاستخدام Throwable في catch block

class AndroidSmsGatewayChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable The entity (e.g., User model) receiving the notification.
     * @param  \Illuminate\Notifications\Notification  $notification The notification instance.
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // 1. التأكد من وجود دالة لتحضير بيانات الرسالة في كلاس الإشعار
        if (! method_exists($notification, 'toAndroidSmsGateway')) {
            Log::warning('Method toAndroidSmsGateway not found in notification.', ['notification' => get_class($notification)]);
            return;
        }

        // 2. الحصول على بيانات الرسالة (النص) من الإشعار
        $messageData = $notification->toAndroidSmsGateway($notifiable);
        if (!isset($messageData['content']) || empty(trim($messageData['content']))) {
             Log::warning('SMS content is empty for notification.', ['notification' => get_class($notification)]);
             return;
        }
        // تأكد من أن المحتوى هو نص
        $messageContent = is_string($messageData['content']) ? $messageData['content'] : strval($messageData['content']);


        // 3. الحصول على رقم الجوال للمستلم
        if (! $recipient = $notifiable->routeNotificationFor('sms', $notification)) {
             Log::warning('Recipient mobile number not found for SMS notification.', [ // السجل الذي ظهر لديك سابقاً
                'notification' => get_class($notification),
                'notifiable_id' => $notifiable->getKey() ?? 'N/A',
                'notifiable_type' => get_class($notifiable)
             ]);
            return; // لا يمكن الإرسال بدون رقم
        }
         // تأكد من أن الرقم هو نص
         $recipient = is_string($recipient) ? $recipient : strval($recipient);


        // --- !!! إضافة تسجيل هنا للتشخيص !!! ---
        Log::debug('Data prepared for AndroidSmsGatewayChannel', [
            'notification' => get_class($notification),
            'recipient_number_resolved' => $recipient, // سجل الرقم الذي تم جلبه بالفعل
            'message_content_preview' => mb_substr($messageContent, 0, 50) . '...' // سجل جزء من الرسالة
        ]);
        // --- !!! نهاية إضافة التسجيل !!! ---


        // 4. الحصول على إعدادات البوابة من config
        $login = config('services.sms_gateway.login');
        $password = config('services.sms_gateway.password');
        $url = config('services.sms_gateway.url');

        // التحقق من وجود الإعدادات الأساسية
        if (!$login || !$password) {
            Log::error('SMS Gateway login or password not configured in config/services.php or .env file.');
            return;
        }
         if (!$url) {
             Log::warning('SMS Gateway URL not configured, using library default.', ['notification' => get_class($notification)]);
         }

        Log::info("Attempting to send SMS notification via Android Gateway.", [ // السجل الذي رأيته
                'notification' => get_class($notification),
                'recipient_preview' => substr($recipient, 0, 5) . '...', // لا تسجل الرقم كاملاً في info
                'url_used' => $url ?: 'Default'
            ]);

        // 5. محاولة إرسال الرسالة
        try {
            // إنشاء العميل مع تمرير العنوان
            $client = new Client($login, $password, $url); // استخدام العنوان هنا

            // إنشاء الرسالة (استخدام المحتوى والمستلم)
            $message = new Message($messageContent, [$recipient]);

            // إرسال الرسالة
            $messageState = $client->Send($message); // <-- الخطأ 400 حدث بسبب هذه المحاولة

            // هذا السجل لن يظهر إذا حدث خطأ في السطر السابق
            Log::info('SMS notification sent successfully via Android Gateway.', [
                'notification' => get_class($notification),
                'message_id' => $messageState->ID()
                ]);

        } catch (Throwable $e) { // استخدام Throwable لالتقاط أوسع للأخطاء بما في ذلك أخطاء المكتبة
            // !!! تسجيل الخطأ الذي رأيته !!!
            Log::error('Error sending SMS notification via Android Gateway: ' . $e->getMessage(), [
                'notification' => get_class($notification),
                'recipient_number_used' => $recipient, // سجل الرقم المستخدم عند حدوث الخطأ
                'exception_class' => get_class($e),
                // إضافة تفاصيل إضافية إذا كانت متوفرة في الاستثناء (قد تختلف حسب نوع الاستثناء)
                // 'exception_code' => $e->getCode(),
                // 'exception_trace' => $e->getTraceAsString() // كن حذراً، قد يكون التتبع طويلاً جداً
            ]);
            // يمكنك اختيار إعادة إلقاء الاستثناء إذا أردت أن تفشل مهمة الطابور
            // throw $e;
        }
    }
}