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
use Carbon\Carbon;

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
        $logContext = [ /* ... */ ]; // كما في الكود السابق

        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("BookingConfirmedNotification: Mail channel SKIPPED (email missing or invalid).", $logContext + ['email_provided' => $notifiable->email ?? 'N/A', 'notifiable_id' => $notifiable->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_confirmed_admin' : 'booking_confirmed_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) { // زيادة مدة الكاش قليلاً
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("BookingConfirmedNotification: HttpSmsChannel SKIPPED (template '{$templateKey}' not found or inactive).", $logContext + ['notifiable_id' => $notifiable->id]);
            }
        } else {
             Log::warning("BookingConfirmedNotification: HttpSmsChannel SKIPPED (mobile_number missing).", $logContext + ['notifiable_id' => $notifiable->id]);
        }
        
        if(empty($channels)){
            Log::error("BookingConfirmedNotification: No channels determined.", $logContext + ['notifiable_id' => $notifiable->id]);
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (منطق toMail كما هو في الكود السابق)
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تأكيد حجز إداري: تم تأكيد الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم تأكيد الحجز التالي بنجاح (بواسطة إجراء إداري أو عملية دفع مكتملة):")
                        ->line("- العميل: {$customerUser->name} (جوال: {$customerUser->mobile_number})")
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
                        ->action('عرض تفاصيل حجوزاتي', route('customer.bookings.index')); // افترض أن هذا المسار موجود
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
        
        // --- START: إضافة متغيرات التاريخ والوقت ---
        $bookingDateFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('Y/m/d'); // تنسيق قصير
        $bookingTimeFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('h:ia');    // تنسيق قصير
        
        $specificReplacements = [
            '[booking_date]' => $bookingDateFormatted,
            '[booking_time]' => $bookingTimeFormatted,
            '[booking_date_time]' => $bookingDateFormatted . ' ' . $bookingTimeFormatted,
            // أضف أي متغيرات أخرى خاصة بهذا الإشعار إذا لزم الأمر
        ];
        // --- END: إضافة متغيرات التاريخ والوقت ---

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingConfirmedNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
                'booking_id' => $this->booking->id, 
                'template_identifier_used' => $templateIdentifier,
                'is_admin_recipient' => $notifiable->is_admin ?? 'N/A'
            ]);
            // رسالة افتراضية إذا فشل القالب
            // if (!$notifiable->is_admin && $templateIdentifier === 'booking_confirmed_customer') {
            //     $messageContent = "تم تأكيد حجزك رقم {$this->booking->id} بتاريخ {$bookingDateFormatted} الساعة {$bookingTimeFormatted}. شكراً لك.";
            // } elseif ($notifiable->is_admin && $templateIdentifier === 'booking_confirmed_admin') {
            //     $messageContent = "تنبيه إداري: تم تأكيد الحجز رقم {$this->booking->id} للعميل {$this->booking->user?->name}.";
            // }
            // if (empty($messageContent)) return [];
        }

        if (empty($messageContent)) { // تحقق أخير
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
