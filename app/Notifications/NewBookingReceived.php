<?php

namespace App\Notifications;

use App\Models\Booking;
// use App\Models\User; // $notifiable هو المستلم
use App\Notifications\Channels\HttpSmsChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // لإستخدام via
use App\Models\SmsTemplate;      // لإستخدام via
use Illuminate\Support\Str;


class NewBookingReceived extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    protected Booking $booking;
    protected string $paymentMethod;

    public function __construct(Booking $booking, string $paymentMethod)
    {
        $this->booking = $booking;
        $this->paymentMethod = $paymentMethod;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        // هذا الإشعار كان يرسل SMS للعميل فقط في نسختك الأصلية.
        // إذا أردت إرسال بريد إلكتروني أيضًا للعميل، أضف:
        // if (!$notifiable->is_admin && $notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
        //     $channels[] = 'mail';
        // }

        if (!$notifiable->is_admin && $notifiable->mobile_number) { // يرسل للعميل فقط
            $templateKey = 'booking_request_customer'; // أو أي مفتاح آخر تستخدمه لهذا القصد
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("NewBookingReceived: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
    }

    // إذا أضفت 'mail' في via() للعميل، يجب إضافة دالة toMail() هنا.

    public function toHttpSms(object $notifiable): array
    {
        if ($notifiable->is_admin) {
            return []; // تأكيد إضافي أنه لا يرسل للمدير
        }
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('NewBookingReceived (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $paymentMethodText = $this->paymentMethod === 'bank_transfer' ? 'بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : Str::title($this->paymentMethod));
        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
            '[payment_details_prompt]' => $this->paymentMethod === 'bank_transfer' ? " يرجى تحويل العربون للتأكيد." : "",
            // يمكن إضافة تاريخ ووقت الحجز إذا كان القالب يتطلبه
            // '[booking_date_time]' => Carbon::parse($this->booking->booking_datetime)->translatedFormat('l, d M Y - h:i A'),
        ];

        // استخدم نفس مفتاح القالب الذي استخدمته في via()
        $messageContent = $this->getSmsMessageContent('booking_request_customer', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('NewBookingReceived (toHttpSms): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_request_customer']);
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
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer', // سيكون customer هنا
            'event' => 'new_booking_received_sms', // اسم مميز
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
