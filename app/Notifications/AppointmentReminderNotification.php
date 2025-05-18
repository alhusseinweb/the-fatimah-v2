<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Channels\HttpSmsChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // لإستخدام via
use App\Models\SmsTemplate;      // لإستخدام via
use Illuminate\Support\Str;


class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    // public User $recipient; // $notifiable هو المستلم

    public function __construct(Booking $booking /*, User $recipient*/)
    {
        $this->booking = $booking;
        // $this->recipient = $recipient;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->is_admin) { // لا يرسل للمدير
            return [];
        }

        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if (!$notifiable->is_admin && $notifiable->mobile_number) { // التأكد أنه ليس مديرًا
            $templateKey = 'appointment_reminder_customer'; // هذا الإشعار للعميل فقط
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("AppointmentReminderNotification: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        if ($notifiable->is_admin) {
            return null;
        }
        // ... (الكود الأصلي كما هو) ...
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime);
        $eventLocation = $this->booking->event_location ?? 'حسب الاتفاق';

        return (new MailMessage)
                    ->subject('تذكير بموعدك القادم لدى المصورة فاطمة علي')
                    ->greeting("مرحباً {$notifiable->name},")
                    ->line("نود تذكيرك بموعد تصويرك القادم لدى المصورة فاطمة علي:")
                    ->line("الخدمة: **{$serviceName}**")
                    ->line("التاريخ: **" . $bookingDateTime->translatedFormat('l, d F Y') . "**")
                    ->line("الوقت: **" . $bookingDateTime->translatedFormat('h:i A') . "**")
                    ->line("الموقع: **{$eventLocation}**")
                    ->line("رقم الحجز: **{$this->booking->id}**")
                    ->line("نرجو الالتزام بالموعد المحدد. إذا كنت بحاجة إلى تعديل الموعد، يرجى التواصل معنا في أقرب وقت ممكن.")
                    ->action('عرض تفاصيل الحجز', route('customer.bookings.index'))
                    ->line('نتطلع لرؤيتك قريباً!')
                    ->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }

    public function toHttpSms(object $notifiable): array
    {
        if ($notifiable->is_admin) { // تأكيد إضافي
            return [];
        }
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('AppointmentReminderNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        // المتغيرات الخاصة مثل [booking_date_short] و [booking_time_short] موجودة في $baseReplacements
        // التي يتم دمجها داخل getSmsMessageContent
        $specificReplacements = [
            // يمكنك إضافة متغيرات خاصة إضافية هنا إذا لزم الأمر
            // مثال: '[location_short]' => Str::limit($this->booking->event_location ?? 'الموقع', 15),
        ];

        $messageContent = $this->getSmsMessageContent('appointment_reminder_customer', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
             Log::warning('AppointmentReminderNotification (toHttpSms): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'appointment_reminder_customer']);
            return [];
        }

        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

    public function toArray(object $notifiable): array
    {
        if ($notifiable->is_admin) { return []; }
        return [
            'booking_id' => $this->booking->id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => 'customer',
            'event' => 'appointment_reminder',
            'channels_used' => $this->via($notifiable),
        ];
    }
}
