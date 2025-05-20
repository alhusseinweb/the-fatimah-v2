<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Traits\ManagesSmsContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsTemplate;
use Illuminate\Support\Str;
use Carbon\Carbon; // تأكد من وجود هذا

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Invoice $invoice;
    public float $actuallyPaidAmount;
    public string $paidCurrency;

    public function __construct(Invoice $invoice, float $actuallyPaidAmount, string $paidCurrency)
    {
        $this->invoice = $invoice;
        $this->actuallyPaidAmount = $actuallyPaidAmount;
        $this->paidCurrency = $paidCurrency;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        $logContext = [
            'notification' => class_basename($this),
            'notifiable_id' => $notifiable->id,
            'notifiable_type' => get_class($notifiable),
            'is_admin' => $notifiable->is_admin ?? 'N/A',
            'invoice_id' => $this->invoice->id
        ];

        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
            Log::info('PaymentSuccessNotification: Mail channel ADDED.', $logContext + ['email' => $notifiable->email]);
        } else {
            Log::warning("PaymentSuccessNotification: Mail channel SKIPPED (email missing or invalid).", $logContext + ['email_provided' => $notifiable->email ?? 'N/A']);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'payment_success_admin' : 'payment_success_customer';
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(5), function () use ($templateKey) { // تقليل مدة الكاش للاختبار
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
                Log::info("PaymentSuccessNotification: HttpSmsChannel ADDED.", $logContext + ['template_key' => $templateKey]);
            } else {
                Log::warning("PaymentSuccessNotification: HttpSmsChannel SKIPPED (template '{$templateKey}' not found or inactive).", $logContext);
            }
        } else {
            Log::warning("PaymentSuccessNotification: HttpSmsChannel SKIPPED (mobile_number missing).", $logContext);
        }
        
        if(empty($channels)){
            Log::error("PaymentSuccessNotification: No channels determined.", $logContext);
        } else {
            Log::info("PaymentSuccessNotification: Channels determined.", $logContext + ['channels' => $channels]);
        }
        return $channels;
    }

    // ... (دوال toMail, toHttpSms, toArray كما قدمتها لك سابقًا، فهي صحيحة من حيث مفتاح القالب) ...
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->invoice->booking;
        $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
        $bookingId = $booking ? $booking->id : 'غير متوفر';
        $invoiceNumber = $this->invoice->invoice_number;
        $paidAmountFormatted = number_format($this->actuallyPaidAmount, 2) . ' ' . $this->paidCurrency;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه إداري: تم استلام دفعة للفاتورة رقم #{$invoiceNumber}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم استلام دفعة بنجاح للفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                        ->line("العميل: " . ($booking->user ? "{$booking->user->name} ({$booking->user->mobile_number})" : "غير محدد"))
                        ->line("الخدمة: {$serviceName}")
                        ->line("المبلغ المدفوع في هذه العملية: {$paidAmountFormatted}")
                        ->line("إجمالي الفاتورة: " . number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency)
                        ->line("الحالة الحالية للفاتورة: " . ($this->invoice->status_label ?? $this->invoice->status))
                        ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
        } else { // للعميل
            $customerInvoiceUrl = route('customer.invoices.show', $this->invoice->id);
            $mailMessage->subject("شكراً لك، تم استلام دفعتك بنجاح!")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نشكرك جزيل الشكر، لقد تم استلام دفعتك بنجاح لحجزك رقم #{$bookingId} (فاتورة رقم #{$invoiceNumber}).")
                        ->line("المبلغ المدفوع في هذه العملية: **{$paidAmountFormatted}**.")
                        ->line("الخدمة: {$serviceName}.")
                        ->line("حالة الفاتورة الآن: " . ($this->invoice->status_label ?? $this->invoice->status) . ".")
                        ->lineIf($this->invoice->status === Invoice::STATUS_PAID, "لقد تم دفع مبلغ الفاتورة بالكامل.")
                        ->lineIf($this->invoice->status === Invoice::STATUS_PARTIALLY_PAID, "تم استلام العربون. سيتم تأكيد حجزك بشكل نهائي قريباً.")
                        ->line("إذا كان هذا الدفع يؤكد حجزك، فستتلقى إشعار تأكيد منفصل قريباً (إذا لم تكن قد تلقيته بالفعل).")
                         ->action('عرض تفاصيل الفاتورة', $customerInvoiceUrl);
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق ' . config('app.name', 'المصورة فاطمة علي'));
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('PaymentSuccessNotification (toHttpSms): Recipient mobile number could not be determined or is invalid for notifiable ID ' . $notifiable->id, ['invoice_id' => $this->invoice->id]);
            return [];
        }

        $templateIdentifier = $notifiable->is_admin ? 'payment_success_admin' : 'payment_success_customer';

        $currencySymbol = $this->invoice->currency_symbol_short ?? ($this->invoice->currency ?? 'ر.س');
        $specificReplacements = [
            '[invoice_number]' => $this->invoice->invoice_number,
            '[paid_amount_short]' => number_format($this->actuallyPaidAmount, 0) . ' ' . $currencySymbol,
            '[invoice_total_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol,
            '[invoice_status]' => $this->invoice->status_label ?? $this->invoice->status,
            '[remaining_amount_short]' => $this->invoice->status === Invoice::STATUS_PARTIALLY_PAID ? (number_format($this->invoice->remaining_amount, 0) . ' ' . $currencySymbol) : '',
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->invoice->booking);

        if (empty($messageContent)) {
            Log::warning('PaymentSuccessNotification (toHttpSms): SMS message content is empty after processing template for notifiable ID ' . $notifiable->id, [
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
            'event' => 'payment_success',
            // ... (بقية البيانات)
        ];
    }
}
