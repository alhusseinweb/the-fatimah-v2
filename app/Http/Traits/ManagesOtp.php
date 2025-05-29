<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting; // لاستيراد إعدادات مزود الخدمة
use App\Notifications\SendOtpNotification; // إشعار OTP الخاص بنا
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Channels\TwilioSmsChannel;    // افترض أن هذا هو مسار قناتك لـ Twilio
use App\Notifications\Channels\SmsGatewayAppChannel; // افترض أن هذا هو مسار قناتك لبوابة الأندرويد
use Illuminate\Support\Facades\Notification as LaravelNotification;

trait ManagesOtp
{
    protected function generateAndSendOtp(string $mobileNumber, string $purpose = 'verification'): ?string
    {
        $otpCode = (string) random_int(1000, 9999);
        $validityMinutes = (int) config('auth.otp_validity_minutes', 5);

        // تطبيع رقم الجوال (يمكن تحسين هذا لاحقًا إذا لزم الأمر)
        // $normalizedMobile = $this->normalizeMobileForCache($mobileNumber);
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber);


        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
        Cache::put($cacheKey, $otpCode, now()->addMinutes($validityMinutes));

        Log::info("Generated OTP {$otpCode} for {$normalizedMobile} (purpose: {$purpose}). Stored in cache [{$cacheKey}] for {$validityMinutes} minutes.");

        // --- اختيار قناة الإرسال بناءً على إعدادات المدير ---
        $otpProviderKey = Setting::where('key', 'sms_otp_provider')->value('value');
        if(empty($otpProviderKey)) { // إذا لم يتم تعيين قيمة، استخدم قيمة افتراضية أو سجل خطأ
             $otpProviderKey = 'httpsms'; // أو 'none' إذا كنت تريد تعطيل الإرسال كافتراضي
             Log::warning("OTP Provider not set in settings, defaulting to '{$otpProviderKey}'. Please configure 'sms_otp_provider'.");
        }


        $channelToUse = null;
        switch ($otpProviderKey) {
            case 'httpsms':
                $channelToUse = HttpSmsChannel::class;
                break;
            case 'twilio':
                $channelToUse = TwilioSmsChannel::class;
                break;
            case 'smsgateway':
                $channelToUse = SmsGatewayAppChannel::class;
                break;
            case 'none':
                Log::info("OTP SMS sending is disabled via settings for {$normalizedMobile} (purpose: {$purpose}).");
                return $otpCode; // أرجع الرمز للاختبار أو إذا كانت هناك معالجة بديلة (مثل عرضه للمدير)
            default:
                Log::error("Unknown OTP Provider '{$otpProviderKey}' configured in settings for {$normalizedMobile} (purpose: {$purpose}). OTP not sent.");
                return null; // لا يمكن إرسال الرمز
        }

        try {
            // تأكد من أن SendOtpNotification جاهز للاستخدام مع أي قناة (يعيد 'to' و 'content')
            $notificationToSend = new SendOtpNotification($otpCode, $normalizedMobile);
            
            LaravelNotification::route($channelToUse, $normalizedMobile) // الرقم الذي سيتم تمريره للقناة
                             ->notify($notificationToSend);
            Log::info("OTP notification dispatched via provider '{$otpProviderKey}' using channel {$channelToUse} to {$normalizedMobile} (purpose: {$purpose}).");
        } catch (\Exception $e) {
            Log::error("Failed to send OTP SMS to {$normalizedMobile} via provider '{$otpProviderKey}'.", [
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return null; // فشل الإرسال
        }
        
        return $otpCode; // تم إرسال الرمز بنجاح (أو تم تعطيل الإرسال وتم إرجاع الرمز)
    }

    protected function verifyOtpForMobile(string $mobileNumber, string $otpCode, string $purpose = 'verification'): bool
    {
        // $normalizedMobile = $this->normalizeMobileForCache($mobileNumber);
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            Log::warning("OTP verification failed: No OTP found in cache or expired for {$normalizedMobile} (purpose: {$purpose}). Cache key: {$cacheKey}");
            return false;
        }

        if ($storedOtp === $otpCode) {
            Cache::forget($cacheKey);
            Log::info("OTP verification successful for {$normalizedMobile} (purpose: {$purpose}). OTP removed from cache.");
            return true;
        }

        Log::warning("OTP verification failed: Submitted OTP '{$otpCode}' does not match stored OTP '{$storedOtp}' for {$normalizedMobile} (purpose: {$purpose}).");
        return false;
    }

    // (اختياري) دالة لتطبيع رقم الجوال لمفتاح الكاش إذا لزم الأمر
    // protected function normalizeMobileForCache(string $mobileNumber): string
    // {
    //     return preg_replace('/[^0-9]/', '', $mobileNumber);
    // }
}
