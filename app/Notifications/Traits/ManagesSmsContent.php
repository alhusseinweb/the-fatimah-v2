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
     * Formats the recipient's phone number.
     */
    protected function formatSmsRecipient(?string $number): ?string
    {
        if (empty($number)) {
            return null;
        }
        // ... (منطق تنسيق رقم الهاتف كما هو لديك)
        // للتأكد من أنه يعمل بشكل صحيح، يمكنك إضافة تسجيل هنا:
        // Log::debug("formatSmsRecipient: Input='{$number}', Output='{$formatted_number_after_logic}'");
        // return $formatted_number_after_logic;
        // الكود الحالي يبدو معقولاً، لكن اختبره إذا كنت تشك فيه.
        $originalNumber = $number; // للاحتفاظ بالرقم الأصلي للتسجيل
        $number = preg_replace('/[^\d+]/', '', $number);
        
        if (substr_count($number, '+') > 1) {
            $number = '+' . str_replace('+', '', $number);
        } elseif (substr_count($number, '+') == 1 && !str_starts_with($number, '+')) {
            $number = str_replace('+', '', $number);
        }

        if (preg_match('/^(009665|9665|\+9665|05|5)([0-9]{8})$/', $number, $matches)) {
            $formattedNumber = '+9665' . $matches[2];
            Log::info("Formatted SMS Recipient: Original='{$originalNumber}', Formatted='{$formattedNumber}' using Saudi rule.");
            return $formattedNumber;
        }
        
        // قواعد إضافية إذا لم يتطابق مع القاعدة السعودية المباشرة
        if (str_starts_with($number, '00') && !str_starts_with($number, '00966')) {
             $number = '+' . substr($number, 2);
        } elseif (str_starts_with($number, '0') && !str_starts_with($number, '05')) { // تأكد أن هذه لا تتعارض مع 05 السعودية
            // هذه القاعدة قد تكون واسعة جدًا، قد تحتاج لتخصيصها إذا كانت الأرقام غير السعودية تتطلب معاملة خاصة
             $number = '+' . substr($number, 1); 
        }

        // إذا لم يبدأ بـ + ولكن يمكن أن يكون رقمًا دوليًا
        if (!str_starts_with($number, '+') && strlen($number) > 9 && !preg_match('/^5[0-9]{8}$/', $number) && !preg_match('/^05[0-9]{8}$/', $number) ) {
             $number = '+' . $number;
        }
        
        // التحقق النهائي من الطول بعد التنسيق
        if (str_starts_with($number, '+') && (strlen($number) < 10 || strlen($number) > 16)) { // زدت الحد الأقصى قليلاً
             Log::warning('ManagesSmsContent: Potentially invalid international phone number length after formatting.', ['original' => $originalNumber, 'formatted_number' => $number]);
        }
        
        if (empty($number)){
             Log::warning("ManagesSmsContent: Number became empty after formatting.", ['original' => $originalNumber]);
            return null;
        }
        Log::info("Formatted SMS Recipient (Final Fallback): Original='{$originalNumber}', Formatted='{$number}'");
        return $number;
    }

    protected function getSmsMessageContent(string $templateIdentifier, object $notifiable, array $additionalReplacements = [], ?Booking $bookingContext = null): string
    {
        $currentBooking = $bookingContext;
        if (!$currentBooking && isset($this->booking) && $this->booking instanceof Booking) {
            $currentBooking = $this->booking;
        } elseif (!$currentBooking && isset($this->invoice) && isset($this->invoice->booking) && $this->invoice->booking instanceof Booking) {
            $currentBooking = $this->invoice->booking;
        }

        $logContextBase = [
            'notification_class' => class_basename($this),
            'booking_id' => $currentBooking?->id ?? 'N/A',
            'notifiable_id' => $notifiable->id ?? 'N/A',
            'template_identifier_searched' => $templateIdentifier,
        ];

        Log::debug("ManagesSmsContent: Getting SMS content.", $logContextBase + ['specific_replacements_passed' => $additionalReplacements]);

        // استخدام مفتاح كاش متسق مع ما قد يتم مسحه في SmsTemplateController
        $cacheKey = 'sms_template_content_' . $templateIdentifier; 
        
        // --- تعطيل الكاش مؤقتًا للاختبار ---
        // Log::debug("ManagesSmsContent: Attempting to get template from DB (cache disabled for test).", $logContextBase);
        // $templateModel = SmsTemplate::where('notification_type', $templateIdentifier)
        //                           ->where('is_active', true)
        //                           ->first();
        // --- نهاية تعطيل الكاش ---

        // --- استخدام الكاش (أعد تفعيله بعد الاختبار) ---
        $templateModel = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($templateIdentifier, $logContextBase) {
            Log::debug("ManagesSmsContent: Cache miss for '{$templateIdentifier}'. Fetching from DB.", $logContextBase);
            return SmsTemplate::where('notification_type', $templateIdentifier)
                              ->where('is_active', true)
                              ->first();
        });
        // --- نهاية استخدام الكاش ---


        $message = '';

        if ($templateModel && !empty(trim($templateModel->template_content))) {
            $message = $templateModel->template_content;
            Log::info("ManagesSmsContent: Template '{$templateIdentifier}' loaded from " . (Cache::has($cacheKey) ? "cache" : "DB") . ".", $logContextBase + ['template_id' => $templateModel->id]);
            Log::debug("ManagesSmsContent: Original template content:", $logContextBase + ['content' => $message]);
        } else {
            Log::error("ManagesSmsContent: SMS Template '{$templateIdentifier}' NOT FOUND, inactive, or empty. Defaulting message.", $logContextBase);
            // رسالة افتراضية إذا لم يتم العثور على القالب
            $defaultBase = "تنبيه من المصورة فاطمة";
            if ($currentBooking) {
                $defaultBase .= " بخصوص حجزك رقم " . ($currentBooking->id ?? '');
            }
            $message = $defaultBase . ". يرجى مراجعة حسابك أو البريد الإلكتروني لمزيد من التفاصيل.";
            // لا تقم بإرجاع سلسلة فارغة هنا مباشرة، دع عملية الاستبدال تحاول العمل على الرسالة الافتراضية
        }

        // بناء مصفوفة الاستبدالات الأساسية
        $baseReplacements = [];
        $baseReplacements['[photographer_name]'] = config('app.photographer_name', 'المصورة فاطمة');
        
        if (isset($notifiable->name)) {
            $baseReplacements['[customer_name]'] = $notifiable->name;
            $baseReplacements['[user_name]'] = $notifiable->name; // اسم عام
            $baseReplacements['[customer_name_short]'] = Str::limit($notifiable->name, 10, '');
        } else {
            $baseReplacements['[customer_name]'] = 'عميلنا العزيز';
            $baseReplacements['[user_name]'] = 'المستخدم';
            $baseReplacements['[customer_name_short]'] = 'عميلنا';
        }

        if ($currentBooking) {
            $baseReplacements['[booking_id]'] = $currentBooking->id;
            $baseReplacements['[event_location]'] = $currentBooking->event_location ?? '-';
            
            if ($currentBooking->service) {
                $serviceName = $currentBooking->service->name_ar ?? $currentBooking->service->name_en ?? 'الخدمة';
                $baseReplacements['[service_name]'] = $serviceName;
                $baseReplacements['[service_name_short]'] = Str::limit($serviceName, 15, ''); // اسم خدمة مختصر
            } else {
                $baseReplacements['[service_name]'] = 'الخدمة المختارة';
                $baseReplacements['[service_name_short]'] = 'خدمة';
            }

            if ($currentBooking->booking_datetime instanceof Carbon) {
                $baseReplacements['[booking_date]'] = $currentBooking->booking_datetime->translatedFormat('Y/m/d');
                $baseReplacements['[booking_time]'] = $currentBooking->booking_datetime->translatedFormat('h:ia');
                $baseReplacements['[booking_date_time]'] = $currentBooking->booking_datetime->translatedFormat('Y/m/d h:ia');
                // يمكنك إضافة تنسيقات أخرى إذا لزم الأمر
                $baseReplacements['[booking_day_name]'] = $currentBooking->booking_datetime->translatedFormat('l'); // اسم اليوم
            } else {
                // إذا لم يكن booking_datetime موجودًا أو ليس Carbon، ضع قيمًا افتراضية أو فارغة
                $baseReplacements['[booking_date]'] = '-';
                $baseReplacements['[booking_time]'] = '-';
                $baseReplacements['[booking_date_time]'] = '-';
                $baseReplacements['[booking_day_name]'] = '-';
                Log::warning("ManagesSmsContent: booking_datetime is not a Carbon instance or is null for template '{$templateIdentifier}'.", $logContextBase + ['booking_datetime_value' => $currentBooking->booking_datetime ?? 'null']);
            }
            // أضف حالة الحجز
            $baseReplacements['[booking_status_label]'] = $currentBooking->status_label ?? $currentBooking->status ?? '-';
        } else {
            // قيم افتراضية إذا لم يكن هناك حجز مرتبط (قد لا يكون هذا السيناريو شائعًا للإشعارات التي تعتمد على الحجز)
            Log::warning("ManagesSmsContent: No \$currentBooking object available for template '{$templateIdentifier}'. Some placeholders may not be replaced.", $logContextBase);
            $placeholdersToEmpty = ['[booking_id]', '[service_name]', '[service_name_short]', '[event_location]', '[booking_date]', '[booking_time]', '[booking_date_time]', '[booking_day_name]', '[booking_status_label]'];
            foreach($placeholdersToEmpty as $ph) $baseReplacements[$ph] = '';
        }

        // دمج المتغيرات الأساسية مع الممررة، مع إعطاء الأولوية للمتغيرات الممررة
        $allReplacements = array_merge($baseReplacements, $additionalReplacements);
        
        Log::debug("ManagesSmsContent: Preparing to replace placeholders for '{$templateIdentifier}'.", $logContextBase + ['all_replacements_map' => $allReplacements]);

        $processedMessage = $message; // ابدأ بمحتوى القالب (أو الرسالة الافتراضية)
        foreach ($allReplacements as $placeholder => $value) {
            $count = 0; // لتتبع عدد مرات الاستبدال
            $processedMessage = str_replace($placeholder, (string)$value, $processedMessage, $count);
            if ($count > 0) {
                Log::debug("ManagesSmsContent: Replaced '{$placeholder}' with '{$value}' ({$count} time(s)).", $logContextBase);
            } elseif (Str::startsWith($placeholder, '[booking_date') || Str::startsWith($placeholder, '[booking_time')) { 
                // سجل فقط إذا لم يتم العثور على متغيرات التاريخ والوقت المهمة
                 Log::warning("ManagesSmsContent: Placeholder '{$placeholder}' NOT FOUND in template or default message for '{$templateIdentifier}'.", $logContextBase);
            }
        }
        
        // إزالة أي متغيرات متبقية لم يتم استبدالها (اختياري)
        // $finalMessage = preg_replace('/\[[^\]]+\]/', '', $processedMessage);
        $finalMessage = $processedMessage; // إذا كنت تفضل رؤية المتغيرات غير المستبدلة في الرسالة للاختبار
        
        $finalMessage = trim(preg_replace('/\s+/', ' ', $finalMessage)); // إزالة المسافات الزائدة
        
        Log::info("ManagesSmsContent: Final SMS content for '{$templateIdentifier}'.", $logContextBase + ['final_content_preview' => Str::limit($finalMessage, 100)]);

        return $finalMessage;
    }
}
