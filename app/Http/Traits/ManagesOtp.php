<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting; // تأكد من أن هذا هو موديل الإعدادات الصحيح
use App\Notifications\SendOtpNotification; // افترض أن هذا كلاس الإشعار لإرسال OTP المخصص
use App\Notifications\Channels\HttpSmsChannel;
// تأكد من أن هذه الكلاسات موجودة ومُعدة بشكل صحيح إذا كنت ستستخدمها
// use App\Notifications\Channels\TwilioSmsChannel; 
// use App\Notifications\Channels\SmsGatewayAppChannel; 
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Session;

trait ManagesOtp
{
    /**
     * يولد ويرسل رمز OTP أو يطلب إرسال تحقق عبر Twilio Verify.
     * @param string $mobileNumber
     * @param string $purpose
     * @return bool true إذا نجحت عملية بدء إرسال الـ OTP، false إذا فشلت.
     */
    protected function generateAndSendOtp(string $mobileNumber, string $purpose = 'verification'): bool
    {
        // جلب مزود خدمة OTP من الإعدادات
        $otpProviderKey = Setting::where('key', 'sms_otp_provider')->value('value');

        if(empty($otpProviderKey) || $otpProviderKey == 'none') {
            Log::warning("ManagesOtp: OTP Provider not set or set to 'none'. OTP sending disabled for '{$purpose}' to {$mobileNumber}.");
            // يمكنك إضافة منطق لتوليد رمز وتخزينه للاختبار إذا كان المزود 'none'
            // if ($otpProviderKey == 'none' && config('app.env') !== 'production') {
            //     $otpCodeForNone = (string) random_int(config('otp.code_min', 1000), config('otp.code_max', 9999));
            //     $validityMinutesForNone = (int) config('otp.validity_minutes', 5);
            //     $normalizedMobileForNone = preg_replace('/[^0-9]/', '', $mobileNumber);
            //     $cacheKeyForNone = $this->getOtpCacheKey($normalizedMobileForNone, $purpose);
            //     Cache::put($cacheKeyForNone, $otpCodeForNone, now()->addMinutes($validityMinutesForNone));
            //     Log::info("ManagesOtp: Generated OTP {$otpCodeForNone} for 'none' provider (NOT SENT) for {$normalizedMobileForNone}. Stored in cache [{$cacheKeyForNone}].");
            //     return true; // اعتبرها نجاحًا (لأنها لا ترسل) أو false حسب منطقك
            // }
            return false; 
        }

        $validityMinutes = (int) config('otp.validity_minutes', 5); // مدة صلاحية OTP من ملف config
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber); 

        Log::info("ManagesOtp: Attempting to generate/send OTP for {$normalizedMobile} via provider '{$otpProviderKey}' (purpose: {$purpose}).");

        switch ($otpProviderKey) {
            case 'httpsms':
            case 'smsgateway': // افترض أن بوابة الرسائل هذه تستخدم نفس منطق OTP المخصص
                $otpCode = (string) random_int(config('otp.code_min', 1000), config('otp.code_max', 9999)); // طول الرمز من config
                $cacheKey = $this->getOtpCacheKey($normalizedMobile, $purpose); // استخدام الدالة المساعدة
                Cache::put($cacheKey, $otpCode, now()->addMinutes($validityMinutes));
                Log::info("ManagesOtp: Generated custom OTP {$otpCode} for {$normalizedMobile}. Stored in cache [{$cacheKey}].");

                $channelToUse = ($otpProviderKey === 'httpsms') ? HttpSmsChannel::class : null; // SmsGatewayAppChannel::class
                if (!$channelToUse && $otpProviderKey === 'smsgateway') {
                     Log::error("ManagesOtp: SmsGatewayAppChannel not yet fully implemented or 'use' statement missing.");
                     return false; // أو تعامل معها بشكل مختلف
                }


                try {
                    $notificationToSend = new SendOtpNotification($otpCode, $normalizedMobile); 
                    LaravelNotification::route($channelToUse, $mobileNumber) 
                                     ->notify($notificationToSend);
                    Log::info("ManagesOtp: Custom OTP notification dispatched via provider '{$otpProviderKey}' to {$mobileNumber}.");
                    return true;
                } catch (\Exception $e) {
                    Log::error("ManagesOtp: Failed to send custom OTP SMS via '{$otpProviderKey}' to {$mobileNumber}.", ['error' => $e->getMessage(), 'trace' => Str::limit($e->getTraceAsString(), 500)]);
                    Cache::forget($cacheKey); // مسح الكاش عند الفشل
                    return false;
                }

            case 'twilio': // استخدام Twilio Verify
                $accountSid = Setting::where('key', 'twilio_account_sid')->value('value');
                $authToken = Setting::where('key', 'twilio_auth_token')->value('value');
                $verifySid = Setting::where('key', 'twilio_verify_sid')->value('value');

                if (empty($accountSid) || empty($authToken) || empty($verifySid)) {
                    Log::error("ManagesOtp: Twilio Verify settings are missing from database for OTP provider '{$otpProviderKey}'.");
                    return false;
                }

                try {
                    $twilio = new TwilioClient($accountSid, $authToken);
                    $e164PhoneNumber = $this->formatPhoneNumberForE164($mobileNumber); 

                    if(!$e164PhoneNumber){
                        Log::error("ManagesOtp: Could not format mobile number '{$mobileNumber}' to E.164 for Twilio Verify.");
                        return false;
                    }

                    Log::info("ManagesOtp: Sending Twilio Verify OTP to {$e164PhoneNumber} using Verify SID {$verifySid}.");
                    $verification = $twilio->verify->v2->services($verifySid)
                        ->verifications
                        ->create($e164PhoneNumber, "sms", [
                             'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en' // محاولة تحديد اللغة
                        ]);

                    if (in_array($verification->status, ['pending', 'approved'])) { // approved إذا كان التحقق فوريًا
                        Log::info("ManagesOtp: Twilio Verify OTP request sent successfully to {$e164PhoneNumber}. Status: {$verification->status}, SID: {$verification->sid}");
                        Session::put('twilio_otp_e164_mobile_for_verification', $e164PhoneNumber); 
                        return true;
                    } else {
                        Log::error("ManagesOtp: Twilio Verify OTP request failed for {$e164PhoneNumber}. Status: {$verification->status}");
                        return false;
                    }
                } catch (TwilioException $e) {
                    Log::error("ManagesOtp: Twilio Verify API exception for {$mobileNumber}: " . $e->getMessage(), [
                        'code' => $e->getCode(), 'details' => method_exists($e, 'getDetails') ? $e->getDetails() : null
                    ]);
                    return false;
                } catch (\Exception $e) {
                    Log::error("ManagesOtp: Generic exception during Twilio Verify OTP send for {$mobileNumber}: " . $e->getMessage());
                    return false;
                }

            default:
                Log::error("ManagesOtp: Unknown OTP Provider '{$otpProviderKey}' configured. OTP not sent for {$mobileNumber}.");
                return false;
        }
    }

    /**
     * يتحقق من صحة رمز OTP المدخل.
     * @param string $mobileNumber
     * @param string $otpCode
     * @param string $purpose
     * @return bool
     */
    protected function verifyOtpForMobile(string $mobileNumber, string $otpCode, string $purpose = 'verification'): bool
    {
        $otpProviderKey = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber);

        Log::info("ManagesOtp: Attempting to verify OTP '{$otpCode}' for {$normalizedMobile} via provider '{$otpProviderKey}' (purpose: {$purpose}).");

        if ($otpProviderKey === 'twilio') {
            $accountSid = Setting::where('key', 'twilio_account_sid')->value('value');
            $authToken = Setting::where('key', 'twilio_auth_token')->value('value');
            $verifySid = Setting::where('key', 'twilio_verify_sid')->value('value');
            
            $e164PhoneNumber = Session::get('twilio_otp_e164_mobile_for_verification') ?? $this->formatPhoneNumberForE164($mobileNumber);

            if (empty($accountSid) || empty($authToken) || empty($verifySid) || empty($e164PhoneNumber)) {
                Log::error("ManagesOtp: Twilio Verify settings or formatted mobile number missing for OTP verification of {$mobileNumber}.");
                if(empty($e164PhoneNumber)) Session::forget('twilio_otp_e164_mobile_for_verification');
                return false;
            }

            try {
                $twilio = new TwilioClient($accountSid, $authToken);
                Log::info("ManagesOtp: Checking Twilio Verify OTP for {$e164PhoneNumber} with Verify SID {$verifySid}. Code: {$otpCode}");
                $verificationCheck = $twilio->verify->v2->services($verifySid)
                    ->verificationChecks
                    ->create(['to' => $e164PhoneNumber, 'code' => $otpCode]);

                if ($verificationCheck->status === 'approved') {
                    Log::info("ManagesOtp: Twilio Verify OTP check successful for {$e164PhoneNumber}. Status: {$verificationCheck->status}");
                    Session::forget('twilio_otp_e164_mobile_for_verification');
                    return true;
                } else {
                    Log::warning("ManagesOtp: Twilio Verify OTP check failed for {$e164PhoneNumber}. Status: {$verificationCheck->status}");
                    return false;
                }
            } catch (TwilioException $e) {
                 if ($e->getCode() == 20404) { // Not Found (e.g., incorrect code, max attempts, expired)
                    Log::warning("ManagesOtp: Twilio Verify: Verification check failed (Code 20404) for {$e164PhoneNumber}. Message: " . $e->getMessage());
                } else {
                    Log::error("ManagesOtp: Twilio Verify API exception during check for {$e164PhoneNumber}: " . $e->getMessage(), ['code' => $e->getCode()]);
                }
                return false;
            } catch (\Exception $e) {
                Log::error("ManagesOtp: Generic exception during Twilio Verify OTP check for {$e164PhoneNumber}: " . $e->getMessage());
                return false;
            }

        } elseif (in_array($otpProviderKey, ['httpsms', 'smsgateway', 'none'])) { // 'none' for testing cached OTP
            $cacheKey = $this->getOtpCacheKey($normalizedMobile, $purpose); // استخدام الدالة المساعدة
            $storedOtp = Cache::get($cacheKey);

            if (!$storedOtp) {
                Log::warning("ManagesOtp: Custom OTP verification failed: No OTP found in cache or expired for {$normalizedMobile}. Cache key: {$cacheKey}");
                return false;
            }
            if ($storedOtp === $otpCode) {
                // لا تقم بمسح الكاش هنا مباشرة، اتركه للكنترولر بعد إتمام العملية بنجاح
                // Cache::forget($cacheKey); 
                Log::info("ManagesOtp: Custom OTP verification successful for {$normalizedMobile}. Stored OTP matched.");
                return true;
            }
            Log::warning("ManagesOtp: Custom OTP verification failed: Submitted OTP '{$otpCode}' does not match stored '{$storedOtp}' for {$normalizedMobile}.");
            return false;
        }

        Log::error("ManagesOtp: Unknown OTP Provider '{$otpProviderKey}' for verification of {$normalizedMobile}.");
        return false;
    }

    /**
     * تنسيق رقم الهاتف إلى صيغة E.164 (مهم لـ Twilio).
     * @param string $mobileNumber
     * @return string|null Null if formatting fails or number is invalid
     */
    protected function formatPhoneNumberForE164(string $mobileNumber): ?string
    {
        $cleanedNumber = preg_replace('/[^0-9]/', '', $mobileNumber);

        // السعودية: 05xxxxxxxx -> +9665xxxxxxxx
        if (Str::startsWith($cleanedNumber, '05') && strlen($cleanedNumber) === 10) {
            return '+966' . substr($cleanedNumber, 1);
        }
        // السعودية: 5xxxxxxxx -> +9665xxxxxxxx
        if (Str::startsWith($cleanedNumber, '5') && strlen($cleanedNumber) === 9) {
            return '+966' . $cleanedNumber;
        }
        // السعودية: 9665xxxxxxxx -> +9665xxxxxxxx
        if (Str::startsWith($cleanedNumber, '9665') && strlen($cleanedNumber) === 12) {
            return '+' . $cleanedNumber;
        }
        // السعودية: 009665xxxxxxxx -> +9665xxxxxxxx
        if (Str::startsWith($cleanedNumber, '009665') && strlen($cleanedNumber) === 14) {
            return '+' . substr($cleanedNumber, 2);
        }

        // إذا كان الرقم يبدأ بـ + بالفعل، افترض أنه دولي وصحيح (يمكن إضافة المزيد من التحققات)
        if (Str::startsWith($mobileNumber, '+')) {
            if (strlen($cleanedNumber) >= 10 && strlen($cleanedNumber) <= 15) { // مدى معقول لطول الأرقام الدولية
                return '+' . $cleanedNumber; // تأكد من وجود + واحدة فقط
            }
        }
        
        Log::warning("ManagesOtp - formatPhoneNumberForE164: Could not confidently format '{$mobileNumber}' to E.164 for Twilio.");
        return null; // إرجاع null إذا لم يتمكن من التنسيق بشكل موثوق
    }

    // --- START: ADDED HELPER FUNCTIONS FOR CACHE KEYS ---
    /**
     * Get the cache key for storing the OTP.
     *
     * @param string $normalizedMobile
     * @param string $purpose (e.g., 'login', 'registration')
     * @return string
     */
    protected function getOtpCacheKey(string $normalizedMobile, string $purpose): string
    {
        return 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
    }

    /**
     * Get the cache key for storing OTP attempts.
     *
     * @param string $normalizedMobile
     * @param string $purpose
     * @return string
     */
    protected function getOtpAttemptCacheKey(string $normalizedMobile, string $purpose): string
    {
        return 'otp_attempts_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
    }

    /**
     * Get the cache key for OTP resend availability.
     * @param string $normalizedMobile
     * @param string $purpose
     * @return string
     */
    protected function getOtpResendAvailabilityCacheKey(string $normalizedMobile, string $purpose): string
    {
        return 'otp_resend_available_after_' . Str::slug($purpose) . '_' . $normalizedMobile;
    }
    // --- END: ADDED HELPER FUNCTIONS FOR CACHE KEYS ---
}
