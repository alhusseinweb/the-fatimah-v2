<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log; // استيراد Log
use Carbon\Carbon; // استيراد Carbon

// لا تقم بعمل 'use' للواجهة هنا، سنستخدم الاسم الكامل أدناه
// use Spatie\GoogleCalendar\Tokens\GoogleCalendarAccessToken as GoogleCalendarAccessTokenInterface;

class DatabaseTokenRepository implements \Spatie\GoogleCalendar\Tokens\GoogleCalendarAccessToken // <-- استخدام الاسم المؤهل بالكامل هنا
{
    public function getAccessToken(): ?array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user || !$user->google_access_token) {
            return null;
        }

        $decryptedAccessToken = null;
        $decryptedRefreshToken = null;

        try {
            $decryptedAccessToken = $user->google_access_token ? Crypt::decryptString($user->google_access_token) : null;
            $decryptedRefreshToken = $user->google_refresh_token ? Crypt::decryptString($user->google_refresh_token) : null;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::error("Failed to decrypt Google token for user ID {$user->id}: " . $e->getMessage());
            return null;
        }

        if (!$decryptedAccessToken) {
            return null;
        }

        $expiresAt = $user->google_token_expires_at; // هذا يجب أن يكون كائن Carbon بسبب الـ cast في نموذج User
        $expiresIn = null;
        $created = null; // تقدير وقت الإنشاء

        if ($expiresAt instanceof Carbon) {
            $expiresAtTimestamp = $expiresAt->getTimestamp();
            $currentTime = time();
            if ($expiresAtTimestamp > $currentTime) {
                $expiresIn = $expiresAtTimestamp - $currentTime;
                // الحزمة تتوقع 'created' كـ timestamp.
                // إذا كانت صلاحية المفتاح ساعة واحدة (3600 ثانية أو 3599 عمليًا من Google)
                // فإن created = expiresAtTimestamp - (المدة الأصلية لـ expires_in)
                // بما أننا لا نخزن 'created' الأصلي، سنفترض أن مدة الصلاحية الأصلية كانت حوالي ساعة.
                $originalExpiresInSeconds = 3599; // القيمة الشائعة من Google
                $created = $expiresAtTimestamp - $originalExpiresInSeconds;

            } else {
                $expiresIn = 0; // انتهت صلاحيته
                $created = $expiresAtTimestamp - 3599; // افترض أنه انتهى لتوّه
            }
        } else {
             // إذا لم يكن $expiresAt كائن Carbon صالحًا، ربما لم يتم حفظه بشكل صحيح
             Log::warning("google_token_expires_at is not a valid Carbon instance for user ID {$user->id}. Value: " . print_r($expiresAt, true));
             // يمكنك تعيين قيم افتراضية أو إرجاع null إذا كان هذا يعتبر خطأ فادحًا
             $created = time() - 3600; // افترض أنه أنشئ قبل ساعة
             $expiresIn = 0; // انتهت صلاحيته
        }


        $tokenArray = [
            'access_token' => $decryptedAccessToken,
            'expires_in' => $expiresIn,
            'created' => $created,
        ];

        if ($decryptedRefreshToken) {
            $tokenArray['refresh_token'] = $decryptedRefreshToken;
        }
        
        return $tokenArray;
    }

    public function saveAccessToken(array $accessToken): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            Log::warning('Attempted to save Google access token for non-authenticated user.');
            return;
        }

        try {
            if (isset($accessToken['access_token'])) {
                $user->google_access_token = Crypt::encryptString($accessToken['access_token']);
            }

            if (isset($accessToken['refresh_token'])) {
                $user->google_refresh_token = Crypt::encryptString($accessToken['refresh_token']);
            }

            $createdTimestamp = $accessToken['created'] ?? time();

            if (isset($accessToken['expires_in'])) {
                $user->google_token_expires_at = Carbon::createFromTimestamp($createdTimestamp + $accessToken['expires_in']);
            } elseif (isset($accessToken['expires_at'])) { // Not standard from Google OAuth but handle if present
                 $user->google_token_expires_at = Carbon::createFromTimestamp($accessToken['expires_at']);
            } else {
                // قيمة افتراضية إذا لم يتم توفير مدة الصلاحية (مثلاً + أقل من ساعة واحدة)
                Log::warning("Google access token response did not contain 'expires_in'. Setting default expiration for user ID {$user->id}.");
                $user->google_token_expires_at = Carbon::createFromTimestamp($createdTimestamp + 3500); // 3500 ثانية
            }

            if (empty($user->google_calendar_id)) {
                $user->google_calendar_id = config('google-calendar.calendar_id', 'primary');
            }

            $user->save();
            Log::info("Google access token saved successfully for user ID {$user->id}. Expires at: " . $user->google_token_expires_at);

        } catch (\Exception $e) {
            Log::error("Error saving Google access token for user ID {$user->id}: " . $e->getMessage(), ['exception' => $e]);
        }
    }
}