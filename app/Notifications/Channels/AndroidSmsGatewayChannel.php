<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use AndroidSmsGateway\Client as SmsGatewayClient; // تم تغيير الاسم لتجنب التعارض المحتمل
use AndroidSmsGateway\Domain\Message as SmsGatewayMessage; // تم تغيير الاسم
use Illuminate\Support\Facades\Log;
use Throwable; // لالتقاط جميع أنواع الأخطاء والاستثناءات

class AndroidSmsGatewayChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        // 1. التحقق من تفعيل إرسال الرسائل النصية بشكل عام
        if (!config('services.sms_gateway.enabled', env('SMS_GATEWAY_ENABLED', false))) {
            Log::info('AndroidSmsGatewayChannel: SMS sending is disabled via config/env.', [
                'notification' => get_class($notification),
                'notifiable_id' => $notifiable->getKey() ?? 'N/A',
            ]);
            return;
        }

        // 2. التأكد من وجود دالة لتحضير بيانات الرسالة في كلاس الإشعار
        if (!method_exists($notification, 'toSmsGateway')) { // تم تغيير الاسم إلى toSmsGateway ليكون أكثر عمومية
            Log::error('AndroidSmsGatewayChannel: Method toSmsGateway not found in notification.', [
                'notification' => get_class($notification)
            ]);
            return;
        }

        // 3. الحصول على بيانات الرسالة من الإشعار
        $messageData = $notification->toSmsGateway($notifiable);

        if (empty($messageData) || !is_array($messageData) || empty(trim($messageData['message'] ?? ''))) {
            Log::warning('AndroidSmsGatewayChannel: SMS message data is invalid, empty, or content is missing.', [
                'notification' => get_class($notification),
                'message_data_received' => $messageData // سجل البيانات المستلمة للتشخيص
            ]);
            return;
        }
        $messageContent = strval($messageData['message']); // تأكد أن المحتوى نصي

        // 4. الحصول على رقم الجوال للمستلم من الإشعار أو من $notifiable مباشرة
        //    الافتراض أن toSmsGateway ستعيد الرقم في 'to' أو أن $notifiable لديه routeNotificationFor
        $recipient = $messageData['to'] ?? $notifiable->routeNotificationFor('sms', $notification);

        if (!$recipient) {
            Log::warning('AndroidSmsGatewayChannel: Recipient mobile number not found for SMS notification.', [
                'notification' => get_class($notification),
                'notifiable_id' => $notifiable->getKey() ?? 'N/A',
                'notifiable_type' => get_class($notifiable)
            ]);
            return;
        }
        $recipient = strval($recipient); // تأكد أن الرقم نصي

        // 5. الحصول على إعدادات البوابة من config/services.php (مفضل) أو .env
        $login = config('services.sms_gateway.login', env('SMS_GATEWAY_LOGIN')); // افترض أن لديك هذه المتغيرات
        $password = config('services.sms_gateway.password', env('SMS_GATEWAY_PASSWORD')); // افترض أن لديك هذه المتغيرات
        $url = config('services.sms_gateway.url', env('SMS_GATEWAY_API_URL')); // تم توحيد الاسم ليطابق ما استخدمناه سابقًا
        $deviceId = $messageData['device_id'] ?? config('services.sms_gateway.device_id', env('SMS_GATEWAY_DEVICE_ID')); // السماح بتحديد الجهاز لكل رسالة

        if (!$login || !$password || !$url) {
            Log::critical('AndroidSmsGatewayChannel: SMS Gateway URL, login, or password not configured.', [
                'url_configured' => !empty($url),
                'login_configured' => !empty($login),
                // لا تسجل كلمة المرور
            ]);
            return;
        }

        Log::info('AndroidSmsGatewayChannel: Attempting to send SMS.', [
            'notification' => get_class($notification),
            'recipient_preview' => substr($recipient, 0, 5) . str_repeat('*', max(0, strlen($recipient) - 8)) . substr($recipient, -3), // إخفاء جزء من الرقم
            'gateway_url_preview' => Str::limit($url, 30, '...'),
            'device_id_used' => $deviceId ?? 'Default/None'
        ]);

        // 6. محاولة إرسال الرسالة
        try {
            // تأكد من أن المكتبة تتوقع هذه المعلمات بهذه الطريقة
            // قد تحتاج المكتبة إلى Client::create($login, $password, $url) أو طريقة أخرى
            $client = new SmsGatewayClient($login, $password, $url);

            // تحقق من وثائق المكتبة لكيفية إضافة device_id إذا كان مدعومًا مباشرة في كائن Message
            // أو إذا كان يجب إرساله كجزء من خيارات الإرسال أو الـ payload.
            // الافتراض الحالي هو أن $recipient هو رقم الهاتف.
            // قد تتطلب المكتبة أن يكون $recipient مصفوفة من الأرقام.
            $smsMessage = new SmsGatewayMessage($messageContent, [$recipient]);

            // إذا كان deviceId مطلوبًا كجزء من الرسالة أو خياراتها (هذا يعتمد على المكتبة)
            // مثال (قد لا يكون هذا هو الصحيح للمكتبة التي تستخدمها):
            // if ($deviceId) {
            //     $smsMessage->setDeviceId($deviceId); // أو أي دالة مشابهة
            // }

            $messageState = $client->Send($smsMessage);

            // تحقق من استجابة $messageState إذا كانت المكتبة توفر تفاصيل
            // (مثل ID الرسالة، حالة الإرسال من البوابة)
            Log::info('AndroidSmsGatewayChannel: SMS sent successfully request submitted to gateway.', [
                'notification' => get_class($notification),
                'recipient_preview' => substr($recipient, 0, 5) . '***',
                'message_state_id' => method_exists($messageState, 'ID') ? $messageState->ID() : 'N/A', // مثال
                // سجل أي معلومات مفيدة أخرى من $messageState
            ]);

        } catch (Throwable $e) {
            Log::error('AndroidSmsGatewayChannel: Error sending SMS.', [
                'notification' => get_class($notification),
                'recipient_preview' => substr($recipient, 0, 5) . '***',
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                // 'exception_trace' => $e->getTraceAsString(), // استخدم بحذر في الإنتاج
            ]);
            // يمكنك اختيار إعادة إلقاء الاستثناء إذا أردت أن تفشل مهمة الطابور ويتم إعادة محاولتها
            // throw $e;
        }
    }
}
