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


class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    // public User $recipient; // $notifiable هو المستلم
    public ?User $actor;
    public ?string $cancellationReason;

    public function __construct(Booking $booking, /* User $recipient, */ ?User $actor = null, ?string $cancellationReason = null)
    {
        $this->booking = $booking;
        // $this->recipient = $recipient;
        $this->actor = $actor;
        $this->cancellationReason = $cancellationReason;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'booking_cancelled_admin' : 'booking_cancelled_customer';
            // الكود الأصلي كان يستخدم Cache::remember مع مدة مختلفة، يمكنك تعديله إذا أردت
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("BookingCancelledNotification: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    protected function getCancelledByTextForNotifiable(object $notifiable): string
    {
        // ... (الكود الأصلي كما هو) ...
        if (!$this->actor) return "بواسطة النظام";
        if ($notifiable->id === $this->actor->id) return "بطلب منك"; // إذا كان المستلم هو الفاعل
        if ($this->actor->is_admin) return "من قبل إدارة المصورة";
        if ($this->booking->user && $this->actor->id === $this->booking->user_id) return "من قبل العميل (" . Str::limit($this->actor->name, 15) . ")";
        return "بواسطة طرف آخر";
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (الكود الأصلي كما هو) ...
        $serviceName = $this->booking->service?->name_ar ?: 'الخدمة المحددة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime, config('app.timezone'))->translatedFormat('l، d F Y - h:i A');
        $customerName = $this->booking->user?->name ?: 'العميل';
        $customerMobile = $this->booking->user?->mobile_number ?: 'غير متوفر';
        $cancelledByText = $this->getCancelledByTextForNotifiable($notifiable);
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $subjectCancelledBy = $this->actor ? ($this->booking->user && $this->actor->id === $this->booking->user_id ? "العميل " . $customerName : ($this->actor->is_admin ? "الإدارة" : "النظام")) : "النظام";
            $mailMessage->subject("تنبيه: تم إلغاء الحجز رقم #{$this->booking->id} (بواسطة {$subjectCancelledBy})")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم إلغاء الحجز رقم #{$this->booking->id} الخاص بالعميل: {$customerName} (جوال: {$customerMobile}).")
                        ->line("الخدمة الملغاة: {$serviceName}")
                        ->line("موعد الحجز الملغى: {$bookingDateTime}")
                        ->line("تم الإلغاء بواسطة: " . $cancelledByText . ".");
            if ($this->cancellationReason) {
                $mailMessage->line("سبب الإلغاء المذكور: " . $this->cancellationReason);
            }
            $mailMessage->action('مراجعة الحجز الملغي', route('admin.bookings.show', $this->booking->id));
        } else {
            $mailMessage->subject("إشعار بإلغاء حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نأسف لإعلامك بأنه تم إلغاء حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الموعد الذي تم إلغاؤه: {$bookingDateTime}.")
                        ->line("تم الإلغاء: " . $cancelledByText . ".");
            if ($this->cancellationReason && $this->actor && $this->actor->is_admin) {
                $mailMessage->line("السبب الموضح من الإدارة: " . $this->cancellationReason);
            }
            $mailMessage->line("إذا كان لديك أي استفسارات أو كنت ترغب في إعادة الحجز، يرجى التواصل معنا أو زيارة الموقع.")
                        ->action('تصفح الخدمات المتاحة', route('services.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة ' . config('app.photographer_name', 'فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingCancelledNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $cancelledByShortForSms = "";
        if ($this->actor) {
            if ($notifiable->id !== $this->actor->id) {
                 $cancelledByShortForSms = $this->actor->is_admin ? " (الادارة)" : ($this->booking->user && $this->actor->id === $this->booking->user_id ? " (العميل)" : " (النظام)");
            }
        } else {
             $cancelledByShortForSms = " (النظام)";
        }

        $cancellationReasonForSms = "";
        if (!$notifiable->is_admin && $this->cancellationReason && $this->actor && $this->actor->is_admin) {
            $cancellationReasonForSms = " سبب: " . Str::limit($this->cancellationReason, 20, '..');
        } elseif ($notifiable->is_admin && $this->cancellationReason) {
             $cancellationReasonForSms = " سبب: " . Str::limit($this->cancellationReason, 20, '..');
        }

        $specificReplacements = [
            '[cancelled_by_actor]' => $cancelledByShortForSms,
            '[cancellation_reason_sms]' => $cancellationReasonForSms,
        ];

        $messageContent = $this->getSmsMessageContent('booking_cancelled', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingCancelledNotification (toHttpSms): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_cancelled']);
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
            'event' => 'booking_cancelled',
            'actor_id' => $this->actor?->id,
            'actor_type' => $this->actor ? ($this->actor->is_admin ? 'admin' : ($this->booking->user && $this->actor->id === $this->booking->user_id ? 'customer' : 'system')) : 'system',
            'cancellation_reason' => $this->cancellationReason,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
