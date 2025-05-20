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
        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("BookingConfirmedNotification: Email not sent to notifiable ID {$notifiable->id}, email missing or invalid.", ['booking_id' => $this->booking->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("BookingConfirmedNotification: SMS template '{$templateKey}' for notifiable ID {$notifiable->id} not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        } else {
             Log::warning("BookingConfirmedNotification: SMS not sent to notifiable ID {$notifiable->id}, mobile_number missing.", ['booking_id' => $this->booking->id]);
        }
        
        if(empty($channels)){
            Log::error("BookingConfirmedNotification: No channels determined for notifiable ID {$notifiable->id}.", ['booking_id' => $this->booking->id, 'is_admin' => $notifiable->is_admin ?? 'N/A']);
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تأكيد حجز: تم تأكيد الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم تأكيد الحجز التالي بنجاح:")
                        ->line("- العميل: {$customerUser->name} (جوال: {$customerUser->mobile_number})")
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->action('عرض الحجز في لوحة التحكم', route('admin.bookings.show', $this->booking->id));
        } else { // للعميل
            $mailMessage->subject("تم تأكيد حجزك رقم #{$this->booking->id} لدى المصورة فاطمة!")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("يسرنا تأكيد حجزك للخدمة التالية لدى المصورة فاطمة علي:")
                        ->line("الخدمة: **{$serviceName}**")
                        ->line("التاريخ والوقت: **{$bookingDateTime}**")
                        ->line("رقم الحجز: **{$this->booking->id}**")
                        ->lineIf($this->booking->event_location, "مكان الحدث: **{$this->booking->event_location}**")
                        ->line("نشكر ثقتك ونتطلع لخدمتك قريباً. نرجو الالتزام بالموعد.")
                        ->action('عرض تفاصيل حجوزاتي', route('customer.bookings.index')); // أو customer.bookings.show
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

        // --- START: التعديل الهام هنا ---
        $templateIdentifier = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
        // --- END: التعديل الهام هنا ---

        $specificReplacements = [
            // لا توجد متغيرات إضافية محددة هنا بشكل افتراضي
            // المتغيرات الأساسية مثل [booking_date_time_short] ستأتي من ManagesSmsContent
        ];

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
            'recipient_id' => $notifiable->id,
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'event' => 'booking_confirmed',
            'channels_used' => $this->via($notifiable),
        ];
    }
}
