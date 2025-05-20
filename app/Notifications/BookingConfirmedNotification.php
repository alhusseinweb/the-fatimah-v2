<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Traits\ManagesSmsContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsTemplate;
use Illuminate\Support\Str;
use Carbon\Carbon; // تأكد من وجود هذا

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        $logContext = [
            'notification' => class_basename($this),
            'notifiable_id' => $notifiable->id,
            'notifiable_type' => get_class($notifiable),
            'is_admin' => $notifiable->is_admin ?? 'N/A',
            'booking_id' => $this->booking->id
        ];

        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
            Log::info('BookingConfirmedNotification: Mail channel ADDED.', $logContext + ['email' => $notifiable->email]);
        } else {
            Log::warning("BookingConfirmedNotification: Mail channel SKIPPED (email missing or invalid).", $logContext + ['email_provided' => $notifiable->email ?? 'N/A']);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(5), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
                Log::info("BookingConfirmedNotification: HttpSmsChannel ADDED.", $logContext + ['template_key' => $templateKey]);
            } else {
                Log::warning("BookingConfirmedNotification: HttpSmsChannel SKIPPED (template '{$templateKey}' not found or inactive).", $logContext);
            }
        } else {
             Log::warning("BookingConfirmedNotification: HttpSmsChannel SKIPPED (mobile_number missing).", $logContext);
        }
        
        if(empty($channels)){
            Log::error("BookingConfirmedNotification: No channels determined.", $logContext);
        } else {
            Log::info("BookingConfirmedNotification: Channels determined.", $logContext + ['channels' => $channels]);
        }
        return $channels;
    }

    // ... (دوال toMail, toHttpSms, toArray كما قدمتها لك سابقًا، فهي صحيحة من حيث مفتاح القالب) ...
    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تأكيد حجز إداري: تم تأكيد الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}")
                        ->greeting("مرحباً أيها المدير،")
                        // ... (بقية محتوى البريد للمدير)
                        ->line("تم تأكيد الحجز التالي بنجاح (بواسطة إجراء إداري أو عملية دفع مكتملة):")
                        ->line("- العميل: {$customerUser->name} (جوال: {$customerUser->mobile_number})")
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->action('عرض الحجز في لوحة التحكم', route('admin.bookings.show', $this->booking->id));
        } else { // للعميل
            $mailMessage->subject("تم تأكيد حجزك رقم #{$this->booking->id} لدى المصورة فاطمة!")
                        ->greeting("مرحباً {$notifiable->name},")
                        // ... (بقية محتوى البريد للعميل)
                        ->line("يسرنا تأكيد حجزك للخدمة التالية لدى المصورة فاطمة علي:")
                        ->line("الخدمة: **{$serviceName}**")
                        ->line("التاريخ والوقت: **{$bookingDateTime}**")
                        ->line("رقم الحجز: **{$this->booking->id}**")
                        ->lineIf($this->booking->event_location, "مكان الحدث: **{$this->booking->event_location}**")
                        ->line("نشكر ثقتك ونتطلع لخدمتك قريباً. نرجو الالتزام بالموعد.")
                        ->action('عرض تفاصيل حجوزاتي', route('customer.bookings.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingConfirmedNotification (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateIdentifier = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
        
        $specificReplacements = [ /* ... أي متغيرات خاصة تحتاجها ... */ ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingConfirmedNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
                'booking_id' => $this->booking->id, 
                'template_identifier_used' => $templateIdentifier
            ]);
            return [];
        }

        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

     public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            // ... (بقية البيانات)
        ];
    }
}
