<?php

namespace App\Notifications;

// ... (use statements كما هي أو مع إضافة اللازم إذا احتجت) ...
use App\Models\Booking;
use App\Models\Invoice;
// use App\Models\User;
use App\Notifications\Channels\HttpSmsChannel;
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


class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Invoice $invoice;
    public ?string $reason;

    public function __construct(Invoice $invoice, ?string $reason = null)
    {
        $this->invoice = $invoice;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("PaymentFailedNotification: Email not sent to notifiable ID {$notifiable->id}, email missing or invalid.", ['invoice_id' => $this->invoice->id]);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'payment_failed_admin' : 'payment_failed_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("PaymentFailedNotification: SMS template '{$templateKey}' for notifiable ID {$notifiable->id} not found or not active, HttpSmsChannel skipped.", ['invoice_id' => $this->invoice->id]);
            }
        } else {
            Log::warning("PaymentFailedNotification: SMS not sent to notifiable ID {$notifiable->id}, mobile_number missing.", ['invoice_id' => $this->invoice->id]);
        }
        
        if(empty($channels)){
            Log::error("PaymentFailedNotification: No channels determined for notifiable ID {$notifiable->id}.", ['invoice_id' => $this->invoice->id, 'is_admin' => $notifiable->is_admin ?? 'N/A']);
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->invoice->booking;
        $customerUser = $booking ? $booking->user : null;
        $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
        $bookingId = $booking ? $booking->id : 'غير متوفر';
        $invoiceNumber = $this->invoice->invoice_number;
        $amount = number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه إداري: فشل عملية دفع للفاتورة رقم #{$invoiceNumber}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("نود إعلامك بفشل عملية دفع متعلقة بالفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                        ->line("العميل: " . ($customerUser ? "{$customerUser->name} ({$customerUser->mobile_number})" : "غير محدد"))
                        ->line("الخدمة: {$serviceName}")
                        ->line("مبلغ الفاتورة: {$amount}")
                        ->lineIf($this->reason, "سبب الفشل (إن وجد): {$this->reason}")
                        ->line("يرجى مراجعة تفاصيل الفاتورة والحجز في لوحة التحكم.")
                        ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
        } else { // للعميل
            $customerInvoiceUrl = route('customer.invoices.show', $this->invoice->id);
            $retryPaymentUrl = '#'; // رابط افتراضي
            if ($this->invoice->booking_id) { // تحقق من وجود حجز مرتبط لإعادة المحاولة
                 // افترض أن صفحة الانتظار هي المكان المناسب لإعادة المحاولة
                 $retryPaymentUrl = route('booking.pending', $this->invoice->booking_id);
                 // أو إذا كان لديك مسار مخصص لإعادة دفع فاتورة:
                 // $retryPaymentUrl = route('customer.invoices.retry-payment', $this->invoice->id);
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
        return $mailMessage->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('PaymentFailedNotification (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['invoice_id' => $this->invoice->id]);
            return [];
        }

        // --- START: التعديل الهام هنا ---
        $templateIdentifier = $notifiable->is_admin ? 'payment_failed_admin' : 'payment_failed_customer';
        // --- END: التعديل الهام هنا ---

        $currencySymbol = $this->invoice->currency_symbol_short ?? ($this->invoice->currency ?? 'ر.س');
        $specificReplacements = [
            '[invoice_number]' => $this->invoice->invoice_number,
            '[reason]' => $this->reason ?? '',
            '[reason_short]' => $this->reason ? (' سبب:' . Str::limit($this->reason, 20, '..')) : '',
            '[invoice_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol,
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->invoice->booking);

        if (empty($messageContent)) {
            Log::warning('PaymentFailedNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
                'invoice_id' => $this->invoice->id, 
                'template_identifier_used' => $templateIdentifier
            ]);
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
