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

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public User $recipient;
    public ?User $cancelledBy;
    public ?string $cancellationReason;

    public function __construct(Booking $booking, User $recipient, ?User $cancelledBy = null, ?string $cancellationReason = null)
    {
        $this->booking = $booking;
        $this->recipient = $recipient;
        $this->cancelledBy = $cancelledBy;
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

    // ... (getCancelledByTextForNotifiable و toMail كما هما) ...
    protected function getCancelledByTextForNotifiable(object $notifiable): string
    {
        if (!$this->cancelledBy) {
            return "بواسطة النظام";
        }
        if ($notifiable->id === $this->cancelledBy->id) {
            return "بطلب منك";
        }
        if ($this->cancelledBy->is_admin) {
            return "من قبل إدارة المصورة";
        } else {
            return "من قبل العميل " . $this->cancelledBy->name;
        }
    }

    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - H:i');
        $customer = $this->booking->user;
        $cancelledByText = $this->getCancelledByTextForNotifiable($notifiable);
        $cancelledByForAdminSubject = $this->cancelledBy ? ($this->cancelledBy->is_admin ? "الإدارة" : "العميل " . $this->cancelledBy->name) : "النظام";

        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: تم إلغاء الحجز رقم #{$this->booking->id} (بواسطة {$cancelledByForAdminSubject})")
                        ->greeting("مرحبا ايها المدير،")
                        ->line("تم إلغاء الحجز رقم #{$this->booking->id} الخاص بالعميل: {$customer->name} (جوال: {$customer->mobile_number}).")
                        ->line("الخدمة الملغاة: {$serviceName}")
                        ->line("موعد الحجز الملغى: {$bookingDateTime}")
                        ->line("تم الإلغاء بواسطة: " . $cancelledByForAdminSubject . ".")
                        ->lineIf($this->cancellationReason, "سبب الإلغاء المذكور: {$this->cancellationReason}")
                        ->action('مراجعة الحجز الملغي', route('admin.bookings.show', $this->booking->id));
        } else { // للعميل
            $mailMessage->subject("إشعار بإلغاء حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نأسف لإعلامك بأنه تم إلغاء حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الموعد الذي تم إلغاؤه: {$bookingDateTime}.")
                        ->line("تم الإلغاء: " . $cancelledByText . ".")
                        ->lineIf($this->cancellationReason && $this->cancelledBy && $this->cancelledBy->is_admin && $notifiable->id !== $this->cancelledBy->id, "سبب الإلغاء من الإدارة: {$this->cancellationReason}")
                        ->line("إذا كان لديك أي استفسارات أو كنت ترغب في إعادة الحجز، يرجى التواصل معنا أو زيارة الموقع.")
                        ->action('تصفح الخدمات المتاحة', route('services.index'));
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
            Log::warning('BookingCancelledNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = $notifiable->is_admin ? 'booking_cancelled_admin' : 'booking_cancelled_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['booking_id' => $this->booking->id]);
            // نص افتراضي إذا لم يوجد قالب
            if ($notifiable->is_admin) {
                $templateContent = "الغاء حجز [booking_id]! عميل:[customer_name_short]. بواسطة:[cancelled_by_short].";
            } else {
                $templateContent = "تم الغاء حجزك رقم [booking_id].[cancelled_by_text]";
            }
        }

        $cancelledByTextForSms = "";
        if (!$notifiable->is_admin && (!$this->cancelledBy || $this->cancelledBy->id !== $notifiable->id)) {
            $cancelledByShort = $this->cancelledBy ? ($this->cancelledBy->is_admin ? "الادارة" : "العميل") : "النظام";
            $cancelledByTextForSms = " بواسطة " . $cancelledByShort . ".";
        }
        // إضافة سبب الإلغاء إذا كان من الإدارة وكان للعميل والطول يسمح
        $cancellationReasonShortForSms = "";
        if (!$notifiable->is_admin && $this->cancellationReason && $this->cancelledBy && $this->cancelledBy->is_admin && (!$this->cancelledBy || $this->cancelledBy->id !== $notifiable->id)) {
            $reasonText = " سبب:" . Str::limit($this->cancellationReason, 15, '..');
            // التحقق من الطول الكلي قبل الإضافة (مثال بسيط)
            // if(mb_strlen($templateContent . $cancelledByTextForSms . $reasonText, 'UTF-8') < 130) {
                $cancellationReasonShortForSms = $reasonText;
            // }
        }


        $replacements = [
            '[customer_name]' => $this->booking->user->name ?? 'العميل',
            '[customer_name_short]' => $this->booking->user ? Str::limit($this->booking->user->name, 10) : "عميل",
            '[customer_mobile]' => $this->booking->user->mobile_number ?? '',
            '[booking_id]' => $this->booking->id,
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : 'الخدمة',
            '[cancelled_by_text]' => $cancelledByTextForSms, // للمتغير الذي تم تحديده في القالب
            '[cancelled_by_short]' => $this->cancelledBy ? ($this->cancelledBy->is_admin ? "الادارة" : "العميل") : "النظام",
            '[cancellation_reason]' => $this->cancellationReason ?? '',
            '[cancellation_reason_short]' => $cancellationReasonShortForSms,
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('BookingCancelledNotification: Final SMS content from DB template.', [
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
        // ... (الكود الأصلي لدالة toArray)
        return [
            'booking_id' => $this->booking->id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'event' => 'booking_cancelled',
            'cancelled_by_id' => $this->cancelledBy ? $this->cancelledBy->id : null,
            'cancelled_by_type' => $this->cancelledBy ? ($this->cancelledBy->is_admin ? 'admin' : ($this->cancelledBy->id === $this->booking->user_id ? 'customer' : 'other_user')) : 'system',
            'cancellation_reason' => $this->cancellationReason,
            'channels_used' => $this->via($notifiable),
        ];
    }
}