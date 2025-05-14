<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class SmsLimitReachedNotification extends Notification implements ShouldQueue // يجب أن يكون ShouldQueue إذا أردت معالجته في الطابور
{
    use Queueable;

    public int $limit;
    public int $currentCount;

    public function __construct(int $limit, int $currentCount)
    {
        $this->limit = $limit;
        $this->currentCount = $currentCount;
    }

    public function via(object $notifiable): array
    {
        return ['mail']; // إرسال بريد إلكتروني فقط
    }

    public function toMail(object $notifiable): MailMessage
    {
        $monthName = Carbon::now()->translatedFormat('F Y'); // اسم الشهر والسنة

        return (new MailMessage)
                    ->subject('تنبيه: تم تجاوز الحد الشهري للرسائل النصية القصيرة (SMS)')
                    ->greeting('مرحباً أيها المدير،')
                    ->error() // لجعل البريد يظهر كخطأ أو تحذير هام
                    ->line("نود إعلامك بأنه تم الوصول إلى أو تجاوز الحد الشهري المسموح به لإرسال رسائل SMS لشهر {$monthName}.")
                    ->line("الحد الشهري المحدد: **{$this->limit} رسالة**.")
                    ->line("العدد المرسل حتى الآن هذا الشهر: **{$this->currentCount} رسالة**.")
                    ->lineIf(filter_var(optional(\App\Models\Setting::where('key', 'sms_stop_sending_on_limit')->first())->value ?? false, FILTER_VALIDATE_BOOLEAN), 'تم إيقاف إرسال رسائل SMS جديدة تلقائياً بناءً على الإعدادات الحالية.')
                    ->line('يرجى مراجعة إعدادات حدود الرسائل في لوحة التحكم أو زيادة الرصيد لدى مزود الخدمة إذا لزم الأمر.')
                    ->action('مراجعة إعدادات الرسائل', route('admin.settings.edit')) // أو رابط صفحة قوالب SMS إذا كانت الإعدادات هناك
                    ->salutation('مع التحية، نظام ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sms_limit' => $this->limit,
            'sms_current_count' => $this->currentCount,
            'message' => "SMS monthly limit of {$this->limit} has been reached or exceeded (Current: {$this->currentCount}).",
        ];
    }
}