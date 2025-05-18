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


class BookingStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    public string $oldStatus;
    public string $newStatus;
    // public User $recipient; // $notifiable هو المستلم

    public function __construct(Booking $booking, string $oldStatus, string $newStatus/*, User $recipient*/)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        // $this->recipient = $recipient;
    }

    public function via(object $notifiable): array
    {
        if ($this->newStatus === $this->oldStatus) { return []; }

        $excludedStatuses = [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CANCELLED_BY_ADMIN,
            Booking::STATUS_CANCELLED_BY_USER,
        ];
        if (in_array($this->newStatus, $excludedStatuses)) {
            return []; // هذه الحالات لها إشعارات خاصة بها
        }

        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'booking_status_changed_admin' : 'booking_status_changed_customer';
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("BookingStatusChangedNotification: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    protected function translateStatus(string $statusKey): string
    {
        // ... (الكود الأصلي كما هو) ...
        if (method_exists($this->booking, 'getStatusLabelAttribute')) {
            // ...
        }
        $statusTranslations = Booking::getStatusesWithOptions(); // استخدام الدالة من الموديل مباشرة
        return $statusTranslations[$statusKey] ?? Str::title(str_replace('_', ' ', $statusKey));
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (الكود الأصلي كما هو) ...
        $newStatusText = $this->translateStatus($this->newStatus);
        $oldStatusText = $this->translateStatus($this->oldStatus);
        $customerUser = $this->booking->user;
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - H:i');
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تحديث حالة الحجز رقم #{$this->booking->id} إلى: {$newStatusText}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم تحديث حالة الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}.")
                        ->line("الحالة السابقة: {$oldStatusText}")
                        ->line("الحالة الجديدة: **{$newStatusText}**")
                        ->line("تفاصيل الحجز:")
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد: {$bookingDateTime}")
                        ->action('عرض الحجز', route('admin.bookings.show', $this->booking->id));
        } else {
            $mailMessage->subject("تحديث على حالة حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("تم تحديث حالة حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الحالة الجديدة لحجزك هي: **{$newStatusText}**.")
                        ->line("موعد الحجز: {$bookingDateTime}.")
                        ->line("للمزيد من التفاصيل أو في حال وجود أي استفسارات، يرجى مراجعة حسابك أو التواصل معنا.")
                        ->action('عرض تفاصيل حجزي', route('customer.bookings.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingStatusChangedNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $newStatusTranslated = $this->translateStatus($this->newStatus);
        $oldStatusTranslated = $this->translateStatus($this->oldStatus);
        $specificReplacements = [
            '[new_status_translated]' => Str::limit($newStatusTranslated, 25, '..'),
            '[old_status_translated]' => Str::limit($oldStatusTranslated, 25, '..'),
        ];

        $messageContent = $this->getSmsMessageContent('booking_status_changed', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
             Log::warning('BookingStatusChangedNotification (toHttpSms): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_status_changed']);
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
            'event' => 'booking_status_changed',
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
