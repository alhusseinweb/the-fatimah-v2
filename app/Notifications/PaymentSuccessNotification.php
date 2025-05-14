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
use App\Models\SmsTemplate; // <-- استيراد الموديل الجديد
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- استيراد الكاش
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Invoice $invoice;
    public User $recipient;
    public float $actuallyPaidAmount;
    public string $paidCurrency;

    public function __construct(Invoice $invoice, User $recipient, float $actuallyPaidAmount, string $paidCurrency)
    {
        $this->invoice = $invoice;
        $this->recipient = $recipient;
        $this->actuallyPaidAmount = $actuallyPaidAmount;
        $this->paidCurrency = $paidCurrency;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->email && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        // --- التأكد من وجود رقم جوال قبل إضافة القناة ---
        if ($notifiable->mobile_number) {
            // --- التأكد من وجود قالب لهذا النوع قبل إضافة القناة ---
             $templateKey = $notifiable->is_admin ? 'payment_success_admin' : 'payment_success_customer';
             $templateExists = Cache::rememberForever('sms_template_exists_' . $templateKey, function () use ($templateKey) {
                  return SmsTemplate::where('notification_type', $templateKey)->exists();
             });
             if ($templateExists) {
                 $channels[] = HttpSmsChannel::class;
             } else {
                 Log::warning("SMS template not found for [{$templateKey}], HttpSmsChannel skipped.", ['invoice_id' => $this->invoice->id]);
             }
            // ---------------------------------------------
        }
        // -------------------------------------------------
        return $channels;
    }

    // --- دالة toMail تبقى كما هي ---
     public function toMail(object $notifiable): MailMessage
     {
        // ... (الكود الأصلي لدالة toMail) ...
         $booking = $this->invoice->booking;
         $customer = $booking ? $booking->user : null;
         $serviceName = $booking && $booking->service ? $booking->service->name_ar : 'خدمة غير محددة';
         $bookingId = $booking ? $booking->id : 'غير متوفر';
         $invoiceNumber = $this->invoice->invoice_number;
         $paidAmountFormatted = number_format($this->actuallyPaidAmount, 2) . ' ' . $this->paidCurrency;

         $mailMessage = (new MailMessage);
         $paymentDescription = "دفعة";

         if ($notifiable->is_admin) {
             $mailMessage->subject("تنبيه: تم استلام دفعة للفاتورة رقم #{$invoiceNumber}")
                         ->greeting("مرحبا ايها المدير")
                         ->line("تم استلام {$paymentDescription} بنجاح للفاتورة رقم #{$invoiceNumber} (حجز رقم #{$bookingId}).")
                         ->line("العميل: " . ($customer ? "{$customer->name} ({$customer->mobile_number})" : "غير محدد"))
                         ->line("الخدمة: {$serviceName}")
                         ->line("المبلغ المدفوع في هذه العملية: {$paidAmountFormatted}")
                         ->line("إجمالي الفاتورة: " . number_format($this->invoice->amount, 2) . ' ' . $this->invoice->currency)
                         ->line("الحالة الحالية للفاتورة: " . ($this->invoice->status_label ?? $this->invoice->status))
                         ->action('عرض الفاتورة', route('admin.invoices.show', $this->invoice->id));
         } else {
             // الحصول على رابط الفاتورة للعميل (تأكد من وجود المسار الصحيح)
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
    // ------------------------------


    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);
        // ... (منطق تحويل الرقم الدولي) ...
        if ($recipientPhoneNumber) {
             if (str_starts_with($recipientPhoneNumber, '05')) { $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1); }
             elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { $recipientPhoneNumber = '+966' . $recipientPhoneNumber; }
             // التأكد من وجود علامة +
             elseif (!str_starts_with($recipientPhoneNumber, '+')) { $recipientPhoneNumber = '+' . $recipientPhoneNumber; }
         } else {
            Log::warning('PaymentSuccessNotification (toHttpSms): Recipient mobile number could not be determined.', ['invoice_id' => $this->invoice->id]);
            return []; // لا يمكن الإرسال بدون رقم
        }

        // --- الجزء الجديد: جلب القالب من قاعدة البيانات ---
        $templateKey = $notifiable->is_admin ? 'payment_success_admin' : 'payment_success_customer';

        // استخدام الكاش لجلب القالب لتجنب الاستعلام المتكرر عن قاعدة البيانات
        $templateContent = Cache::rememberForever('sms_template_' . $templateKey, function () use ($templateKey) {
            $template = SmsTemplate::where('notification_type', $templateKey)->first();
            return $template ? $template->template_content : null;
        });

        // إذا لم يتم العثور على القالب، استخدم نصًا افتراضيًا أو سجل خطأ
        if (!$templateContent) {
            Log::error("SMS template not found in DB for [{$templateKey}]. Using default or skipping.", ['invoice_id' => $this->invoice->id]);
            // يمكنك وضع نص افتراضي هنا أو إرجاع مصفوفة فارغة لإلغاء الإرسال
             $defaultMessage = "تم استلام دفعتك بنجاح."; // مثال لنص افتراضي بسيط
             $templateContent = $defaultMessage;
             // return []; // لإلغاء الإرسال إذا لم يوجد قالب
        }
        // -----------------------------------------------------

        // --- تجهيز المتغيرات للاستبدال ---
        $booking = $this->invoice->booking; // جلب الحجز لتسهيل الوصول للبيانات
        $customer = $booking ? $booking->user : null;
        $currencySymbol = $this->invoice->currency_symbol_short ?? 'ر.س'; // رمز عملة مختصر

        $replacements = [
            '[customer_name]' => $customer->name ?? 'العميل',
            '[customer_name_short]' => $customer ? Str::limit($customer->name, 10) : "عميل",
            '[customer_mobile]' => $customer->mobile_number ?? '', // للمدير فقط عادةً
            '[booking_id]' => $booking->id ?? 'N/A',
            '[service_name]' => $booking && $booking->service ? $booking->service->name_ar : 'الخدمة',
            '[service_name_short]' => $booking && $booking->service ? Str::limit($booking->service->name_ar, 12, '') : "تصوير",
            '[invoice_number]' => $this->invoice->invoice_number,
            '[paid_amount_short]' => number_format($this->actuallyPaidAmount, 0) . ' ' . $currencySymbol, // مبلغ مختصر
            '[invoice_total_amount]' => number_format($this->invoice->amount, 0) . ' ' . $currencySymbol, // إجمالي الفاتورة مختصر
            '[invoice_status]' => $this->invoice->status_label ?? $this->invoice->status, // حالة الفاتورة (للمدير)
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'), // اسم المصورة من الإعدادات
             // أضف أي متغيرات أخرى مشتركة أو خاصة بهذا الإشعار
        ];
        // ---------------------------------

        // --- استبدال المتغيرات في القالب ---
        $messageContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);
        // ----------------------------------

        Log::debug('PaymentSuccessNotification: Final SMS content from DB template.', [
            'template_key' => $templateKey,
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'to' => $recipientPhoneNumber,
            'final_content' => $messageContent,
            'content_length_chars_arabic' => mb_strlen($messageContent, 'UTF-8'),
        ]);

        // --- إرجاع البيانات لقناة الإرسال ---
        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
        // ---------------------------------
    }

    // --- دالة toArray تبقى كما هي ---
    public function toArray(object $notifiable): array
    {
        // ... (الكود الأصلي لدالة toArray) ...
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