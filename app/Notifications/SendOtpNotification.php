<?php

namespace App\Notifications;

use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Traits\ManagesSmsContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsTemplate;

class SendOtpNotification extends Notification
{
    use Queueable, ManagesSmsContent;

    public string $otpCode;
    public string $mobileNumber;

    /**
     * Create a new notification instance.
     *
     * @param string $otpCode
     * @param string $mobileNumber
     */
    public function __construct(string $otpCode, string $mobileNumber)
    {
        $this->otpCode = $otpCode;
        $this->mobileNumber = $mobileNumber;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->determineSmsChannels('otp_verification', $notifiable, true);
        
        // Final fallback if no channels determined but we have a mobile number
        if (empty($channels) && $this->mobileNumber) {
            $otpProvider = \App\Models\Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';
            if ($otpProvider === 'whatsapp') {
                $channels[] = WhatsAppChannel::class;
            } elseif ($otpProvider === 'httpsms') {
                $channels[] = HttpSmsChannel::class;
            }
        }

        return $channels;
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param  object  $notifiable
     * @return array
     */
    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($this->mobileNumber);
        
        $templateIdentifier = 'otp_verification';
        $specificReplacements = [
            '[otp_code]' => $this->otpCode,
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements);

        if (empty($messageContent)) {
            $messageContent = "رمز التحقق الخاص بك هو: {$this->otpCode}";
        }
        
        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

    /**
     * Get the WhatsApp representation of the notification.
     *
     * @param  object  $notifiable
     * @return array
     */
    public function toWhatsApp(object $notifiable): array
    {
        $smsData = $this->toHttpSms($notifiable);
        
        return [
            'to' => $this->formatWhatsAppRecipient($this->mobileNumber),
            'content' => $smsData['content'],
        ];
    }
}
