<?php

namespace App\Notifications\Traits;

use App\Models\Booking;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

trait ManagesSmsContent
{
    /**
     * Formats the recipient's phone number to an international format if necessary.
     *
     * @param string|null $number
     * @return string|null
     */
    protected function formatSmsRecipient(?string $number): ?string
    {
        if (empty($number)) {
            return null;
        }

        // إزالة أي أحرف غير رقمية باستثناء علامة + في البداية
        $number = preg_replace('/[^\d+]/', '', $number);
        
        // إزالة أي علامات + داخل الرقم وتركها فقط إذا كانت في البداية
        if (substr_count($number, '+') > 1) {
            $number = '+' . str_replace('+', '', $number);
        } elseif (substr_count($number, '+') == 1 && !str_starts_with($number, '+')) {
             $number = str_replace('+', '', $number); // إزالة الـ + إذا لم تكن في البداية
        }


        // التحقق من الأرقام السعودية وتنسيقها
        if (preg_match('/^(009665|9665|\+9665|05|5)([0-9]{8})$/', $number, $matches)) {
            return '+9665' . $matches[2];
        }
        
        // إذا كان الرقم يبدأ بـ 00 ولكنه ليس 00966، افترض أنه دولي ولكن أزل الـ 00
        if (str_starts_with($number, '00') && !str_starts_with($number, '00966')) {
            $number = '+' . substr($number, 2);
        }
        // إذا كان الرقم يبدأ بـ 0 ولكنه ليس 05، قد يكون رقمًا محليًا لدولة أخرى أو خطأ
        // في هذه الحالة، سنضيف + إذا لم تكن موجودة، بافتراض أنه قد يكون كاملاً
        elseif (str_starts_with($number, '0') && !str_starts_with($number, '05')) {
             $number = '+' . substr($number, 1); // قد تحتاج لمراجعة هذا السلوك
        }


        // التأكد من وجود علامة + في بداية الأرقام الدولية غير السعودية التي تم تحديدها بالفعل
        if (!str_starts_with($number, '+') && strlen($number) > 9 && !preg_match('/^5[0-9]{8}$/', $number) && !preg_match('/^05[0-9]{8}$/', $number) ) {
            $number = '+' . $number;
        }


        // التحقق من صحة أساسية لطول الرقم الدولي (تقديرية)
        if (str_starts_with($number, '+') && (strlen($number) < 10 || strlen($number) > 15)) {
             Log::warning('ManagesSmsContent: Potentially invalid international phone number length.', ['number' => $number]);
            // يمكنك اختيار إرجاع null هنا إذا كان الطول غير صالح بشكل واضح
            // return null;
        }
        
        if (empty($number)){
            return null;
        }

        return $number;
    }

    /**
     * Retrieves and processes the SMS message content from a template.
     *
     * @param string $templateIdentifier Base identifier for the template (e.g., 'payment_success')
     * @param object $notifiable The entity being notified.
     * @param array $additionalReplacements Specific replacements for this notification.
     * @param Booking|null $bookingContext The booking associated with this notification, if any.
     * @return string The processed message content, or an empty string if no suitable message could be generated.
     */
    protected function getSmsMessageContent(string $templateIdentifier, object $notifiable, array $additionalReplacements = [], ?Booking $bookingContext = null): string
    {
        // تحديد سياق الحجز (قد يكون $this->booking أو $this->invoice->booking الخ)
        // من الأفضل تمريره بشكل صريح إذا اختلف بين الإشعارات
        $currentBooking = $bookingContext;
        if (!$currentBooking && isset($this->booking) && $this->booking instanceof Booking) {
            $currentBooking = $this->booking;
        } elseif (!$currentBooking && isset($this->invoice) && isset($this->invoice->booking) && $this->invoice->booking instanceof Booking) {
            $currentBooking = $this->invoice->booking;
        }

        $templateKey = $notifiable->is_admin ? $templateIdentifier . '_admin' : $templateIdentifier . '_customer';

        $template = Cache::rememberForever('sms_template_active_' . $templateKey, function () use ($templateKey) {
            return SmsTemplate::where('notification_type', $templateKey)->where('is_active', true)->first();
        });

        $message = '';
        $logContext = [
            'booking_id' => $currentBooking->id ?? 'N/A',
            'invoice_id' => $this->invoice->id ?? 'N/A', // إذا كان الإشعار يحتوي على فاتورة
            'template_key_used' => $templateKey,
        ];

        if ($template && !empty(trim($template->template_content))) {
            $message = $template->template_content;
            $logContext['template_source'] = 'database';
        } else {
            Log::warning("ManagesSmsContent: SMS template '{$templateKey}' not found, not active, or empty in DB. Check default messages or skip.", $logContext);
            // يمكنك تحديد رسائل افتراضية أكثر تحديدًا هنا لكل نوع إشعار إذا أردت
            // أو تركها فارغة ليتم التعامل معها في دالة toSmsGateway
            // مثال لرسالة افتراضية عامة جدًا
            $defaultBase = "تنبيه من المصورة فاطمة";
            if ($currentBooking) {
                $defaultBase .= " بخصوص حجزك رقم [booking_id]";
            }
            $message = $defaultBase . ". يرجى مراجعة حسابك لمزيد من التفاصيل."; // رسالة افتراضية جداً
            $logContext['template_source'] = 'default_generic';
        }

        // المتغيرات الأساسية التي قد تحتاجها معظم الإشعارات
        $baseReplacements = [
            '[photographer_name]' => config('app.photographer_name', 'المصورة فاطمة'),
            '[customer_name]' => $currentBooking->user->name ?? ($notifiable->name ?? 'العميل'),
            '[customer_name_short]' => $currentBooking->user ? Str::limit($currentBooking->user->name, 10) : ($notifiable->name ? Str::limit($notifiable->name, 10) : "عميل"),
            '[booking_id]' => $currentBooking->id ?? 'غير متوفر',
            '[service_name]' => $currentBooking && $currentBooking->service ? Str::limit($currentBooking->service->name_ar, 20) : 'الخدمة',
            '[service_name_short]' => $currentBooking && $currentBooking->service ? Str::limit($currentBooking->service->name_ar, 12, '') : "تصوير",
            '[booking_date]' => isset($currentBooking->booking_datetime) ? Carbon::parse($currentBooking->booking_datetime)->translatedFormat('d M') : '-',
            '[booking_time]' => isset($currentBooking->booking_datetime) ? Carbon::parse($currentBooking->booking_datetime)->translatedFormat('h:i A') : '-',
            '[booking_date_time_short]' => isset($currentBooking->booking_datetime) ? Carbon::parse($currentBooking->booking_datetime)->translatedFormat('d-m H:i') : '-',
        ];

        $allReplacements = array_merge($baseReplacements, $additionalReplacements);
        $processedMessage = str_replace(array_keys($allReplacements), array_values($allReplacements), $message);

        // إزالة أي متغيرات متبقية لم يتم استبدالها (تلك التي بين أقواس مربعة)
        $finalMessage = preg_replace('/\[[^\]]+\]/', '', $processedMessage);
        // إزالة المسافات الزائدة
        $finalMessage = trim(preg_replace('/\s+/', ' ', $finalMessage));
        
        $logContext['final_content_preview'] = Str::limit($finalMessage, 50);
        Log::debug("ManagesSmsContent: SMS content processed.", $logContext);

        return $finalMessage;
    }
}
