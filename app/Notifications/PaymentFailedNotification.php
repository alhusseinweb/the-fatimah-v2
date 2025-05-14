<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\HttpSmsChannel;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Booking;
use App\Models\SmsTemplate; // <-- استيراد
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- استيراد
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Invoice $invoice;
    public ?string $reason;
    public User $recipient;

    /**
     * Create a new notification instance.
     *
     * @param Invoice $invoice
     * @param User $recipient
     * @param string|null $reason
     */
    public function __construct(Invoice $invoice, User $recipient, ?string $reason = null)
    {
        $this->invoice = $invoice;
        $this->recipient = $recipient;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'payment_failed_admin' : 'payment_failed_customer';
            $templateExists = Cache::rememberForever('sms_template_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("SMS template not found for [{$templateKey}], HttpSmsChannel skipped.", ['invoice_id' => $this->invoice->id]);
            }
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->invoice->booking;
        $customer = $booking ? $booking->user : null;
        $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
        $bookingId = $booking ? $booking->id : 'غير متوفر';
        $invoiceNumber = $this->invoice->invoice_number;
        $amount = number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency;

        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: فشل عملية دفع للفاتورة رقم #{$invoiceNumber}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("نود إعلامك بفشل عملية دفع متعلقة بالفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                        ->line("العميل: " . ($customer ? "{$customer->name} ({$customer->mobile_number})" : "غير محدد"))
                        ->line("الخدمة: {$serviceName}")
                        ->line("مبلغ الفاتورة: {$amount}")
                        ->lineIf($this->reason, "سبب الفشل (إن وجد): {$this->reason}")
                        ->line("يرجى مراجعة تفاصيل الفاتورة والحجز في لوحة التحكم.")
                        ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
        } else {
            $customerInvoiceUrl = route('customer.invoices.show', $this->invoice->id); // رابط فاتورة العميل
            $retryPaymentUrl = '#'; // يمكنك إنشاء مسار لإعادة محاولة الدفع إذا كان ذلك منطقياً
            // على سبيل المثال، إذا كان لديك مسار لإعادة توجيه العميل إلى صفحة الدفع للفاتورة
            if ($this->invoice->payment_method === 'tamara' && $this->invoice->booking_id) {
                 // قد يكون هناك مسار خاص لإعادة محاولة الدفع عبر تمارا
                 // $retryPaymentUrl = route('payment_retry_tamara', $this->invoice->id);
                 // أو رابط صفحة حالة الحجز التي قد تحتوي على زر إعادة المحاولة
                 $retryPaymentUrl = route('booking.pending', $this->invoice->booking_id);
            }


            $mailMessage->subject("مشكلة في عملية الدفع لحجزك رقم #{$bookingId}")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نأسف لإعلامك بأنه تعذرت عملية الدفع المتعلقة بحجزك رقم #{$bookingId} (فاتورة رقم #{$invoiceNumber}).")
                        ->line("الخدمة: {$serviceName}")
                        ->line("المبلغ: {$amount}")
                        ->lineIf($this->reason, "السبب المحتمل: {$this->reason}")
                        ->line("يرجى محاولة الدفع مرة أخرى في أقرب وقت ممكن لضمان تأكيد حجزك.")
                        ->action('مراجعة الفاتورة أو إعادة محاولة الدفع', $retryPaymentUrl)
                        ->line("إذا استمرت المشكلة، يرجى التواصل معنا للمساعدة.");
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }

    /**
     * Get the HttpSms representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        if (!$recipientPhoneNumber && isset($notifiable->mobile_number)) {
            $recipientPhoneNumber = $notifiable->mobile_number;
        }

        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
            elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
            elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
        } else {
            Log::warning('PaymentFailedNotification (toHttpSms): Recipient mobile number could not be determined.', ['invoice_id' => $this->invoice->id]);
            return [];
        }

        $templateKey = $notifiable->is_admin ? 'payment_failed_admin' : 'payment_failed_customer';
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        if (!$templateContent) {
            Log::error("SMS template not found for [{$templateKey}]. Using default.", ['invoice_id' => $this->invoice->id]);
            if ($notifiable->is_admin) {
                $templateContent = "فشل دفع! حجز:[booking_id]. عميل:[customer_name_short]. فاتورة:[invoice_number].";
            } else {
                $templateContent = "نأسف، تعذر الدفع لحجزك رقم [booking_id]. يرجى المحاولة مجددا او التواصل معنا.[reason_short]";
            }
        }

        $booking = $this->invoice->booking;
        $customer = $booking ? $booking->user : null;
        $currencySymbol = $this->invoice->currency_symbol_short ?? 'ر.س';

        $replacements = [
            '[customer_name]' => $customer->name ?? 'العميل',
            '[customer_name_short]' => $customer ? Str::limit($customer->name, 10) : "عميل",
            '[customer_mobile]' => $customer->mobile_number ?? '',
            '[booking_id]' => $booking->id ?? 'N/A',
            '[invoice_number]' => $this->invoice->invoice_number,
            '[reason]' => $this->reason ?? '',
            '[reason_short]' => $this->reason ? (' سبب:' . Str::limit($this->reason, 20, '..')) : '', // إضافة "سبب:" فقط إذا وجد السبب
            '[invoice_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol,
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
        ];

        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

        Log::debug('PaymentFailedNotification: Final SMS content from DB template.', [
            'template_key' => $templateKey,
            'to' => $recipientPhoneNumber,
            'final_content' => $messageContent,
        ]);

        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'booking_id' => $this->invoice->booking_id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'event' => 'payment_failed',
            'reason' => $this->reason,
            'channels_used' => $this->via($notifiable),
        ];
    }
}