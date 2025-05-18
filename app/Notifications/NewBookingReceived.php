<?php

namespace App\Notifications;

use App\Models\Booking;
// use App\Models\User; // $notifiable is the user
use App\Notifications\Channels\AndroidSmsGatewayChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent; // <-- تم الإضافة
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewBookingReceived extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent; // <-- تم الإضافة

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
        // لا يوجد إرسال بريد إلكتروني محدد هنا في الكود الأصلي.
        // إذا كنت تريد إرسال بريد، أضف:
        // if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
        //     $channels[] = 'mail';
        // }

        // هذا الإشعار عادة للعميل الذي قام بالحجز
        if (!$notifiable->is_admin && $notifiable->mobile_number && config('services.sms_gateway.enabled', env('SMS_GATEWAY_ENABLED', false))) {
            $channels[] = AndroidSmsGatewayChannel::class; // <-- تم التغيير
        }
        return $channels;
    }

    // إذا أضفت 'mail' في via()، يجب إضافة دالة toMail() هنا

    public function toSmsGateway(object $notifiable): array
    {
        if ($notifiable->is_admin) { // تأكد أنه لا يرسل للمدير إذا كان هذا هو القصد
            return [];
        }
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('NewBookingReceived (toSmsGateway): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $paymentMethodText = $this->paymentMethod === 'bank_transfer' ? 'بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : $this->paymentMethod);
        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
            '[payment_details_prompt]' => $this->paymentMethod === 'bank_transfer' ? " يرجى تحويل العربون للتأكيد." : "",
        ];
        // كان templateKey المستخدم سابقًا هو 'booking_request_customer'
        $messageContent = $this->getSmsMessageContent('booking_request_customer', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('NewBookingReceived (toSmsGateway): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_request_customer']);
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
            'event' => 'new_booking_received_sms',
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
