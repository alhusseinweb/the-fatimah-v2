<?php

namespace App\Notifications;

// ... (use statements كما هي أو مع إضافة اللازم) ...
use App\Models\Booking;
// use App\Models\User;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
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


class BookingStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    public string $oldStatus;
    public string $newStatus;

    public function __construct(Booking $booking, string $oldStatus, string $newStatus)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function via(object $notifiable): array
    {
        if ($this->newStatus === $this->oldStatus) {
            Log::info("BookingStatusChangedNotification: Old and new status are the same ({$this->newStatus}), skipping notification for booking ID {$this->booking->id}.");
            return [];
        }

        // الحالات التي لها إشعارات مخصصة بها ولا يجب أن يرسل هذا الإشعار لها
        $excludedStatusesForThisNotification = [
            Booking::STATUS_CONFIRMED_PAID,     // يستخدم BookingConfirmedNotification
            Booking::STATUS_CONFIRMED_DEPOSIT,  // يستخدم BookingConfirmedNotification
            Booking::STATUS_CANCELLED_BY_ADMIN, // يستخدم BookingCancelledNotification
            Booking::STATUS_CANCELLED_BY_USER,  // يستخدم BookingCancelledNotification
            Booking::STATUS_UNDER_REVIEW,      // حالة أولية
        ];
        if (in_array($this->newStatus, $excludedStatusesForThisNotification)) {
            Log::info("BookingStatusChangedNotification: New status '{$this->newStatus}' has a dedicated notification, skipping this generic status change notification for booking ID {$this->booking->id}.");
            return [];
        }

        $channels = [];
        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("BookingStatusChangedNotification: Email not sent to notifiable ID {$notifiable->id}, email missing or invalid.", ['booking_id' => $this->booking->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_status_changed_admin' : 'booking_status_changed_customer';
            $smsChannels = $this->determineSmsChannels($templateKey, $notifiable);
            $channels = array_merge($channels, $smsChannels);

            if (empty($smsChannels)) {
                Log::warning("BookingStatusChangedNotification: No SMS/WhatsApp channels determined for notifiable ID {$notifiable->id}.");
            }
        }
        
        if(empty($channels)){
            Log::error("BookingStatusChangedNotification: No channels determined for notifiable ID {$notifiable->id}.", ['booking_id' => $this->booking->id, 'is_admin' => $notifiable->is_admin ?? 'N/A']);
        }
        return $channels;
    }

    protected function translateStatus(string $statusKey): string
    {
        // إذا كان لديك accessor في موديل Booking لـ status_label، يمكنك استخدامه
        if (isset($this->booking->status_label) && $this->booking->getRawOriginal('status') === $statusKey) {
             // هذا قد لا يعمل دائماً إذا كان status_label يعتمد على الحالة الحالية للموديل
             // من الأفضل استخدام مصفوفة ترجمة أو دالة static
        }
        $statusTranslations = Booking::getStatusesWithOptions(); // استخدام الدالة من الموديل مباشرة
        return $statusTranslations[$statusKey] ?? Str::title(str_replace('_', ' ', $statusKey));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newStatusText = $this->translateStatus($this->newStatus);
        $oldStatusText = $this->translateStatus($this->oldStatus);
        $customerUser = $this->booking->user;
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - H:i');
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تحديث حالة الحجز رقم #{$this->booking->id} إلى: {$newStatusText}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم تحديث حالة الحجز رقم #{$this->booking->id} للعميل {$customerUser->name}.")
                        ->line("الحالة السابقة: {$oldStatusText}")
                        ->line("الحالة الجديدة: **{$newStatusText}**")
                        ->line("تفاصيل الحجز:")
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد: {$bookingDateTime}")
                        ->action('عرض الحجز', route('admin.bookings.show', $this->booking->id));
        } else { // للعميل
            $mailMessage->subject("تحديث على حالة حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("تم تحديث حالة حجزك رقم #{$this->booking->id} المتعلق بخدمة '{$serviceName}'.")
                        ->line("الحالة الجديدة لحجزك هي: **{$newStatusText}**.")
                        ->line("موعد الحجز: {$bookingDateTime}.")
                        ->line("للمزيد من التفاصيل أو في حال وجود أي استفسارات، يرجى مراجعة حسابك أو التواصل معنا.")
                        ->action('عرض تفاصيل حجزي', route('customer.bookings.index')); // أو customer.bookings.show إذا كان لديك
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingStatusChangedNotification (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        // --- START: التعديل الهام هنا ---
        $templateIdentifier = $notifiable->is_admin ? 'booking_status_changed_admin' : 'booking_status_changed_customer';
        // --- END: التعديل الهام هنا ---

        $newStatusTranslated = $this->translateStatus($this->newStatus);
        $oldStatusTranslated = $this->translateStatus($this->oldStatus);
        $specificReplacements = [
            '[new_status_translated]' => Str::limit($newStatusTranslated, 25, '..'),
            '[old_status_translated]' => Str::limit($oldStatusTranslated, 25, '..'),
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
             Log::warning('BookingStatusChangedNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
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

    public function toWhatsApp(object $notifiable): array
    {
        $smsData = $this->toHttpSms($notifiable);
        if (empty($smsData)) return [];

        return [
            'to' => $this->formatWhatsAppRecipient($notifiable->mobile_number),
            'content' => $smsData['content'],
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
