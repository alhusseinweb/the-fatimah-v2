<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\HttpSmsChannel;
use App\Models\Booking;
use App\Models\User;
use App\Models\SmsTemplate; // <-- استيراد
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- استيراد
use Illuminate\Support\Str;
use Carbon\Carbon;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public User $recipient;

    public function __construct(Booking $booking, User $recipient)
    {
        $this->booking = $booking;
        $this->recipient = $recipient;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        // لا نرسل إشعار تذكير للمدير، فقط للعميل
        if ($notifiable->is_admin) {
            return [];
        }

        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = 'appointment_reminder_customer'; // هذا الإشعار للعميل فقط
            $templateExists = Cache::rememberForever('sms_template_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("SMS template not found for [{$templateKey}], HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        if ($notifiable->is_admin) {
            return null;
        }
        // ... (الكود الأصلي لدالة toMail)
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
        // هذا الإشعار موجه للعميل فقط، لا حاجة للتحقق من is_admin هنا
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) {
            $recipientPhoneNumber = $notifiable->mobile_number;
        }

        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('AppointmentReminderNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = 'appointment_reminder_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default or skipping.", ['booking_id' => $this->booking->id]);
            $templateContent = "تذكير بموعدك المصورة فاطمة. خدمة: [service_name_short]. تاريخ: [booking_date_short] وقت: [booking_time_short]. رقم: [booking_id]."; // نص افتراضي
        }

        $parsedBookingDateTime = Carbon::parse($this->booking->booking_datetime);
        $replacements = [
            '[customer_name]' => $notifiable->name ?? 'عميلنا العزيز',
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : "تصوير",
            '[service_name_short]' => $this->booking->service ? Str::limit($this->booking->service->name_ar, 15, '') : "تصوير",
            '[booking_id]' => $this->booking->id,
            '[booking_date_time]' => $parsedBookingDateTime->translatedFormat('l, d M Y - h:i A'),
            '[booking_date_short]' => $parsedBookingDateTime->translatedFormat('d-m'), // مثال: "15-05"
            '[booking_time_short]' => $parsedBookingDateTime->translatedFormat('h:iA'), // مثال: "04:00م"
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('AppointmentReminderNotification: Final SMS content from DB template.', [
            'template_key' => $templateKey,
            'to' => $recipientPhoneNumber,
            'final_content' => $messageContent,
        ]);

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