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


class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Invoice $invoice;
    public float $actuallyPaidAmount;
    public string $paidCurrency;

    // تم إزالة User $recipient من هنا لأن $notifiable هو المستلم
    public function __construct(Invoice $invoice, float $actuallyPaidAmount, string $paidCurrency)
    {
        $this->invoice = $invoice;
        $this->actuallyPaidAmount = $actuallyPaidAmount;
        $this->paidCurrency = $paidCurrency;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        if ($notifiable->mobile_number) { // لا يوجد تحقق من config لـ SMS_GATEWAY_ENABLED هنا، نفترض إذا كان هناك رقم وقالب، حاول الإرسال
            $templateKey = $notifiable->is_admin ? 'payment_success_admin' : 'payment_success_customer';
            $templateExists = Cache::rememberForever('sms_template_active_exists_' . $templateKey, function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->exists();
            });
            if ($templateExists) {
                $channels[] = HttpSmsChannel::class; // <-- تم التغيير
            } else {
                Log::warning("PaymentSuccessNotification: SMS template '{$templateKey}' not found or not active, HttpSmsChannel skipped.", ['invoice_id' => $this->invoice->id]);
            }
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->invoice->booking;
        // $customer = $booking ? $booking->user : null; // $notifiable هو المستلم
        $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
        $bookingId = $booking ? $booking->id : 'غير متوفر';
        $invoiceNumber = $this->invoice->invoice_number;
        $paidAmountFormatted = number_format($this->actuallyPaidAmount, 2) . ' ' . $this->paidCurrency;
        $mailMessage = (new MailMessage);

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه: تم استلام دفعة للفاتورة رقم #{$invoiceNumber}")
                        ->greeting("مرحبا ايها المدير")
                        ->line("تم استلام دفعة بنجاح للفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                        ->line("العميل: " . ($booking->user ? "{$booking->user->name} ({$booking->user->mobile_number})" : "غير محدد"))
                        ->line("الخدمة: {$serviceName}")
                        ->line("المبلغ المدفوع في هذه العملية: {$paidAmountFormatted}")
                        ->line("إجمالي الفاتورة: " . number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency)
                        ->line("الحالة الحالية للفاتورة: " . ($this->invoice->status_label ?? $this->invoice->status))
                        ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
        } else {
            $customerInvoiceUrl = route('customer.invoices.show', $this->invoice->id);
            $mailMessage->subject("شكراً لك، تم استلام دفعتك بنجاح!")
                        ->greeting("مرحباً {$notifiable->name},")
                        ->line("نشكرك جزيل الشكر، لقد تم استلام دفعتك بنجاح لحجزك رقم #{$bookingId} (فاتورة رقم #{$invoiceNumber}).")
                        ->line("المبلغ المدفوع في هذه العملية: **{$paidAmountFormatted}**.")
                        ->line("الخدمة: {$serviceName}.")
                        ->line("إذا كان هذا الدفع يؤكد حجزك، فستتلقى إشعار تأكيد منفصل قريباً (إذا لم تكن قد تلقيته بالفعل).")
                         ->action('عرض تفاصيل الفاتورة', $customerInvoiceUrl);
        }
        return $mailMessage->salutation('مع خالص التقدير، فريق المصورة فاطمة علي');
    }

    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning('PaymentSuccessNotification (toHttpSms): Recipient mobile number could not be determined or is invalid.', ['invoice_id' => $this->invoice->id]);
            return [];
        }

        $currencySymbol = $this->invoice->currency_symbol_short ?? ($this->invoice->currency ?? 'ر.س');
        $specificReplacements = [
            '[invoice_number]' => $this->invoice->invoice_number,
            '[paid_amount_short]' => number_format($this->actuallyPaidAmount, 0) . ' ' . $currencySymbol,
            '[invoice_total_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol,
            '[invoice_status]' => $this->invoice->status_label ?? $this->invoice->status,
        ];

        $messageContent = $this->getSmsMessageContent('payment_success', $notifiable, $specificReplacements, $this->invoice->booking);

        if (empty($messageContent)) {
            Log::warning('PaymentSuccessNotification (toHttpSms): SMS message content is empty after processing template.', [
                'invoice_id' => $this->invoice->id, 'template_identifier' => 'payment_success']);
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
            'actually_paid_amount' => $this->actuallyPaidAmount,
            'paid_currency' => $this->paidCurrency,
            'invoice_total_amount' => $this->invoice->amount,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
