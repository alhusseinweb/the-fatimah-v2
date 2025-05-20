<?php

namespace App\Notifications;

use App\Models\Booking;
// use App\Models\User; // $notifiable هو المستلم، لا حاجة لـ User هنا كـ property
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
use Illuminate\Support\HtmlString;


class BookingRequestReceived extends Notification implements ShouldQueue
{
    use Queueable, ManagesSmsContent;

    public Booking $booking;
    public string $paymentMethod;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Booking $booking
     * @param string $paymentMethod
     */
    public function __construct(Booking $booking, string $paymentMethod)
    {
        $this->booking = $booking;
        $this->paymentMethod = $paymentMethod;
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

        // إرسال بريد إلكتروني دائمًا إذا كان البريد متوفرًا
        if (isset($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        } else {
            Log::warning("BookingRequestReceived: Email not sent to notifiable ID {$notifiable->id} because email is missing or invalid.", ['booking_id' => $this->booking->id]);
        }

        // إرسال SMS إذا كان رقم الجوال متوفرًا وهناك قالب نشط
        if (isset($notifiable->mobile_number) && !empty($notifiable->mobile_number)) {
            $templateKey = $notifiable->is_admin ? 'booking_request_admin' : 'booking_request_customer';
            // يمكنك استخدام الكاش هنا كما فعلت، أو التحقق مباشرة إذا كنت تفضل ذلك عند كل مرة لضمان الحداثة
            $templateExists = Cache::remember('sms_template_active_exists_' . $templateKey, now()->addMinutes(60), function () use ($templateKey) {
                 return SmsTemplate::where('notification_type', $templateKey)
                                   ->where('is_active', true)
                                   ->exists();
            });

            if ($templateExists) {
                $channels[] = HttpSmsChannel::class;
            } else {
                Log::warning("BookingRequestReceived: SMS template '{$templateKey}' for notifiable ID {$notifiable->id} not found, not active, or empty in DB. SMS channel skipped.", ['booking_id' => $this->booking->id]);
                // يمكنك إضافة منطق لإرسال رسالة SMS افتراضية هنا إذا أردت، كما كان في NewBookingReceived
                // ولكن الأفضل هو التأكد من وجود القوالب.
            }
        } else {
            Log::warning("BookingRequestReceived: SMS not sent to notifiable ID {$notifiable->id} because mobile_number is missing.", ['booking_id' => $this->booking->id]);
        }
        
        if(empty($channels)){
            Log::error("BookingRequestReceived: No channels determined for notifiable ID {$notifiable->id}. Check email/mobile and templates.", ['booking_id' => $this->booking->id, 'is_admin' => $notifiable->is_admin ?? 'N/A']);
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
        $serviceName = $this->booking->service ? $this->booking->service->name_ar : 'الخدمة المختارة';
        $bookingDateTime = Carbon::parse($this->booking->booking_datetime)->translatedFormat('l، d F Y - الساعة h:i A');
        $customerUser = $this->booking->user; // هذا هو العميل صاحب الحجز
        $mailMessage = (new MailMessage);

        $paymentMethodReadable = match ($this->paymentMethod) {
            'bank_transfer' => 'تحويل بنكي',
            'tamara' => 'تمارا',
            'cash_on_delivery' => 'الدفع عند الاستلام', // افترض أن هذا قد يكون خيارًا
            default => Str::title(str_replace('_', ' ', $this->paymentMethod)),
        };

        if ($notifiable->is_admin) {
            $mailMessage->subject("تنبيه إداري: طلب حجز جديد رقم #{$this->booking->id} من {$customerUser->name}")
                        ->greeting("مرحباً أيها المدير،")
                        ->line("تم استلام طلب حجز جديد من العميل: **{$customerUser->name}** (جوال: {$customerUser->mobile_number}).")
                        ->line(new HtmlString("<strong>تفاصيل الطلب:</strong>"))
                        ->line("- الخدمة: {$serviceName}")
                        ->line("- الموعد المطلوب: {$bookingDateTime}")
                        ->line("- رقم الطلب: {$this->booking->id}")
                        ->line("- طريقة الدفع المختارة: {$paymentMethodReadable}");

            if ($this->paymentMethod === 'bank_transfer') {
                $mailMessage->line(new HtmlString("- حالة الدفعة: بانتظار تأكيد استلام العربون/المبلغ من العميل."));
            }

            $mailMessage->lineIf($this->booking->event_location, "- مكان الحدث: {$this->booking->event_location}")
                        ->lineIf($this->booking->customer_notes, "- ملاحظات العميل: {$this->booking->customer_notes}")
                        ->action('مراجعة الطلب في لوحة التحكم', route('admin.bookings.show', $this->booking->id))
                        ->line('يرجى متابعة الطلب واتخاذ الإجراء اللازم.');
        } else { // للعميل
            $bookingUrl = route('booking.pending', $this->booking->id); // أو customer.bookings.show
            $mailMessage->subject("تم استلام طلب حجزك رقم #{$this->booking->id} لدى المصورة فاطمة")
                        ->greeting("مرحباً {$notifiable->name}،")
                        ->line("شكراً لك! لقد استلمنا طلب حجزك بنجاح لدى المصورة فاطمة.")
                        ->line("طلبك الآن قيد المراجعة أو بانتظار إتمام عملية الدفع حسب الطريقة المختارة.")
                        ->line(new HtmlString("<strong>تفاصيل طلبك المبدئي:</strong>"))
                        ->line(new HtmlString("- رقم الطلب: <strong>{$this->booking->id}</strong>"))
                        ->line(new HtmlString("- الخدمة: <strong>{$serviceName}</strong>"))
                        ->line(new HtmlString("- التاريخ والوقت: <strong>{$bookingDateTime}</strong>"))
                        ->line(new HtmlString("- طريقة الدفع: <strong>{$paymentMethodReadable}</strong>"));

            if ($this->paymentMethod === 'bank_transfer') {
                $mailMessage->line(new HtmlString("لتأكيد حجزك، يرجى القيام بتحويل مبلغ العربون المطلوب. ستجد تفاصيل الحساب البنكي وقيمة العربون عند متابعة حالة الطلب."));
                // يمكنك أيضًا تضمين تفاصيل الحساب البنكي مباشرة هنا إذا كانت ثابتة ومناسبة
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
            Log::warning('BookingRequestReceived (toHttpSms): Recipient mobile number could not be determined for notifiable ID ' . $notifiable->id, ['booking_id' => $this->booking->id]);
            return [];
        }

        // **التعديل الهام: استخدام مفتاح قالب ديناميكي**
        $templateIdentifier = $notifiable->is_admin ? 'booking_request_admin' : 'booking_request_customer';

        $paymentMethodText = match ($this->paymentMethod) {
            'bank_transfer' => 'تحويل بنكي',
            'tamara' => 'تمارا',
            default => Str::title(str_replace('_', ' ', $this->paymentMethod)),
        };

        $specificReplacements = [
            '[payment_method]' => $paymentMethodText,
            // هذا المتغير خاص بالعميل إذا كان الدفع بنكيًا
            '[payment_details_prompt]' => (!$notifiable->is_admin && $this->paymentMethod === 'bank_transfer') ? " يرجى دفع العربون لتأكيد الحجز." : "",
            // يمكنك إضافة المزيد من المتغيرات المشتركة هنا إذا لزم الأمر
            // '[booking_date]' => Carbon::parse($this->booking->booking_datetime)->translatedFormat('d M Y'),
            // '[booking_time]' => Carbon::parse($this->booking->booking_datetime)->translatedFormat('h:i A'),
        ];

        // تم استخدام $templateIdentifier هنا
        $messageContent = $this->getSmsMessageContent($templateIdentifier, $notifiable, $specificReplacements, $this->booking);

        if (empty($messageContent)) {
            // السجل من قبل كان يظهر template_key_used خاطئ بسبب مشكلة في ManagesSmsContent أو NewBookingReceived
            // الآن يجب أن يظهر المفتاح الصحيح المستخدم
            Log::warning("BookingRequestReceived (toHttpSms): SMS message content is empty after processing template for notifiable ID {$notifiable->id}.", [
                'booking_id' => $this->booking->id,
                'template_identifier_used' => $templateIdentifier,
                'is_admin_recipient' => $notifiable->is_admin
            ]);
            // قد ترغب في إرسال رسالة SMS افتراضية هنا كحل احتياطي إذا فشل تحميل القالب
            // if (!$notifiable->is_admin && $templateIdentifier === 'booking_request_customer') {
            //     $messageContent = "طلب حجزك رقم {$this->booking->id} قيد المعالجة. لتفاصيل دفع العربون، يرجى مراجعة بريدك أو حسابك.";
            // } elseif ($notifiable->is_admin && $templateIdentifier === 'booking_request_admin') {
            //     $messageContent = "تنبيه إداري: طلب حجز جديد رقم {$this->booking->id}.";
            // }
        }
        
        // تأكد من أن الرسالة ليست فارغة قبل إرسالها
        if (empty($messageContent)) {
            return [];
        }

        return [
            'to' => $recipientPhoneNumber,
            'content' => $messageContent,
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
            'recipient_type' => $notifiable->is_admin ? 'admin' : 'customer',
            'event' => 'booking_request_received', // اسم مميز لهذا الإشعار
            'payment_method' => $this->paymentMethod,
            'channels_used' => $this->via($notifiable),
        ];
    }
}
