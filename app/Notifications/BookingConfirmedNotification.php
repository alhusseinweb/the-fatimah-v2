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

class BookingConfirmedNotification extends Notification implements ShouldQueue
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
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number && config('services.sms_gateway.enabled', env('SMS_GATEWAY_ENABLED', false))) {
            $channels[] = AndroidSmsGatewayChannel::class; // <-- تم التغيير
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (الكود الأصلي كما هو) ...
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تأكيد حجز: تم تأكيد الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم تأكيد الحجز التالي بنجاح:")
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
                        ->action('عرض تفاصيل حجوزاتي', route('customer.bookings.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }

    public function toSmsGateway(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingConfirmedNotification (toSmsGateway): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        // لا توجد متغيرات إضافية محددة هنا، ستستخدم المتغيرات الأساسية
        $messageContent = $this->getSmsMessageContent('booking_confirmed', $notifiable, [], $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingConfirmedNotification (toSmsGateway): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_confirmed']);
            return [];
        }

        return [
            'to' => $recipientPhoneNumber,
            'message' => $messageContent,
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
