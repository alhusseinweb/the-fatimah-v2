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

class BookingConfirmedNotification extends Notification implements ShouldQueue
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
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
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

    // ... (دالة toMail كما هي) ...
    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customer = $this->booking->user;

        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تأكيد حجز: تم تأكيد الحجز رقم #{$this->booking->id} للعميل {$customer->name}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم تأكيد الحجز التالي بنجاح:")
                        ->line("- العميل: {$customer->name} (جوال: {$customer->mobile_number})")
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->action('عرض الحجز في لوحة التحكم', route('admin.bookings.show', $this->booking->id));
        } else {
            $mailMessage->subject("تم تأكيد حجزك رقم #{$this->booking->id} لدى المصورة فاطمة!")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("يسرنا تأكيد حجزك للخدمة التالية لدى المصورة فاطمة علي:")
                        ->line("الخدمة: **{$serviceName}**")
                        ->line("التاريخ والوقت: **{$bookingDateTime}**")
                        ->line("رقم الحجز: **{$this->booking->id}**")
                        ->lineIf($this->booking->event_location, "مكان الحدث: **{$this->booking->event_location}**")
                        ->line("نشكر ثقتك ونتطلع لخدمتك قريباً. نرجو الالتزام بالموعد.")
                        ->action('عرض تفاصيل حجوزاتي', route('customer.bookings.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }


    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) { $recipientPhoneNumber = $notifiable->mobile_number; }
        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('BookingConfirmedNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['booking_id' => $this->booking->id]);
            if ($notifiable->is_admin) {
                $templateContent = "تأكيد حجز للمدير. عميل:[customer_name_short]. خدمة:[service_name_short]. رقم:[booking_id]. موعد:[booking_date_time_short].";
            } else {
                $templateContent = "تم تأكيد حجزك المصورة فاطمة. خدمة:[service_name_short]. موعدك:[booking_date_time_short]. رقم:[booking_id].";
            }
        }

        $parsedBookingDateTime = Carbon::parse($this->booking->booking_datetime);
        $replacements = [
            '[customer_name]' => $this->booking->user->name ?? 'العميل',
            '[customer_name_short]' => $this->booking->user ? Str::limit($this->booking->user->name, 10) : "عميل",
            '[customer_mobile]' => $this->booking->user->mobile_number ?? '',
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : 'تصوير',
            '[service_name_short]' => $this->booking->service ? Str::limit($this->booking->service->name_ar, 12, '') : "تصوير",
            '[booking_id]' => $this->booking->id,
            '[booking_date_time]' => $parsedBookingDateTime->translatedFormat('l, d M Y - h:i A'),
            '[booking_date_time_short]' => $parsedBookingDateTime->translatedFormat('d-m H:i'), // مثال: "15-05 16:00"
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('BookingConfirmedNotification: Final SMS content from DB template.', [
            'template_key' => $templateKey,
            'to' => $recipientPhoneNumber,
            'final_content' => $messageContent,
        ]);

        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

    // ... (دالة toArray كما هي) ...
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'event' => 'booking_confirmed',
            'channels_used' => $this->via($notifiable),
        ];
    }
}