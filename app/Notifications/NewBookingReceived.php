<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Traits\ManagesSmsContent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsTemplate;
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
        // هذا الإشعار يرسل SMS للعميل فقط
        if (!$notifiable->is_admin && isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = 'booking_request_customer'; // المفتاح المحدد لهذا الإشعار للعميل
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("NewBookingReceived: SMS template '{$templateKey}' not found or not active for customer, HttpSmsChannel skipped.", ['booking_id' => $this->booking->id, 'notifiable_id' => $notifiable->id]);
            }
        }
        return $channels;
    }

    public function toHttpSms(object $notifiable): array
    {
        // هذا الإشعار مخصص للعميل فقط بناءً على منطق via
        if ($notifiable->is_admin) {
            return []; 
        }

        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('NewBookingReceived (toHttpSms): Recipient mobile number could not be determined for customer.', ['booking_id' => $this->booking->id, 'notifiable_id' => $notifiable->id]);
            return [];
        }

        $paymentMethodText = match ($this->paymentMethod) {
            'bank_transfer' => 'تحويل بنكي',
            'tamara' => 'تمارا',
            default => Str::title(str_replace('_', ' ', $this->paymentMethod)),
        };
        
        $bookingDateFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('Y/m/d');
        $bookingTimeFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('h:ia');

        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
            '[payment_details_prompt]' => $this->paymentMethod === 'bank_transfer' ? " يرجى تحويل العربون للتأكيد." : "",
            '[booking_date]' => $bookingDateFormatted,
            '[booking_time]' => $bookingTimeFormatted,
            '[booking_date_time]' => $bookingDateFormatted . ' ' . $bookingTimeFormatted,
        ];

        $messageContent = $this->getSmsMessageContent('booking_request_customer', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('NewBookingReceived (toHttpSms): SMS message content is empty after processing template for customer.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_request_customer', 'notifiable_id' => $notifiable->id]);
            // يمكنك وضع رسالة افتراضية هنا إذا فشل القالب
            // $messageContent = "طلب حجزك رقم {$this->booking->id} قيد المعالجة. طريقة الدفع: {$paymentMethodText}." . ($this->paymentMethod === 'bank_transfer' ? " يرجى تحويل العربون." : "");
            // if (empty($messageContent)) return []; // تأكد من عدم إرسال رسالة فارغة تمامًا
        }
        
        if (empty($messageContent)) { // تحقق أخير قبل الإرسال
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
            'recipient_type' => 'customer', // هذا الإشعار للعميل
            'event' => 'new_booking_received_sms_to_customer',
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
