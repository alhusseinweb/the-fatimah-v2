<?php

namespace App\Notifications;

use App\Models\Booking;
// use App\Models\User; // $notifiable هو المستلم، لا حاجة لـ User هنا كـ property
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
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
use Illuminate\Support\HtmlString;


class BookingRequestReceived extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    public string $paymentMethod;
    public ?float $amountForNotification; // المبلغ الذي سيتم عرضه في الإشعار (قد يكون العربون أو الإجمالي)
    public ?string $paymentOptionForNotification; // خيار الدفع (عربون أو كامل)

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Booking $booking
     * @param string $paymentMethod
     * @param float|null $amountForNotification
     * @param string|null $paymentOptionForNotification
     */
    public function __construct(Booking $booking, string $paymentMethod, ?float $amountForNotification = null, ?string $paymentOptionForNotification = null)
    {
        $this->booking = $booking;
        $this->paymentMethod = $paymentMethod;
        $this->amountForNotification = $amountForNotification ?? $booking->invoice?->amount; // قيمة افتراضية هي إجمالي الفاتورة
        $this->paymentOptionForNotification = $paymentOptionForNotification ?? $booking->invoice?->payment_option;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        $logContext = [
            'notification' => class_basename($this),
            'notifiable_id' => $notifiable->id,
            'notifiable_type' => get_class($notifiable),
            'is_admin' => $notifiable->is_admin ?? 'N/A', // التأكد من أن الخاصية is_admin موجودة
            'booking_id' => $this->booking->id
        ];

        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
            Log::info(class_basename($this) . ": Mail channel ADDED.", $logContext + ['email' => $notifiable->email]);
        } else {
            Log::warning(class_basename($this) . ": Mail channel SKIPPED (email missing or invalid).", $logContext + ['email_provided' => $notifiable->email ?? 'N/A']);
        }

        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = ($notifiable->is_admin ?? false) ? 'booking_request_admin' : 'booking_request_customer';
            $smsChannels = $this->determineSmsChannels($templateKey, $notifiable);
            $channels = array_merge($channels, $smsChannels);

            if (empty($smsChannels)) {
                Log::warning(class_basename($this) . ": No SMS/WhatsApp channels determined for notifiable ID {$notifiable->id}.");
            }
        }
        
        if(empty($channels)){
            Log::error(class_basename($this) . ": No channels determined.", $logContext);
        } else {
            Log::info(class_basename($this) . ": Channels determined.", $logContext + ['channels' => $channels]);
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  object  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $serviceName = $this->booking->service ? ($this->booking->service->name_ar ?? $this->booking->service->name_en) : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user; // هذا هو العميل صاحب الحجز
        $mailMessage = (new MailMessage);

        $paymentMethodReadable = match ($this->paymentMethod) {
            'bank_transfer' => 'تحويل بنكي',
            'tamara' => 'تمارا',
            'manual_confirmation_due_to_no_gateway' => 'بانتظار التأكيد اليدوي',
            default => Str::title(str_replace('_', ' ', $this->paymentMethod)),
        };

        $amountToDisplay = $this->amountForNotification ?? $this->booking->invoice?->amount ?? 0;
        $currencyDisplay = $this->booking->invoice?->currency ?: 'SAR';
        $formattedAmount = number_format($amountToDisplay, 2) . ' ' . $currencyDisplay;

        $paymentOptionText = '';
        if($this->paymentOptionForNotification === 'down_payment'){
            $paymentOptionText = " (عربون: {$formattedAmount})";
        } elseif($this->paymentOptionForNotification === 'full'){
            $paymentOptionText = " (المبلغ كاملاً: {$formattedAmount})";
        }


        if ($notifiable->is_admin ?? false) {
            $mailMessage->subject("تنبيه إداري: طلب حجز جديد رقم #{$this->booking->id} من {$customerUser->name}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم استلام طلب حجز جديد من العميل: **{$customerUser->name}** (جوال: {$customerUser->mobile_number}).")
                        ->line(new HtmlString("<strong>تفاصيل الطلب:</strong>"))
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد المطلوب: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->line("- طريقة الدفع المختارة: {$paymentMethodReadable}{$paymentOptionText}");

            if ($this->paymentMethod === 'bank_transfer') {
                $mailMessage->line(new HtmlString("يرجى متابعة العميل لتأكيد استلام الدفعة."));
            }

            $mailMessage->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->lineIf($this->booking->customer_notes, "- ملاحظات العميل: {$this->booking->customer_notes}")
                        ->action('مراجعة الطلب في لوحة التحكم', route('admin.bookings.show', $this->booking->id))
                        ->line('يرجى متابعة الطلب واتخاذ الإجراء اللازم.');
        } else { // للعميل
            $bookingUrl = route('booking.pending', $this->booking->id); 
            $mailMessage->subject("تم استلام طلب حجزك رقم #{$this->booking->id} لدى المصورة فاطمة")
                        ->greeting("مرحباً {$notifiable->name}،")
                        ->line("شكراً لك! لقد استلمنا طلب حجزك بنجاح لدى المصورة فاطمة.")
                        ->line("طلبك الآن قيد المراجعة أو بانتظار إتمام عملية الدفع حسب الطريقة المختارة.")
                        ->line(new HtmlString("<strong>تفاصيل طلبك المبدئي:</strong>"))
                        ->line(new HtmlString("- رقم الطلب: <strong>{$this->booking->id}</strong>"))
                        ->line(new HtmlString("- الخدمة: <strong>{$serviceName}</strong>"))
                        ->line(new HtmlString("- التاريخ والوقت: <strong>{$bookingDateTime}</strong>"))
                        ->line(new HtmlString("- طريقة الدفع: <strong>{$paymentMethodReadable}{$paymentOptionText}</strong>"));

            if ($this->paymentMethod === 'bank_transfer') {
                $mailMessage->line(new HtmlString("لتأكيد حجزك، يرجى القيام بتحويل المبلغ المطلوب. ستجد تفاصيل الحساب البنكي وقيمة الدفعة عند متابعة حالة الطلب."));
            } elseif ($this->paymentMethod === 'manual_confirmation_due_to_no_gateway') {
                 $mailMessage->line(new HtmlString("سيتم التواصل معك قريباً من قبل فريقنا لتأكيد الحجز وترتيب عملية الدفع."));
            }
            $mailMessage->line("يمكنك متابعة حالة طلبك، عرض تفاصيل الدفع، وإتمام العملية من خلال الرابط التالي:")
                        ->action('متابعة حالة الطلب / الدفع', $bookingUrl)
                        ->line("سنقوم بإعلامك فور تأكيد الحجز بشكل نهائي أو في حال وجود أي تحديثات.");
        }
        return $mailMessage->salutation('مع تحياتنا، فريق ' . config('app.name'));
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @param  object  $notifiable
     * @return array
     */
    public function toHttpSms(object $notifiable): array
    {
        $recipientPhoneNumber = $this->formatSmsRecipient($notifiable->mobile_number);
        if (!$recipientPhoneNumber) {
            Log::warning(class_basename($this) . ' (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        $templateIdentifier = ($notifiable->is_admin ?? false) ? 'booking_request_admin' : 'booking_request_customer';

        $paymentMethodText = match ($this->paymentMethod) {
            'bank_transfer' => 'تحويل بنكي',
            'tamara' => 'تمارا',
            'manual_confirmation_due_to_no_gateway' => 'سيتم التواصل معك',
            default => Str::title(str_replace('_', ' ', $this->paymentMethod)),
        };

        $bookingDateFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('Y/m/d');
        $bookingTimeFormatted = Carbon::parse($this->booking->booking_datetime)->translatedFormat('h:ia');
        $amountToDisplaySms = $this->amountForNotification ?? $this->booking->invoice?->amount ?? 0;
        $currencySms = $this->booking->invoice?->currency ?: 'ر.س';
        $formattedAmountSms = number_format($amountToDisplaySms, 0) . ' ' . $currencySms; // بدون كسور عشرية للرسائل القصيرة

        $paymentOptionTextSms = '';
        if ($this->paymentOptionForNotification === 'down_payment') {
            $paymentOptionTextSms = "عربون {$formattedAmountSms}";
        } elseif ($this->paymentOptionForNotification === 'full') {
            $paymentOptionTextSms = "كامل {$formattedAmountSms}";
        }


        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
            '[payment_details_prompt]' => (!$notifiable->is_admin && $this->paymentMethod === 'bank_transfer') ? " يرجى دفع العربون لتأكيد الحجز." : "",
            '[booking_date]' => $bookingDateFormatted,
            '[booking_time]' => $bookingTimeFormatted,
            '[booking_date_time]' => $bookingDateFormatted . ' ' . $bookingTimeFormatted,
            '[service_name_short]' => Str::limit($this->booking->service?->name_ar, 20), // اسم خدمة مختصر
            '[amount_due]' => $formattedAmountSms, // المبلغ المطلوب دفعه الآن
            '[payment_option_details]' => $paymentOptionTextSms, // تفاصيل خيار الدفع
        ];

        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            Log::warning(class_basename($this) . " (toHttpSms): SMS message content is empty after processing template for notifiable ID {$notifiable->id}.", [
                'booking_id' => $this->booking->id, 
                'template_identifier_used' => $templateIdentifier,
                'is_admin_recipient' => $notifiable->is_admin ?? 'N/A'
            ]);
            // رسالة SMS افتراضية إذا فشل القالب
            if (!$notifiable->is_admin && $templateIdentifier === 'booking_request_customer') {
                $messageContent = "طلب حجزك رقم {$this->booking->id} لدى المصورة فاطمة قيد المعالجة. طريقة الدفع: {$paymentMethodText}.";
                if ($this->paymentMethod === 'bank_transfer') {
                    $messageContent .= " يرجى دفع العربون.";
                }
            } elseif (($notifiable->is_admin ?? false) && $templateIdentifier === 'booking_request_admin') {
                $messageContent = "تنبيه إداري: طلب حجز جديد #{$this->booking->id} للعميل {$this->booking->user?->name}.";
            }
            if (empty($messageContent)) return [];
        }
        
        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
        ];
    }

    public function toWhatsApp(object $notifiable): array
    {
        $smsData = $this->toHttpSms($notifiable);
        if (empty($smsData)) return [];

        return [
            'to' => $this->formatWhatsAppRecipient($notifiable->mobile_number),
            'content' => $smsData['content'],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'recipient_id' => $notifiable->id,
            'recipient_type' => ($notifiable->is_admin ?? false) ? 'admin' : 'customer',
            'event' => 'booking_request_received',
            'payment_method' => $this->paymentMethod,
            'amount_for_notification' => $this->amountForNotification,
            'payment_option_for_notification' => $this->paymentOptionForNotification,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
