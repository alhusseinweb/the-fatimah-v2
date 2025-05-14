<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\HttpSmsChannel;
use App\Models\Booking;
use App\Models\User; // إضافة
use App\Models\SmsTemplate; // <-- استيراد
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- استيراد
use Illuminate\Support\Str;
use Carbon\Carbon; // إضافة

class NewBookingReceived extends Notification implements ShouldQueue
{
    use Queueable;
    protected Booking $booking;
    protected string $paymentMethod;
    // لا نحتاج لـ recipient هنا لأن هذا الإشعار غالباً ما يرسل للعميل مباشرة
    // أو إذا كان يرسل للمدير، فسيتم تمرير المدير كـ notifiable

    public function __construct(Booking $booking, string $paymentMethod)
    {
        $this->booking = $booking;
        $this->paymentMethod = $paymentMethod;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->mobile_number) {
            // هذا الإشعار يرسل للعميل عند استلام طلبه.
            // إذا كان يرسل أيضاً للمدير، يجب تحديد templateKey بشكل مختلف.
            // نفترض هنا أنه للعميل بناءً على محتوى الرسالة الأصلي.
            $templateKey = 'booking_request_customer'; // استخدام نفس مفتاح طلب الحجز للعميل
            // أو يمكنك إنشاء مفتاح جديد مثل 'new_booking_received_customer' إذا كان المحتوى مختلفاً جداً

            $templateExists = Cache::rememberForever('sms_template_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->exists();
            });

            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("SMS template not found for [{$templateKey}], HttpSmsChannel skipped for NewBookingReceived.", ['booking_id' => $this->booking->id]);
            }
        }
        return $channels;
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
            Log::warning('NewBookingReceived (toHttpSms): Recipient mobile number could not be determined.', ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateKey = 'booking_request_customer'; // أو 'new_booking_received_customer'
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['booking_id' => $this->booking->id]);
            $templateContent = "طلب حجزك للمصورة فاطمة. خدمة: [service_name_short]. طلب رقم: [booking_id].";
            if ($this->paymentMethod === 'bank_transfer') { $templateContent .= " يرجى تحويل العربون للتأكيد."; }
        }

        $parsedBookingDateTime = Carbon::parse($this->booking->booking_datetime);
        $paymentMethodText = $this->paymentMethod === 'bank_transfer' ? 'بنكي' : ($this->paymentMethod === 'tamara' ? 'تمارا' : $this->paymentMethod);


        $replacements = [
            '[customer_name]' => $notifiable->name ?? 'عميلنا العزيز', // نفترض أن $notifiable هو العميل هنا
            '[service_name]' => $this->booking->service ? $this->booking->service->name_ar : 'تصوير',
            '[service_name_short]' => $this->booking->service ? Str::limit($this->booking->service->name_ar, 12, '') : "تصوير",
            '[booking_id]' => $this->booking->id,
            '[booking_date_time]' => $parsedBookingDateTime->translatedFormat('l, d M Y - h:i A'),
            '[payment_method]' => $paymentMethodText, // قد لا يكون هذا المتغير مستخدماً في القالب الافتراضي للعميل
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('NewBookingReceived: Final SMS content from DB template.', [
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
        return [
            'booking_id' => $this->booking->id,
            'event' => 'new_booking_received_concise_sms', // اسم مختلف للتمييز
            'payment_method' => $this->paymentMethod,
        ];
    }
}