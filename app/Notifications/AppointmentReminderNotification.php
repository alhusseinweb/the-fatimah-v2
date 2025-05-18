<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Channels\AndroidSmsGatewayChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent; // <-- تم الإضافة
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent; // <-- تم الإضافة

    public Booking $booking;
    // public User $recipient; // $notifiable هو الـ recipient

    public function __construct(Booking $booking /*, User $recipient*/)
    {
        $this->booking = $booking;
        // $this->recipient = $recipient;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->is_admin) {
            return [];
        }

        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if (!$notifiable->is_admin && $notifiable->mobile_number && config('services.sms_gateway.enabled', env('SMS_GATEWAY_ENABLED', false))) {
            $channels[] = AndroidSmsGatewayChannel::class; // <-- تم التغيير
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

    public function toSmsGateway(object $notifiable): array
    {
        if ($notifiable->is_admin) { return []; }

        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('AppointmentReminderNotification (toSmsGateway): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        // المتغيرات الخاصة مثل [booking_date_short] و [booking_time_short] موجودة في $baseReplacements
        // التي يتم دمجها داخل getSmsMessageContent
        $specificReplacements = [
            // يمكنك إضافة متغيرات خاصة جداً بهذا الإشعار هنا إذا لزم الأمر
            // مثال: '[custom_reminder_note]' => 'لا تنسَ تجهيزاتك!'
        ];

        $messageContent = $this->getSmsMessageContent('appointment_reminder', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
             Log::warning('AppointmentReminderNotification (toSmsGateway): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'appointment_reminder']);
            return [];
        }

        return [
            'to' => $recipientPhoneNumber,
            'message' => $messageContent,
        ];
    }

    public function toArray(object $notifiable): array
    {
        if ($notifiable->is_admin) { return []; }
        return [
            'booking_id' => $this->booking->id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => 'customer', // هذا الإشعار للعميل فقط
            'event' => 'appointment_reminder',
            'channels_used' => $this->via($notifiable),
        ];
    }
}
