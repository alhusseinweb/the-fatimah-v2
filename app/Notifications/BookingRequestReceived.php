<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Channels\AndroidSmsGatewayChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent; // <-- تم الإضافة
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class BookingRequestReceived extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent; // <-- تم الإضافة

    public Booking $booking;
    // public User $recipient; // $notifiable هو الـ recipient
    public string $paymentMethod;

    public function __construct(Booking $booking, /* User $recipient, */ string $paymentMethod)
    {
        $this->booking = $booking;
        // $this->recipient = $recipient;
        $this->paymentMethod = $paymentMethod;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number && config('services.sms_gateway.enabled', env('SMS_GATEWAY_ENABLED', false))) {
            $channels[] = AndroidSmsGatewayChannel::class; // <-- تم التغيير
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (الكود الأصلي كما هو) ...
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: طلب حجز جديد رقم #{$this->booking->id} من {$customerUser->name}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم استلام طلب حجز جديد من العميل: {$customerUser->name} (جوال: {$customerUser->mobile_number}).")
                        ->line(new HtmlString("<strong>تفاصيل الطلب:</strong>"))
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد المطلوب: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->line("- طريقة الدفع المختارة: " . ($this->paymentMethod === 'bank_transfer' ? 'تحويل بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : $this->paymentMethod)))
                        ->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->lineIf($this->booking->customer_notes, "- ملاحظات العميل: {$this->booking->customer_notes}")
                        ->action('مراجعة الطلب في لوحة التحكم', route('admin.bookings.show', $this->booking->id));
        } else {
            $bookingUrl = route('booking.pending', $this->booking->id);
            $mailMessage->subject("تم استلام طلب حجزك رقم #{$this->booking->id}")
                        ->greeting("مرحباً {$notifiable->name}،")
                        ->line("شكراً لك! لقد استلمنا طلب حجزك بنجاح لدى المصورة فاطمة علي.")
                        ->line("طلبك الآن قيد المراجعة أو بانتظار إتمام عملية الدفع حسب الطريقة المختارة.")
                        ->line(new HtmlString("<strong>تفاصيل طلبك المبدئي:</strong>"))
                        ->line(new HtmlString("- رقم الطلب: <strong>{$this->booking->id}</strong>"))
                        ->line(new HtmlString("- الخدمة: <strong>{$serviceName}</strong>"))
                        ->line(new HtmlString("- التاريخ والوقت: <strong>{$bookingDateTime}</strong>"))
                        ->line("يمكنك متابعة حالة طلبك وإتمام عملية الدفع (إذا لزم الأمر) من خلال الرابط التالي:")
                        ->action('متابعة حالة الطلب', $bookingUrl)
                        ->line("سنقوم بإعلامك فور تأكيد الحجز بشكل نهائي أو في حال وجود أي تحديثات.");
        }
        return $mailMessage->salutation('مع تحياتنا، فريق ' . config('app.name'));
    }

    public function toSmsGateway(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('BookingRequestReceived (toSmsGateway): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $paymentMethodText = $this->paymentMethod === 'bank_transfer' ? 'بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : $this->paymentMethod);
        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
             '[payment_details_prompt]' => $this->paymentMethod === 'bank_transfer' ? " يرجى تحويل العربون للتأكيد." : "",
        ];

        $messageContent = $this->getSmsMessageContent('booking_request', $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning('BookingRequestReceived (toSmsGateway): SMS message content is empty after processing template.', ['booking_id' => $this->booking->id, 'template_identifier' => 'booking_request']);
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
            'event' => 'booking_request_received',
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
