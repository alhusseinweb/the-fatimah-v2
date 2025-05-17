<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\HttpSmsChannel;
use App\Models\Booking;
use App\Models\User;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public User $recipient; // المستلم للإشعار
    public ?User $actor; // المستخدم الذي قام بالإلغاء (قد يكون المدير أو العميل أو null للنظام)
    public ?string $cancellationReason;

    /**
     * Create a new notification instance.
     *
     * @param Booking $booking الحجز الملغي
     * @param User $recipient مستلم الإشعار (العميل أو المدير)
     * @param User|null $actor المستخدم الذي قام بالإلغاء (المدير/العميل/النظام)
     * @param string|null $cancellationReason سبب الإلغاء (إذا تم توفيره)
     */
    public function __construct(Booking $booking, User $recipient, ?User $actor = null, ?string $cancellationReason = null)
    {
        $this->booking = $booking;
        $this->recipient = $recipient;
        $this->actor = $actor; // تم تغيير الاسم من cancelledBy إلى actor ليكون أوضح
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
            // يمكنك تحسين هذا الجزء لتقليل الاستعلامات إذا كنت ستستخدمه كثيرًا
            $templateExists = Cache::rememberForever('sms_template_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists(); // إضافة is_active
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("BookingCancelledNotification: SMS template not found or not active for [{$templateKey}], HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    protected function getCancelledByTextForNotifiable(object $notifiable): string
    {
        if (!$this->actor) {
            return "بواسطة النظام"; // أو "لأسباب تشغيلية"
        }
        // إذا كان مستلم الإشعار هو نفسه من قام بالإلغاء
        if ($notifiable->id === $this->actor->id) {
            return "بطلب منك";
        }
        // إذا كان من قام بالإلغاء هو مدير
        if ($this->actor->is_admin) {
            return "من قبل إدارة المصورة";
        }
        // إذا كان من قام بالإلغاء هو العميل (ولكن مستلم الإشعار هو المدير)
        if ($this->actor->id === $this->booking->user_id) {
             return "من قبل العميل (" . Str::limit($this->actor->name, 15) . ")";
        }
        // حالة أخرى (نادرة)
        return "بواسطة طرف آخر";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service?->name_ar ?: 'الخدمة المحددة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime, config('app.timezone'))->translatedFormat('l، d F Y - h:i A');
        $customerName = $this->booking->user?->name ?: 'العميل';
        $customerMobile = $this->booking->user?->mobile_number ?: 'غير متوفر';

        $cancelledByText = $this->getCancelledByTextForNotifiable($notifiable); // نص "تم الإلغاء بواسطة..."

        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) { // إشعار للمدير
            $subjectCancelledBy = $this->actor ? ($this->actor->id === $customer->id ? "العميل " . $customerName : ($this->actor->is_admin ? "الإدارة" : "النظام")) : "النظام";
            $mailMessage->subject("تنبيه: تم إلغاء الحجز رقم #{$this->booking->id} (بواسطة {$subjectCancelledBy})")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم إلغاء الحجز رقم #{$this->booking->id} الخاص بالعميل: {$customerName} (جوال: {$customerMobile}).")
                        ->line("الخدمة الملغاة: {$serviceName}")
                        ->line("موعد الحجز الملغى: {$bookingDateTime}")
                        ->line("تم الإلغاء بواسطة: " . $cancelledByText . "."); // استخدام النص المخصص
            // عرض السبب دائمًا للمدير إذا كان موجودًا
            if ($this->cancellationReason) {
                $mailMessage->line("سبب الإلغاء المذكور: " . $this->cancellationReason);
            }
            $mailMessage->action('مراجعة الحجز الملغي', route('admin.bookings.show', $this->booking->id));
        } else { // إشعار للعميل
            $mailMessage->subject("إشعار بإلغاء حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نأسف لإعلامك بأنه تم إلغاء حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الموعد الذي تم إلغاؤه: {$bookingDateTime}.")
                        ->line("تم الإلغاء: " . $cancelledByText . ".");
            // عرض السبب للعميل فقط إذا كان الإلغاء من الإدارة وكان هناك سبب
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
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) { $recipientPhoneNumber = $notifiable->mobile_number; }

        // تنسيق رقم الهاتف (الكود من ملفك الأصلي)
        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('BookingCancelledNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return []; // لا يمكن إرسال SMS بدون رقم
        }

        $templateKey = $notifiable->is_admin ? 'booking_cancelled_admin' : 'booking_cancelled_customer';
        // جلب القالب النشط
        $template = Cache::remember('sms_template_' . $templateKey, now()->addHours(6), function () use ($templateKey) {
            return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->first();
        });

        if (!$template || !$template->template_content) {
            Log::error("BookingCancelledNotification: SMS template not found or empty for [{$templateKey}].", ['booking_id' => $this->booking->id]);
            // يمكنك إما عدم إرسال شيء، أو استخدام نص افتراضي أكثر تفصيلاً إذا أردت
            return [];
        }
        $templateContent = $template->template_content;

        $cancelledByShortForSms = "";
        if ($this->actor) {
            if ($notifiable->id === $this->actor->id) { // إذا ألغى الشخص بنفسه
                $cancelledByShortForSms = ""; // لا داعي لذكر "بواسطتك" في رسالة قصيرة
            } elseif ($this->actor->is_admin) {
                $cancelledByShortForSms = " (الادارة)";
            } else { // العميل ألغى، والإشعار للمدير
                $cancelledByShortForSms = " (العميل)";
            }
        } else {
             $cancelledByShortForSms = " (النظام)";
        }

        // تضمين سبب الإلغاء بشكل مختصر إذا كان من الإدارة وموجهًا للعميل
        $cancellationReasonForSms = "";
        if (!$notifiable->is_admin && $this->cancellationReason && $this->actor && $this->actor->is_admin) {
            $cancellationReasonForSms = " سبب: " . Str::limit($this->cancellationReason, 20, '..'); // حد أقصى لعدد أحرف السبب
        }


        $replacements = [
            '[customer_name]' => $this->booking->user?->name ?? 'العميل',
            '[customer_name_short]' => $this->booking->user ? Str::limit($this->booking->user->name, 10) : "عميل",
            '[booking_id]' => $this->booking->id,
            '[service_name_short]' => Str::limit($this->booking->service?->name_ar ?? 'الخدمة', 15),
            '[cancelled_by_actor]' => $cancelledByShortForSms, // متغير جديد للقالب
            '[cancellation_reason_sms]' => $cancellationReasonForSms, // متغير جديد للقالب
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
            // أزل المتغيرات القديمة إذا لم تعد مستخدمة في قوالبك
            // '[cancelled_by_text]' => ...,
            // '[cancelled_by_short]' => ...,
            // '[cancellation_reason]' => ...,
            // '[cancellation_reason_short]' => ...,
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);
        // قم بإزالة أي متغيرات لم يتم استبدالها (مثل [cancellation_reason_sms] إذا لم يكن هناك سبب)
        $messageContent = preg_replace('/\[[^\]]+\]/', '', $messageContent); // يزيل أي شيء بين []
        $messageContent = trim(preg_replace('/\s+/', ' ', $messageContent)); // يزيل المسافات الزائدة

        Log::debug('BookingCancelledNotification: Final SMS content.', [
            'template_key' => $templateKey, 'to' => $recipientPhoneNumber, 'final_content' => $messageContent,
        ]);

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
            'actor_id' => $this->actor?->id, // المستخدم الذي قام بالإلغاء
            'actor_type' => $this->actor ? ($this->actor->is_admin ? 'admin' : 'customer') : 'system',
            'cancellation_reason' => $this->cancellationReason,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
