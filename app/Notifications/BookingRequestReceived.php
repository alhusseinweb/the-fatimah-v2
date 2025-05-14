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
use Illuminate\Support\HtmlString;

class BookingRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public User $recipient;
    public string $paymentMethod;

    public function __construct(Booking $booking, User $recipient, string $paymentMethod)
    {
        $this->booking = $booking;
        $this->recipient = $recipient;
        $this->paymentMethod = $paymentMethod;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'booking_request_admin' : 'booking_request_customer';
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

    // ... (دالة toMail كما هي) ...
    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customer = $this->booking->user;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: طلب حجز جديد رقم #{$this->booking->id} من {$customer->name}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم استلام طلب حجز جديد من العميل: {$customer->name} (جوال: {$customer->mobile_number}).")
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


    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) { $recipientPhoneNumber = $notifiable->mobile_number; }
        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('BookingRequestReceived (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = $notifiable->is_admin ? 'booking_request_admin' : 'booking_request_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['booking_id' => $this->booking->id]);
            if ($notifiable->is_admin) {
                $templateContent = "طلب حجز جديد! عميل:[customer_name_short]. خدمة:[service_name_short]. رقم:[booking_id]. دفع:[payment_method].";
            } else {
                $templateContent = "طلب حجزك للمصورة فاطمة. خدمة: [service_name_short]. طلب رقم: [booking_id].";
                 if ($this->paymentMethod === 'bank_transfer') { $templateContent .= " يرجى تحويل العربون للتأكيد."; }
            }
        }

        $parsedBookingDateTime = Carbon::parse($this->booking->booking_datetime);
        $paymentMethodText = $this->paymentMethod === 'bank_transfer' ? 'بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : $this->paymentMethod);

        $replacements = [
            '[customer_name]' => $this->booking->user->name ?? 'العميل',
            '[customer_name_short]' => $this->booking->user ? Str::limit($this->booking->user->name, 10) : "عميل",
            '[customer_mobile]' => $this->booking->user->mobile_number ?? '',
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : 'تصوير',
            '[service_name_short]' => $this->booking->service ? Str::limit($this->booking->service->name_ar, 12, '') : "تصوير",
            '[booking_id]' => $this->booking->id,
            '[booking_date_time]' => $parsedBookingDateTime->translatedFormat('l, d M Y - h:i A'),
            '[payment_method]' => $paymentMethodText,
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('BookingRequestReceived: Final SMS content from DB template.', [
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
            'event' => 'booking_request_received',
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}