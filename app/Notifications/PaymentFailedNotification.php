<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\Channels\HttpSmsChannel; // <-- تم التغيير
use App\Notifications\Traits\ManagesSmsContent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // لإستخدام via
use App\Models\SmsTemplate;      // لإستخدام via
use Illuminate\Support\Str;


class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Invoice $invoice;
    public ?string $reason;
    // public User $recipient; // $notifiable هو المستلم

    public function __construct(Invoice $invoice, /* User $recipient, */ ?string $reason = null)
    {
        $this->invoice = $invoice;
        // $this->recipient = $recipient;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        if ($notifiable->mobile_number) {
            $templateKey = $notifiable->is_admin ? 'payment_failed_admin' : 'payment_failed_customer';
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("PaymentFailedNotification: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['invoice_id' => $this->invoice->id]);
            }
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ... (الكود الأصلي كما هو) ...
        $booking = $this->invoice->booking;
        $customerUser = $booking ? $booking->user : null;
        $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
        $bookingId = $booking ? $booking->id : 'غير متوفر';
        $invoiceNumber = $this->invoice->invoice_number;
        $amount = number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: فشل عملية دفع للفاتورة رقم #{$invoiceNumber}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("نود إعلامك بفشل عملية دفع متعلقة بالفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                        ->line("العميل: " . ($customerUser ? "{$customerUser->name} ({$customerUser->mobile_number})" : "غير محدد"))
                        ->line("الخدمة: {$serviceName}")
                        ->line("مبلغ الفاتورة: {$amount}")
                        ->lineIf($this->reason, "سبب الفشل (إن وجد): {$this->reason}")
                        ->line("يرجى مراجعة تفاصيل الفاتورة والحجز في لوحة التحكم.")
                        ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
        } else {
            $customerInvoiceUrl = route('customer.invoices.show', $this->invoice->id);
            $retryPaymentUrl = '#';
            if ($this->invoice->payment_method === 'tamara' && $this->invoice->booking_id) {
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

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('PaymentFailedNotification (toHttpSms): Recipient mobile number could not be determined.', ['invoice_id' => $this->invoice->id]);
            return [];
        }

        $currencySymbol = $this->invoice->currency_symbol_short ?? ($this->invoice->currency ?? 'ر.س');
        $specificReplacements = [
            '[invoice_number]' => $this->invoice->invoice_number,
            '[reason]' => $this->reason ?? '',
            '[reason_short]' => $this->reason ? (' سبب:' . Str::limit($this->reason, 20, '..')) : '',
            '[invoice_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol,
        ];

        $messageContent = $this->getSmsMessageContent('payment_failed', $notifiable, $specificReplacements, $this->invoice->booking);

        if (empty($messageContent)) {
            Log::warning('PaymentFailedNotification (toHttpSms): SMS message content is empty after processing template.', ['invoice_id' => $this->invoice->id, 'template_identifier' => 'payment_failed']);
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
