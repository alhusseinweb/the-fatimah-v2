<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Setting;
use App\Notifications\SendOtpNotification;
use App\Notifications\Channels\HttpSmsChannel;
use App\Notifications\Channels\TwilioSmsChannel;    // تأكد من وجود هذا الكلاس وتعديله ليقرأ من DB
use App\Notifications\Channels\SmsGatewayAppChannel; // تأكد من وجود هذا الكلاس وتعديله ليقرأ من DB
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Twilio\Rest\Client as TwilioClient; // لاستخدام Twilio Verify
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Session; // لإدارة الجلسة مع Twilio Verify

trait ManagesOtp
{
    /**
     * يولد ويرسل رمز OTP أو يطلب إرسال تحقق عبر Twilio Verify.
     * @return bool true إذا نجحت عملية بدء إرسال الـ OTP، false إذا فشلت.
     */
    protected function generateAndSendOtp(string $mobileNumber, string $purpose = 'verification'): bool
    {
        $otpProviderKey = Setting::where('key', 'sms_otp_provider')->value('value');
        if(empty($otpProviderKey) || $otpProviderKey == 'none') {
            Log::warning("OTP Provider not set or set to 'none' in settings. OTP sending disabled for '{$purpose}' to {$mobileNumber}.");
            // إذا كان 'none'، يمكنك اختيارياً توليد رمز وتخزينه في الكاش للاختبار بدون إرسال فعلي
            // if ($otpProviderKey == 'none') {
            //     $otpCodeForNone = (string) random_int(1000, 9999);
            //     $validityMinutesForNone = (int) config('auth.otp_validity_minutes', 5);
            //     $normalizedMobileForNone = preg_replace('/[^0-9]/', '', $mobileNumber);
            //     $cacheKeyForNone = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobileForNone;
            //     Cache::put($cacheKeyForNone, $otpCodeForNone, now()->addMinutes($validityMinutesForNone));
            //     Log::info("Generated OTP {$otpCodeForNone} for 'none' provider (not sent) for {$normalizedMobileForNone}. Stored in cache.");
            //     return true; // أو false إذا كنت تعتبر "عدم الإرسال" فشلاً في هذا السياق
            // }
            return false; // فشل بسبب عدم تكوين المزود أو تعطيله
        }

        $validityMinutes = (int) config('auth.otp_validity_minutes', 5);
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber); // تطبيع أساسي

        Log::info("Attempting to generate/send OTP for {$normalizedMobile} via provider '{$otpProviderKey}' (purpose: {$purpose}).");

        switch ($otpProviderKey) {
            case 'httpsms':
            case 'smsgateway':
                $otpCode = (string) random_int(1000, 9999);
                $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
                Cache::put($cacheKey, $otpCode, now()->addMinutes($validityMinutes));
                Log::info("Generated custom OTP {$otpCode} for {$normalizedMobile}. Stored in cache [{$cacheKey}].");

                $channelToUse = ($otpProviderKey === 'httpsms') ? HttpSmsChannel::class : SmsGatewayAppChannel::class;
                try {
                    $notificationToSend = new SendOtpNotification($otpCode, $normalizedMobile); // رقم الجوال الأصلي أو المنسق حسب القناة
                    // القنوات يجب أن تتعامل مع تنسيق الرقم داخلياً إذا لزم الأمر
                    LaravelNotification::route($channelToUse, $mobileNumber) // إرسال إلى الرقم الأصلي، القناة قد تنسقه
                                     ->notify($notificationToSend);
                    Log::info("Custom OTP notification dispatched via provider '{$otpProviderKey}' to {$mobileNumber}.");
                    return true;
                } catch (\Exception $e) {
                    Log::error("Failed to send custom OTP SMS via '{$otpProviderKey}' to {$mobileNumber}.", ['error' => $e->getMessage()]);
                    Cache::forget($cacheKey);
                    return false;
                }

            case 'twilio': // استخدام Twilio Verify
                $accountSid = Setting::where('key', 'twilio_account_sid')->value('value');
                $authToken = Setting::where('key', 'twilio_auth_token')->value('value');
                $verifySid = Setting::where('key', 'twilio_verify_sid')->value('value');

                if (empty($accountSid) || empty($authToken) || empty($verifySid)) {
                    Log::error("Twilio Verify settings (Account SID, Auth Token, or Verify SID) are missing from database for OTP.");
                    return false;
                }

                try {
                    $twilio = new TwilioClient($accountSid, $authToken);
                    $e164PhoneNumber = $this->formatPhoneNumberForE164($mobileNumber); // استخدام دالة التنسيق

                    Log::info("Sending Twilio Verify OTP to {$e164PhoneNumber} using Verify SID {$verifySid}.");
                    $verification = $twilio->verify->v2->services($verifySid)
                        ->verifications
                        ->create($e164PhoneNumber, "sms", [
                            // يمكنك تحديد لغة رمز التحقق هنا إذا كانت خدمتك تدعمها
                            // 'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en' 
                        ]);

                    if ($verification->status === 'pending' || $verification->status === 'approved') {
                        Log::info("Twilio Verify OTP request sent successfully to {$e164PhoneNumber}. Status: {$verification->status}, SID: {$verification->sid}");
                        Session::put('twilio_otp_e164_mobile_for_verification', $e164PhoneNumber); // حفظ الرقم المنسق للجلسة
                        return true;
                    } else {
                        Log::error("Twilio Verify OTP request failed for {$e164PhoneNumber}. Status: {$verification->status}");
                        return false;
                    }
                } catch (TwilioException $e) {
                    Log::error("Twilio Verify API exception for {$mobileNumber}: " . $e->getMessage(), [
                        'code' => $e->getCode(), 'details' => method_exists($e, 'getDetails') ? $e->getDetails() : null
                    ]);
                    return false;
                } catch (\Exception $e) {
                    Log::error("Generic exception during Twilio Verify OTP send for {$mobileNumber}: " . $e->getMessage());
                    return false;
                }

            default:
                Log::error("Unknown or 'none' OTP Provider '{$otpProviderKey}' configured. OTP not sent for {$mobileNumber}.");
                return false;
        }
    }

    protected function verifyOtpForMobile(string $mobileNumber, string $otpCode, string $purpose = 'verification'): bool
    {
        $otpProviderKey = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';
        $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber);

        Log::info("Attempting to verify OTP '{$otpCode}' for {$normalizedMobile} via provider '{$otpProviderKey}' (purpose: {$purpose}).");

        if ($otpProviderKey === 'twilio') {
            $accountSid = Setting::where('key', 'twilio_account_sid')->value('value');
            $authToken = Setting::where('key', 'twilio_auth_token')->value('value');
            $verifySid = Setting::where('key', 'twilio_verify_sid')->value('value');
            
            // استخدام الرقم المنسق المحفوظ في الجلسة أو إعادة تنسيقه
            $e164PhoneNumber = Session::get('twilio_otp_e164_mobile_for_verification') ?? $this->formatPhoneNumberForE164($mobileNumber);

            if (empty($accountSid) || empty($authToken) || empty($verifySid) || empty($e164PhoneNumber)) {
                Log::error("Twilio Verify settings or formatted mobile number missing for OTP verification of {$mobileNumber}.");
                if(empty($e164PhoneNumber)) Session::forget('twilio_otp_e164_mobile_for_verification'); // تنظيف إذا كان الرقم مفقوداً
                return false;
            }

            try {
                $twilio = new TwilioClient($accountSid, $authToken);
                Log::info("Checking Twilio Verify OTP for {$e164PhoneNumber} with Verify SID {$verifySid}. Code: {$otpCode}");
                $verificationCheck = $twilio->verify->v2->services($verifySid)
                    ->verificationChecks
                    ->create(['to' => $e164PhoneNumber, 'code' => $otpCode]);

                if ($verificationCheck->status === 'approved') {
                    Log::info("Twilio Verify OTP check successful for {$e164PhoneNumber}. Status: {$verificationCheck->status}");
                    Session::forget('twilio_otp_e164_mobile_for_verification');
                    return true;
                } else {
                    Log::warning("Twilio Verify OTP check failed for {$e164PhoneNumber}. Status: {$verificationCheck->status}");
                    return false;
                }
            } catch (TwilioException $e) {
                 if ($e->getCode() == 20404) { // Not Found (e.g., incorrect code, max attempts, expired)
                    Log::warning("Twilio Verify: Verification check failed (Code 20404) for {$e164PhoneNumber}. Message: " . $e->getMessage());
                } else {
                    Log::error("Twilio Verify API exception during check for {$e164PhoneNumber}: " . $e->getMessage(), ['code' => $e->getCode()]);
                }
                return false;
            } catch (\Exception $e) {
                Log::error("Generic exception during Twilio Verify OTP check for {$e164PhoneNumber}: " . $e->getMessage());
                return false;
            }

        } elseif (in_array($otpProviderKey, ['httpsms', 'smsgateway'])) {
            $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
            $storedOtp = Cache::get($cacheKey);

            if (!$storedOtp) {
                Log::warning("Custom OTP verification failed: No OTP found in cache or expired for {$normalizedMobile}. Cache key: {$cacheKey}");
                return false;
            }
            if ($storedOtp === $otpCode) {
                Cache::forget($cacheKey);
                Log::info("Custom OTP verification successful for {$normalizedMobile}. OTP removed from cache.");
                return true;
            }
            Log::warning("Custom OTP verification failed: Submitted OTP '{$otpCode}' does not match stored '{$storedOtp}' for {$normalizedMobile}.");
            return false;

        } elseif ($otpProviderKey === 'none') {
            // Log::info("OTP verification skipped for 'none' provider for {$normalizedMobile}.");
            // إذا كان هناك رمز مخزن في الكاش لـ 'none' (للاختبار)، يمكنك التحقق منه هنا
            // $cacheKeyNone = 'otp_for_' . Str::slug($purpose) . '_' . $normalizedMobile;
            // $storedOtpNone = Cache::get($cacheKeyNone);
            // if ($storedOtpNone && $storedOtpNone === $otpCode) { Cache::forget($cacheKeyNone); return true; }
            return false; // بشكل عام، 'none' يعني لا يوجد تحقق فعلي
        }

        Log::error("Unknown OTP Provider '{$otpProviderKey}' for verification of {$normalizedMobile}.");
        return false;
    }

    /**
     * تنسيق رقم الهاتف إلى صيغة E.164 (مهم لـ Twilio).
     */
    protected function formatPhoneNumberForE164(string $mobileNumber): string
    {
        $cleanedNumber = preg_replace('/[^0-9]/', '', $mobileNumber);

        // السعودية: يبدأ بـ 05 وطوله 10 أرقام
        if (Str::startsWith($cleanedNumber, '05') && strlen($cleanedNumber) === 10) {
            return '+966' . substr($cleanedNumber, 1);
        }
        // السعودية: يبدأ بـ 5 (بدون الصفر) وطوله 9 أرقام
        if (Str::startsWith($cleanedNumber, '5') && strlen($cleanedNumber) === 9) {
            return '+966' . $cleanedNumber;
        }
        // إذا كان يبدأ بـ 9665 وطوله 12
        if (Str::startsWith($cleanedNumber, '9665') && strlen($cleanedNumber) === 12) {
            return '+' . $cleanedNumber;
        }
        // إذا كان يبدأ بـ + بالفعل، افترض أنه دولي
        if (Str::startsWith($mobileNumber, '+')) { // تحقق من الرقم الأصلي هنا
            return $mobileNumber;
        }
        
        // كحل أخير، إذا لم يتطابق مع الأنماط المعروفة، سجل تحذيراً
        Log::warning("PhoneNumberFormatting: Could not confidently format '{$mobileNumber}' to E.164. Returning with '+' prefix attempt.");
        return '+' . $cleanedNumber; // هذا قد لا يكون صحيحاً دائماً
    }
   }
