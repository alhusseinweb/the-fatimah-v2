<?php

namespace App\Notifications\Traits;

use App\Models\Booking;
use App\Models\SmsTemplate;
use App\Models\Setting;
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
            Log::warning('ManagesSmsContent - formatSmsRecipient: Input number is empty.');
            return null;
        }

        $originalNumber = $number; // للاحتفاظ بالرقم الأصلي للتسجيل
        // إزالة كل ما هو ليس أرقام أو علامة +
        $number = preg_replace('/[^\d+]/', '', $number);
        
        // معالجة وجود أكثر من علامة + أو وجودها في غير مكانها
        if (substr_count($number, '+') > 1) {
            $number = '+' . str_replace('+', '', $number); // إبقاء أول + وإزالة البقية
        } elseif (substr_count($number, '+') == 1 && !str_starts_with($number, '+')) {
            $number = str_replace('+', '', $number); // إزالة + إذا لم تكن في البداية
        }

        // القاعدة الأساسية لأرقام السعودية
        if (preg_match('/^(009665|9665|\+9665|05|5)([0-9]{8})$/', $number, $matches)) {
            $formattedNumber = '+9665' . $matches[2];
            Log::info("ManagesSmsContent - formatSmsRecipient: Formatted Saudi Number. Original='{$originalNumber}', Formatted='{$formattedNumber}'.");
            return $formattedNumber;
        }
        
        // محاولات تنسيق إضافية إذا لم تتطابق القاعدة السعودية المباشرة
        if (str_starts_with($number, '00') && !str_starts_with($number, '00966')) { // مثل 0020...
             $number = '+' . substr($number, 2);
        } elseif (str_starts_with($number, '0') && strlen($number) > 1 && !str_starts_with($number, '05')) { // مثل 01xxxxxxxxx (رقم أرضي قد يحتاج لرمز دولة)
             // هذه القاعدة قد تحتاج إلى تعديل بناءً على الدولة المستهدفة إذا لم تكن السعودية فقط
             // حاليًا، سنفترض أنها قد تحتاج إلى رمز دولة إذا لم تكن رقم جوال سعودي معروف
             // إذا كنت تتعامل فقط مع أرقام سعودية، قد لا تحتاج هذه القاعدة
             Log::warning("ManagesSmsContent - formatSmsRecipient: Number starts with 0 but not 05, international code might be missing.", ['number' => $originalNumber, 'current_format' => $number]);
             // قد ترغب في إرجاع null هنا أو الرقم كما هو مع تحذير
        }

        // إذا لم يبدأ بـ + ولكن يمكن أن يكون رقمًا دوليًا مكتملًا (بدون +) أو رقم محلي يتطلب رمز الدولة
        if (!str_starts_with($number, '+') && strlen($number) > 9 && !preg_match('/^5[0-9]{8}$/', $number) && !preg_match('/^05[0-9]{8}$/', $number) ) {
             // هذه محاولة عامة، قد لا تكون دقيقة لجميع الحالات
             // $number = '+' . $number; // قم بتفعيل هذا بحذر
             Log::warning("ManagesSmsContent - formatSmsRecipient: Number does not start with '+' and is not a clear Saudi mobile format. Review needed.", ['number' => $originalNumber, 'current_format' => $number]);
        }
        
        // التحقق النهائي من الطول للأرقام الدولية
        if (str_starts_with($number, '+') && (strlen($number) < 11 || strlen($number) > 16)) { // مدى معقول للأرقام الدولية
             Log::warning('ManagesSmsContent - formatSmsRecipient: Potentially invalid international phone number length after formatting.', ['original' => $originalNumber, 'formatted_number' => $number]);
        }
        
        if (empty($number)){
             Log::warning("ManagesSmsContent - formatSmsRecipient: Number became empty after formatting.", ['original' => $originalNumber]);
            return null;
        }

        // إذا لم يتم تطبيق أي من القواعد السعودية المحددة، ولم يبدأ بـ +، ولم يكن فارغًا، قد يكون رقمًا محليًا أو دوليًا غير مكتمل
        // إذا كان الهدف الأساسي هو السعودية، يمكنك إضافة تحذير هنا إذا لم يتم تنسيقه كـ +966...
        if (!str_starts_with($number, '+966') && (str_starts_with($number, '5') && strlen($number) == 9)) { // رقم سعودي بدون 0 أو رمز دولة
            $formattedNumber = '+966' . $number;
            Log::info("ManagesSmsContent - formatSmsRecipient: Formatted Saudi Number (missing 0 and country code). Original='{$originalNumber}', Formatted='{$formattedNumber}'.");
            return $formattedNumber;
        }


        Log::info("ManagesSmsContent - formatSmsRecipient: Number after all checks. Original='{$originalNumber}', Final Used='{$number}'. Review if not in expected international format.");
        return $number;
    }

    /**
     * Retrieves and processes the SMS message content from a template.
     *
     * @param string $templateIdentifier The final identifier for the template (e.g., 'booking_request_customer')
     * @param object $notifiable The entity being notified.
     * @param array $additionalReplacements Specific replacements for this notification.
     * @param Booking|null $bookingContext The booking associated with this notification, if any.
     * @return string The processed message content, or an empty string if no suitable message could be generated.
     */
    protected function getSmsMessageContent(string $templateIdentifier, object $notifiable, array $additionalReplacements = [], ?Booking $bookingContext = null): string
    {
        $currentBooking = $bookingContext;
        // محاولة الحصول على كائن الحجز إذا لم يتم تمريره بشكل مباشر
        if (!$currentBooking && isset($this->booking) && $this->booking instanceof Booking) {
            $currentBooking = $this->booking;
        } elseif (!$currentBooking && isset($this->invoice) && $this->invoice instanceof Invoice && $this->invoice->booking instanceof Booking) { // التأكد من أن invoice و booking كائنات صالحة
            $currentBooking = $this->invoice->booking;
        }

        $logContextBase = [
            'notification_class' => class_basename($this), // اسم كلاس الإشعار الذي يستدعي هذا الـ Trait
            'booking_id' => $currentBooking?->id ?? 'N/A',
            'notifiable_id' => $notifiable->id ?? 'N/A',
            'notifiable_type' => get_class($notifiable),
            'template_identifier_searched' => $templateIdentifier,
        ];

        Log::debug("ManagesSmsContent: Getting SMS content.", $logContextBase + ['specific_replacements_passed_to_trait' => $additionalReplacements]);

        // استخدام مفتاح كاش متسق مع ما يتم مسحه في SmsTemplateController
        $cacheKey = 'sms_template_content_' . $templateIdentifier; 
        
        // جلب القالب. في بيئة الإنتاج، يفضل تفعيل الكاش.
        // للاختبار، يمكنك تعطيل الكاش مؤقتًا للتأكد من أنك دائمًا تجلب أحدث نسخة.
        // $templateModel = SmsTemplate::where('notification_type', $templateIdentifier)
        //                           ->where('is_active', true)
        //                           ->first();
        // Log::debug("ManagesSmsContent: Fetched template directly from DB (cache temporarily bypassed for testing).", $logContextBase + ['found' => !is_null($templateModel)]);
        
        // --- استخدام الكاش (يجب إعادة تفعيله في الإنتاج) ---
        $templateModel = Cache::remember($cacheKey, now()->addMinutes(config('cache.sms_template_duration', 60)), function () use ($templateIdentifier, $logContextBase) {
            Log::debug("ManagesSmsContent: Cache miss for '{$templateIdentifier}'. Fetching from DB.", $logContextBase);
            return SmsTemplate::where('notification_type', $templateIdentifier)
                              ->where('is_active', true)
                              ->first();
        });
        // --- نهاية استخدام الكاش ---

        $message = '';

        if ($templateModel && !empty(trim($templateModel->template_content))) {
            $message = $templateModel->template_content;
            Log::info("ManagesSmsContent: Template '{$templateIdentifier}' loaded from " . (Cache::has($cacheKey) && config('cache.default') !== 'array' ? "cache" : "DB") . ".", $logContextBase + ['template_id' => $templateModel->id]);
            Log::debug("ManagesSmsContent: Original template content:", $logContextBase + ['content' => $message]);
        } else {
            Log::error("ManagesSmsContent: SMS Template '{$templateIdentifier}' NOT FOUND, inactive, or empty. A default message might be used if defined below.", $logContextBase);
            // يمكنك تحديد رسالة افتراضية عامة جدًا هنا، أو تركها فارغة ليعتمد على كلاس الإشعار نفسه
            // $message = "رسالة تلقائية بخصوص طلبكم."; 
            // من الأفضل أن يقوم كل كلاس إشعار بتعريف رسالته الافتراضية إذا فشل القالب
            return ''; // إرجاع فارغ إذا لم يتم العثور على القالب ليتم التعامل معه في كلاس الإشعار
        }

        // بناء مصفوفة الاستبدالات الأساسية
        $baseReplacements = [];
        $baseReplacements['[photographer_name]'] = config('app.photographer_name', Setting::where('key', 'site_name_ar')->value('value') ?? 'المصورة فاطمة'); // اسم المصورة من الإعدادات أو قيمة افتراضية
        
        // اسم المستلم العام (الذي يتلقى الإشعار)
        if (isset($notifiable->name)) {
            $baseReplacements['[user_name]'] = $notifiable->name; // اسم المستلم (قد يكون المدير أو العميل)
        }

        // اسم العميل: دائمًا من كائن الحجز إذا كان متاحًا، وإلا من المستلم إذا لم يكن مديرًا
        if ($currentBooking && $currentBooking->user && isset($currentBooking->user->name)) {
            $baseReplacements['[customer_name]'] = $currentBooking->user->name;
            $baseReplacements['[customer_name_short]'] = Str::limit($currentBooking->user->name, 10, '');
        } elseif (isset($notifiable->name) && !($notifiable->is_admin ?? false)) {
            $baseReplacements['[customer_name]'] = $notifiable->name;
            $baseReplacements['[customer_name_short]'] = Str::limit($notifiable->name, 10, '');
        } else {
            $baseReplacements['[customer_name]'] = 'العميل'; // قيمة افتراضية أكثر عمومية
            $baseReplacements['[customer_name_short]'] = 'عميل';
        }

        if ($currentBooking) {
            $baseReplacements['[booking_id]'] = $currentBooking->id;
            $baseReplacements['[event_location]'] = $currentBooking->event_location ?? '-';
            
            if ($currentBooking->service) {
                $serviceName = $currentBooking->service->name_ar ?? $currentBooking->service->name_en ?? 'الخدمة';
                $baseReplacements['[service_name]'] = $serviceName;
                $baseReplacements['[service_name_short]'] = Str::limit($serviceName, 15, '');
            } else {
                $baseReplacements['[service_name]'] = 'الخدمة المختارة';
                $baseReplacements['[service_name_short]'] = 'خدمة';
            }

            if ($currentBooking->booking_datetime instanceof Carbon) {
                // هذه ستكون القيم الافتراضية إذا لم يتم تمريرها في $additionalReplacements
                $baseReplacements['[booking_date]'] = $currentBooking->booking_datetime->translatedFormat('Y/m/d');
                $baseReplacements['[booking_time]'] = $currentBooking->booking_datetime->translatedFormat('h:ia'); // استخدام h:ia للتوقيت صباحًا/مساءً
                $baseReplacements['[booking_date_time]'] = $currentBooking->booking_datetime->translatedFormat('Y/m/d h:ia');
                $baseReplacements['[booking_day_name]'] = $currentBooking->booking_datetime->translatedFormat('l');
            } else {
                $baseReplacements['[booking_date]'] = '-';
                $baseReplacements['[booking_time]'] = '-';
                $baseReplacements['[booking_date_time]'] = '-';
                $baseReplacements['[booking_day_name]'] = '-';
                Log::warning("ManagesSmsContent: booking_datetime is not a Carbon instance or is null for template '{$templateIdentifier}'.", $logContextBase + ['booking_datetime_value' => $currentBooking->booking_datetime ?? 'null']);
            }
            $baseReplacements['[booking_status_label]'] = $currentBooking->status_label ?? $currentBooking->status ?? '-';

            if($currentBooking->invoice){
                $baseReplacements['[invoice_number]'] = $currentBooking->invoice->invoice_number;
                // استخدام number_format مع صفر للكسور للرسائل النصية
                $baseReplacements['[invoice_total_amount]'] = number_format((float)($currentBooking->invoice->amount ?? 0), 0); 
                $baseReplacements['[invoice_status]'] = $currentBooking->invoice->status_label ?? $currentBooking->invoice->status ?? '-';
            } else {
                $baseReplacements['[invoice_number]'] = '-';
                $baseReplacements['[invoice_total_amount]'] = '-';
                $baseReplacements['[invoice_status]'] = '-';
            }
        } else {
            Log::warning("ManagesSmsContent: No \$currentBooking object available for template '{$templateIdentifier}'. Some placeholders may not be replaced.", $logContextBase);
            $placeholdersToEmpty = ['[booking_id]', '[service_name]', '[service_name_short]', '[event_location]', '[booking_date]', '[booking_time]', '[booking_date_time]', '[booking_day_name]', '[booking_status_label]', '[invoice_number]', '[invoice_total_amount]', '[invoice_status]'];
            foreach($placeholdersToEmpty as $ph) {
                if(!isset($baseReplacements[$ph])) $baseReplacements[$ph] = ''; // أضف فقط إذا لم يكن موجودًا بالفعل
            }
        }

        // دمج المتغيرات الأساسية مع الممررة، مع إعطاء الأولوية للمتغيرات الممررة من كلاس الإشعار
        $allReplacements = array_merge($baseReplacements, $additionalReplacements);
        
        Log::debug("ManagesSmsContent: All replacements to be applied for '{$templateIdentifier}':", $logContextBase + ['all_replacements_map' => $allReplacements]);

        $processedMessage = $message;
        foreach ($allReplacements as $placeholder => $value) {
            $count = 0; 
            $processedMessage = str_replace((string)$placeholder, (string)$value, $processedMessage, $count);
            if ($count > 0) {
                // Log::debug("ManagesSmsContent: Replaced '{$placeholder}' with '{$value}' ({$count} time(s)).", $logContextBase);
            } elseif (Str::startsWith((string)$placeholder, '[booking_date') || Str::startsWith((string)$placeholder, '[booking_time') || $placeholder === '[customer_name]') { 
                 Log::warning("ManagesSmsContent: Placeholder '{$placeholder}' NOT FOUND in template content for '{$templateIdentifier}'.", $logContextBase + ['template_content' => $message]);
            }
        }
        
        // إزالة المسافات المتعددة واستبدالها بمسافة واحدة، وإزالة المسافات من البداية والنهاية
        $finalMessage = trim(preg_replace('/\s+/', ' ', $processedMessage));
        
        Log::info("ManagesSmsContent: Final SMS content for '{$templateIdentifier}'.", $logContextBase + ['final_content_preview' => Str::limit($finalMessage, 100)]);

        return $finalMessage;
    }
}
