<?php

namespace App\Notifications;

// ... (use statements كما هي أو مع إضافة اللازم) ...
use App\Models\Booking;
use App\Models\User;
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


class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    public ?User $actor; // المستخدم الذي قام بالإلغاء (قد يكون null إذا كان النظام)
    public ?string $cancellationReason;

    public function __construct(Booking $booking, ?User $actor = null, ?string $cancellationReason = null)
    {
        $this->booking = $booking;
        $this->actor = $actor;
        $this->cancellationReason = $cancellationReason;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("BookingCancelledNotification: Email not sent to notifiable ID {$notifiable->id}, email missing or invalid.", ['booking_id' => $this->booking->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_cancelled_admin' : 'booking_cancelled_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("BookingCancelledNotification: SMS template '{$templateKey}' for notifiable ID {$notifiable->id} not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        } else {
            Log::warning("BookingCancelledNotification: SMS not sent to notifiable ID {$notifiable->id}, mobile_number missing.", ['booking_id' => $this->booking->id]);
        }
        
        if(empty($channels)){
            Log::error("BookingCancelledNotification: No channels determined for notifiable ID {$notifiable->id}.", ['booking_id' => $this->booking->id, 'is_admin' => $notifiable->is_admin ?? 'N/A']);
        }
        return $channels;
    }

    protected function getCancelledByTextForNotifiable(object $notifiable): string
    {
        if (!$this->actor) return "بواسطة النظام";
        // إذا كان المستلم هو نفسه الفاعل (مثلاً، العميل ألغى حجزه بنفسه)
        if ($notifiable->id === $this->actor->id && get_class($notifiable) === get_class($this->actor)) {
             return "بطلب منك";
        }
        if ($this->actor->is_admin) return "من قبل الإدارة";
        // إذا كان الفاعل هو العميل صاحب الحجز (ولكن المستلم هو المدير)
        if ($this->booking->user && $this->actor->id === $this->booking->user->id) return "من قبل العميل (" . Str::limit($this->actor->name, 15) . ")";
        return "بواسطة طرف آخر"; // حالة غير متوقعة
    }

    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service?->name_ar ?: 'الخدمة المحددة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime, config('app.timezone'))->translatedFormat('l، d F Y - h:i A');
        $customerName = $this->booking->user?->name ?: 'العميل';
        $customerMobile = $this->booking->user?->mobile_number ?: 'غير متوفر';
        $cancelledByText = $this->getCancelledByTextForNotifiable($notifiable);
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $subjectCancelledBy = $this->actor ? ($this->booking->user && $this->actor->id === $this->booking->user->id ? "العميل " . $customerName : ($this->actor->is_admin ? "الإدارة" : "النظام")) : "النظام";
            $mailMessage->subject("تنبيه إداري: تم إلغاء الحجز رقم #{$this->booking->id} (بواسطة {$subjectCancelledBy})")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم إلغاء الحجز رقم #{$this->booking->id} الخاص بالعميل: {$customerName} (جوال: {$customerMobile}).")
                        ->line("الخدمة الملغاة: {$serviceName}")
                        ->line("موعد الحجز الملغى: {$bookingDateTime}")
                        ->line("تم الإلغاء بواسطة: " . $cancelledByText . ".");
            if ($this->cancellationReason) {
                $mailMessage->line("سبب الإلغاء المذكور: " . $this->cancellationReason);
            }
            $mailMessage->action('مراجعة الحجز الملغي', route('admin.bookings.show', $this->booking->id));
        } else { // للعميل
            $mailMessage->subject("إشعار بإلغاء حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نأسف لإعلامك بأنه تم إلغاء حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الموعد الذي تم إلغاؤه: {$bookingDateTime}.")
                        ->line("تم الإلغاء: " . $cancelledByText . ".");
            // عرض سبب الإلغاء للعميل فقط إذا كان الإلغاء من قبل المدير وكان هناك سبب
            if ($this->cancellationReason && $this->actor && $this->actor->is_admin) {
                $mailMessage->line("السبب الموضح من الإدارة: " . $this->cancellationReason);
            }
            $mailMessage->line("إذا كان لديك أي استفسارات أو كنت ترغب في إعادة الحجز، يرجى التواصل معنا أو زيارة الموقع.")
                        ->action('تصفح الخدمات المتاحة', route('services.index'));
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingCancelledNotification (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        // --- START: التعديل الهام هنا ---
        $templateIdentifier = $notifiable->is_admin ? 'booking_cancelled_admin' : 'booking_cancelled_customer';
        // --- END: التعديل الهام هنا ---

        $cancelledByShortForSms = "";
        if ($this->actor) {
            if ($notifiable->id !== $this->actor->id || get_class($notifiable) !== get_class($this->actor)) { // لا تعرض "بواسطتك" إذا كان هو الفاعل
                 $cancelledByShortForSms = $this->actor->is_admin ? " (الادارة)" : ($this->booking->user && $this->actor->id === $this->booking->user->id ? " (العميل)" : "");
            }
        }

        $cancellationReasonForSms = "";
        // اعرض سبب الإلغاء للعميل إذا كان الإلغاء من المدير
        if (!$notifiable->is_admin && $this->cancellationReason && $this->actor && $this->actor->is_admin) {
            $cancellationReasonForSms = " سبب: " . Str::limit($this->cancellationReason, 20, '..');
        } elseif ($notifiable->is_admin && $this->cancellationReason) { // اعرض السبب للمدير دائمًا إذا وجد
             $cancellationReasonForSms = " سبب: " . Str::limit($this->cancellationReason, 20, '..');
        }


        $specificReplacements = [
            '[cancelled_by_actor]' => $cancelledByShortForSms,
            '[cancellation_reason_sms]' => $cancellationReasonForSms,
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingCancelledNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
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
            'event' => 'booking_cancelled',
            'actor_id' => $this->actor?->id,
            'actor_type' => $this->actor ? ($this->actor->is_admin ? 'admin' : ($this->booking->user && $this->actor->id === $this->booking->user_id ? 'customer' : 'system')) : 'system',
            'cancellation_reason' => $this->cancellationReason,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
