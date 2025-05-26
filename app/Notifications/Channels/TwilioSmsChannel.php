<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\Setting; // لاستيراد إعدادات Twilio من قاعدة البيانات
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;
use App\Models\SentSmsLog; // لتسجيل محاولات الإرسال
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TwilioSmsChannel
{
    protected string $accountSid;
    protected string $authToken;
    protected string $fromNumber;
    protected bool $isConfigured = false;
    protected ?TwilioClient $twilioClient = null;

    public function __construct()
    {
        $this->accountSid = Setting::where('key', 'twilio_account_sid')->value('value');
        $this->authToken = Setting::where('key', 'twilio_auth_token')->value('value');
        $this->fromNumber = Setting::where('key', 'twilio_sms_from_number')->value('value');

        if (!empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromNumber)) {
            try {
                $this->twilioClient = new TwilioClient($this->accountSid, $this->authToken);
                $this->isConfigured = true;
            } catch (TwilioException $e) {
                Log::error('TwilioSmsChannel: Failed to initialize Twilio client - ' . $e->getMessage());
                $this->isConfigured = false;
            }
        } else {
            Log::error('TwilioSmsChannel: Twilio Account SID, Auth Token, or From Number is not configured in database settings. SMS sending via Twilio will be disabled.');
            $this->isConfigured = false;
        }
    }

    public function send($notifiable, Notification $notification)
    {
        if (!$this->isConfigured || !$this->twilioClient) {
            Log::error('TwilioSmsChannel: Cannot send SMS. Channel is not properly configured.');
            // لا نسجل محاولة هنا لأن القناة نفسها غير مهيأة بشكل صحيح
            return null;
        }

        // يجب أن يحتوي الإشعار على دالة toTwilioSms أو دالة عامة مثل toSms
        if (!method_exists($notification, 'toSms') && !method_exists($notification, 'toTwilioSms')) {
            Log::warning('TwilioSmsChannel: Method toSms or toTwilioSms not found in notification.', ['notification' => get_class($notification)]);
            return null;
        }

        $messageData = method_exists($notification, 'toTwilioSms')
                       ? $notification->toTwilioSms($notifiable)
                       : $notification->toSms($notifiable); // استخدام toSms كدالة عامة

        $rawRecipientNumber = $messageData['to'] ?? null;
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable && isset($notifiable->routes[__CLASS__])) {
            $rawRecipientNumber = $notifiable->routes[__CLASS__];
        } elseif ($notifiable instanceof User && property_exists($notifiable, 'mobile_number')) {
            $rawRecipientNumber = $notifiable->mobile_number;
        } elseif (property_exists($notification, 'mobileNumber') && is_string($notification->mobileNumber)) {
            $rawRecipientNumber = $notification->mobileNumber;
        }
        
        $contentString = is_string($messageData['content']) ? $messageData['content'] : '';

        if (empty($rawRecipientNumber) || empty($contentString)) {
            Log::warning('TwilioSmsChannel: Recipient number (to) or message content is missing or empty.', ['notification' => get_class($notification)]);
            $this->logSmsAttempt($notifiable, $notification, 'failed_missing_data', null, $rawRecipientNumber, $contentString);
            return null;
        }

        // تنسيق رقم الجوال إلى صيغة E.164 (مهم لـ Twilio)
        $formattedRecipientNumber = $this->formatPhoneNumberForE164($rawRecipientNumber);
        if ($rawRecipientNumber !== $formattedRecipientNumber) {
            Log::debug("TwilioSmsChannel: Formatting recipient number for E.164. Original: [{$rawRecipientNumber}], Formatted: [{$formattedRecipientNumber}]");
        }
        
        $status = 'failed_submission';
        $serviceMessageId = null;

        try {
            $twilioMessage = $this->twilioClient->messages->create(
                $formattedRecipientNumber, // رقم المستلم المنسق
                [
                    'from' => $this->fromNumber, // رقم Twilio الخاص بك
                    'body' => $contentString
                ]
            );

            $serviceMessageId = $twilioMessage->sid;
            // Twilio لا يعيد حالة "sent" مباشرة، بل SID. حالة التسليم تأتي عبر webhooks.
            // للاستخدام الفوري، سنعتبر أن الإرسال للـ API ناجح يعني 'submitted' أو 'sent_to_provider'
            $status = 'sent_to_provider'; // أو 'sent' إذا كنت تعتبر الإرسال الناجح لـ Twilio كـ "مرسل"
            Log::info('TwilioSmsChannel: SMS submitted successfully via Twilio.', [
                'to' => $formattedRecipientNumber, 
                'message_sid' => $serviceMessageId,
                'twilio_status' => $twilioMessage->status // (e.g., queued, sent, failed, delivered) - initial status
            ]);

        } catch (TwilioException $e) {
            $status = 'failed_api_error';
            Log::error('TwilioSmsChannel: Failed to send SMS via Twilio API.', [
                'to' => $formattedRecipientNumber, 
                'error_code' => $e->getCode(), 
                'error_message' => $e->getMessage()
            ]);
        } catch (\Throwable $e) {
            $status = 'failed_exception';
            Log::critical('TwilioSmsChannel: Exception while sending SMS.', [
                'to' => $formattedRecipientNumber, 
                'exception' => $e->getMessage()
            ]);
        }

        $this->logSmsAttempt($notifiable, $notification, $status, $serviceMessageId, $formattedRecipientNumber, $contentString);
        
        // يمكنك إرجاع كائن TwilioMessage أو true/false
        return isset($twilioMessage) ? $twilioMessage : null;
    }

    /**
     * تنسيق رقم الهاتف إلى صيغة E.164.
     * مثال: +9665XXXXXXXX
     */
    protected function formatPhoneNumberForE164(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number); // إزالة كل ما ليس رقماً
        if (Str::startsWith($number, '05') && strlen($number) == 10) { // السعودية 05xxxxxxx
            return '+966' . substr($number, 1);
        }
        if (Str::startsWith($number, '5') && strlen($number) == 9) { // السعودية 5xxxxxxx
            return '+966' . $number;
        }
        if (Str::startsWith($number, '9665') && strlen($number) == 12) { // السعودية 9665xxxxxxx
            return '+' . $number;
        }
        // إذا كان الرقم يبدأ بـ + فهو على الأغلب بصيغة دولية بالفعل
        if (Str::startsWith($number, '+')) {
            return $number;
        }
        // كحالة افتراضية، هذا قد لا يكون كافياً لجميع الحالات الدولية
        // يفضل استخدام مكتبة مثل libphonenumber-for-php للتنسيق الدولي المعقد
        Log::warning("TwilioSmsChannel: Phone number '{$number}' may not be in E.164 format after basic formatting.");
        return '+' . $number; // محاولة بسيطة، قد تحتاج إلى تحسين
    }
    
    // دالة logSmsAttempt (مشابهة لتلك الموجودة في HttpSmsChannel)
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
                'service_message_id' => $serviceMessageId, // سيكون هنا Twilio Message SID
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("TwilioSmsChannel: Failed to log sent SMS attempt.", [
                'error' => $e->getMessage(), 'toNumber' => $toNumber, 'notification_class' => get_class($notification)
            ]);
        }
    }
}
