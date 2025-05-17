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

class BookingStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public string $oldStatus;
    public string $newStatus;
    public User $recipient;

    public function __construct(Booking $booking, string $oldStatus, string $newStatus, User $recipient)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->recipient = $recipient;
    }

    public function via(object $notifiable): array
    {
        if ($this->newStatus === $this->oldStatus) { return []; }
        // استثناء بعض الحالات التي لها إشعارات خاصة
        $excludedStatuses = [
            Booking::STATUS_CONFIRMED, // له BookingConfirmedNotification
            Booking::STATUS_CANCELLED_BY_ADMIN, // له BookingCancelledNotification
            Booking::STATUS_CANCELLED_BY_USER,  // له BookingCancelledNotification
            // يمكنك إضافة حالات أخرى هنا إذا كان لها إشعار خاص بها
        ];

        if (in_array($this->newStatus, $excludedStatuses)) {
            // إذا كانت الحالة الجديدة هي إلغاء، دع BookingCancelledNotification يتعامل معها
            // أو أي حالة أخرى مستثناة لها معالجة خاصة
            // بالنسبة لـ STATUS_CONFIRMED، سيعيد هذا الشرط فارغًا أيضًا إذا لم تكن هناك معالجة إضافية محددة هنا
            return [];
        }

        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'booking_status_changed_admin' : 'booking_status_changed_customer';
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

    // ... (translateStatus و toMail كما هما) ...
    protected function translateStatus(string $statusKey): string
    {
        // التأكد من وجود الدالة getStatusLabel في موديل Booking
        if (method_exists($this->booking, 'getStatusLabelAttribute')) {
            // محاولة استخدام المترجم من الموديل مباشرة إذا كان متاحاً
            // تحتاج لتعديل موديل Booking ليكون $this->booking->status = $statusKey;
            // ثم $this->booking->status_label;
            // هذا مثال، قد تحتاج لتمرير الحالة مباشرة للدالة في الموديل
        }

        // إذا لم تكن getStatusLabel موجودة أو لم تعمل كما هو متوقع، استخدم الترجمة المحلية
        $statusTranslations = [
            Booking::STATUS_PENDING => 'قيد الانتظار',
            Booking::STATUS_CONFIRMED => 'مؤكد',
            Booking::STATUS_CANCELLED_BY_USER => 'ملغي من قبل العميل',
            Booking::STATUS_CANCELLED_BY_ADMIN => 'ملغي من قبل الإدارة',
            Booking::STATUS_COMPLETED => 'مكتمل',
            Booking::STATUS_RESCHEDULED_BY_ADMIN => 'تمت إعادة جدولته من الإدارة',
            Booking::STATUS_RESCHEDULED_BY_USER => 'طلب إعادة جدولة من العميل',
            Booking::STATUS_NO_SHOW => 'لم يحضر العميل',
            'pending_payment' => 'بانتظار الدفع', // إذا كانت هذه حالة مستخدمة
            'pending_confirmation' => 'بانتظار تأكيد التحويل', // إذا كانت هذه حالة مستخدمة
        ];
        return $statusTranslations[$statusKey] ?? Str::title(str_replace('_', ' ', $statusKey));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newStatusText = $this->translateStatus($this->newStatus);
        $oldStatusText = $this->translateStatus($this->oldStatus);
        $customer = $this->booking->user;
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - H:i');
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تحديث حالة الحجز رقم #{$this->booking->id} إلى: {$newStatusText}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم تحديث حالة الحجز رقم #{$this->booking->id} للعميل {$customer->name}.")
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
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) { $recipientPhoneNumber = $notifiable->mobile_number; }
        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('BookingStatusChangedNotification (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = $notifiable->is_admin ? 'booking_status_changed_admin' : 'booking_status_changed_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        $newStatusTranslated = $this->translateStatus($this->newStatus);
        $oldStatusTranslated = $this->translateStatus($this->oldStatus);

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['booking_id' => $this->booking->id]);
            if ($notifiable->is_admin) {
                $templateContent = "تحديث حجز [booking_id]. عميل:[customer_name_short]. حالة:[new_status_translated].";
            } else {
                $templateContent = "تحديث حجزك رقم [booking_id]. الحالة الجديدة: [new_status_translated].";
            }
        }

        $replacements = [
            '[customer_name]' => $this->booking->user->name ?? 'العميل',
            '[customer_name_short]' => $this->booking->user ? Str::limit($this->booking->user->name, 10) : "عميل",
            '[customer_mobile]' => $this->booking->user->mobile_number ?? '',
            '[booking_id]' => $this->booking->id,
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : 'الخدمة',
            '[new_status_translated]' => Str::limit($newStatusTranslated, 25, '..'),
            '[old_status_translated]' => Str::limit($oldStatusTranslated, 25, '..'),
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('BookingStatusChangedNotification: Final SMS content from DB template.', [
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
            'event' => 'booking_status_changed',
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
