<?php

namespace App\Notifications;

// ... (use statements كما هي أو مع إضافة اللازم) ...
use App\Models\Booking;
// use App\Models\User;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Traits\ManagesSmsContent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsTemplate;
use Illuminate\Support\Str;


class AppointmentReminderNotification extends Notification implements ShouldQueue
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
        if ($notifiable->is_admin) { // هذا الإشعار لا يرسل للمدير
            Log::info("AppointmentReminderNotification: Skipped for admin user ID {$notifiable->id}.", ['booking_id' => $this->booking->id]);
            return [];
        }

        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("AppointmentReminderNotification: Email not sent to customer ID {$notifiable->id}, email missing or invalid.", ['booking_id' => $this->booking->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            // هذا الإشعار للعميل فقط، لذا المفتاح ثابت
            $templateKey = 'appointment_reminder_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("AppointmentReminderNotification: SMS template '{$templateKey}' for customer ID {$notifiable->id} not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        } else {
            Log::warning("AppointmentReminderNotification: SMS not sent to customer ID {$notifiable->id}, mobile_number missing.", ['booking_id' => $this->booking->id]);
        }
        
        if(empty($channels) && !$notifiable->is_admin){ // لا تسجل خطأ إذا كان مديراً وتم تخطيه عمداً
            Log::error("AppointmentReminderNotification: No channels determined for customer ID {$notifiable->id}.", ['booking_id' => $this->booking->id]);
        }
        return $channels;
    }

    public function toMail(object $notifiable): ?MailMessage // يمكن أن يكون null إذا كان المستلم مديراً
    {
        if ($notifiable->is_admin) {
            return null;
        }

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
                    ->action('عرض تفاصيل الحجز', route('customer.bookings.index')) // أو customer.bookings.show
                    ->line('نتطلع لرؤيتك قريباً!')
                    ->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        if ($notifiable->is_admin) {
            return [];
        }
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('AppointmentReminderNotification (toHttpSms): Recipient mobile number could not be determined for customer ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        // --- START: التعديل الهام هنا (على الرغم من أنه للعميل فقط، من الأفضل أن يكون واضحًا) ---
        $templateIdentifier = 'appointment_reminder_customer'; // مفتاح خاص بهذا الإشعار للعميل
        // --- END: التعديل الهام هنا ---

        $specificReplacements = [
            // لا توجد متغيرات إضافية محددة هنا بشكل افتراضي
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
             Log::warning('AppointmentReminderNotification (toHttpSms): SMS message content is empty after processing template for customer ID ' . $notifiable->id, [
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
