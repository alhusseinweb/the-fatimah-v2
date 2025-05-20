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

        $number = preg_replace('/[^\d+]/', '', $number);
        
        if (substr_count($number, '+') > 1) {
            $number = '+' . str_replace('+', '', $number);
        } elseif (substr_count($number, '+') == 1 && !str_starts_with($number, '+')) {
            $number = str_replace('+', '', $number);
        }

        if (preg_match('/^(009665|9665|\+9665|05|5)([0-9]{8})$/', $number, $matches)) {
            return '+9665' . $matches[2];
        }
        
        if (str_starts_with($number, '00') && !str_starts_with($number, '00966')) {
            $number = '+' . substr($number, 2);
        }
        elseif (str_starts_with($number, '0') && !str_starts_with($number, '05')) {
            $number = '+' . substr($number, 1);
        }

        if (!str_starts_with($number, '+') && strlen($number) > 9 && !preg_match('/^5[0-9]{8}$/', $number) && !preg_match('/^05[0-9]{8}$/', $number) ) {
            $number = '+' . $number;
        }

        if (str_starts_with($number, '+') && (strlen($number) < 10 || strlen($number) > 15)) {
            Log::warning('ManagesSmsContent: Potentially invalid international phone number length.', ['number' => $number]);
        }
        
        if (empty($number)){
            return null;
        }

        return $number;
    }

    /**
     * Retrieves and processes the SMS message content from a template.
     *
     * @param string $templateIdentifier The final identifier for the template (e.g., 'booking_request_customer' or 'booking_request_admin')
     * @param object $notifiable The entity being notified.
     * @param array $additionalReplacements Specific replacements for this notification.
     * @param Booking|null $bookingContext The booking associated with this notification, if any.
     * @return string The processed message content, or an empty string if no suitable message could be generated.
     */
    protected function getSmsMessageContent(string $templateIdentifier, object $notifiable, array $additionalReplacements = [], ?Booking $bookingContext = null): string
    {
        $currentBooking = $bookingContext;
        if (!$currentBooking && isset($this->booking) && $this->booking instanceof Booking) {
            $currentBooking = $this->booking;
        } elseif (!$currentBooking && isset($this->invoice) && isset($this->invoice->booking) && $this->invoice->booking instanceof Booking) {
            $currentBooking = $this->invoice->booking;
        }

        // --- START: التعديل الرئيسي هنا ---
        // $templateIdentifier الذي تم تمريره هو المفتاح الصحيح والمكتمل. لا حاجة لإضافة لواحق.
        $templateKeyToSearchInDb = $templateIdentifier;
        // --- END: التعديل الرئيسي هنا ---

        // يمكنك استخدام الكاش هنا مع $templateKeyToSearchInDb
        // ملاحظة: Cache::rememberForever قد لا يكون مناسبًا إذا كنت تريد تحديث القوالب بشكل متكرر دون مسح الكاش يدويًا.
        // فكر في استخدام Cache::remember مع مدة صلاحية أقصر.
        $template = Cache::remember('sms_template_active_' . $templateKeyToSearchInDb, now()->addMinutes(60), function () use ($templateKeyToSearchInDb) {
            return SmsTemplate::where('notification_type', $templateKeyToSearchInDb) // <-- استخدام المفتاح الصحيح للبحث
                              ->where('is_active', true)
                              ->first();
        });

        $message = '';
        $logContext = [
            'booking_id' => $currentBooking->id ?? 'N/A',
            'invoice_id' => isset($this->invoice) && $this->invoice ? $this->invoice->id : 'N/A',
            'template_key_used_for_search' => $templateKeyToSearchInDb, // للتسجيل والتحقق
        ];

        if ($template && !empty(trim($template->template_content))) {
            $message = $template->template_content;
            $logContext['template_source'] = 'database';
        } else {
            Log::warning("ManagesSmsContent: SMS template '{$templateKeyToSearchInDb}' not found, not active, or empty in DB. Using default message or skipping.", $logContext);
            $defaultBase = "تنبيه من المصورة فاطمة";
            if ($currentBooking) {
                $defaultBase .= " بخصوص حجزك رقم [booking_id]";
            }
            $message = $defaultBase . ". يرجى مراجعة حسابك لمزيد من التفاصيل.";
            $logContext['template_source'] = 'default_generic';
        }

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

        $finalMessage = preg_replace('/\[[^\]]+\]/', '', $processedMessage);
        $finalMessage = trim(preg_replace('/\s+/', ' ', $finalMessage));
        
        $logContext['final_content_preview'] = Str::limit($finalMessage, 50);
        Log::debug("ManagesSmsContent: SMS content processed.", $logContext);

        return $finalMessage;
    }
}
