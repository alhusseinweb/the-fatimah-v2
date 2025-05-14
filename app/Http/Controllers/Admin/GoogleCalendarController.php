<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\GoogleCalendar\GoogleCalendarFactory;
use Google\Client as GoogleClient; // استيراد Google Client مباشرة للتحكم بشكل أفضل

class GoogleCalendarController extends Controller
{
    /**
     * Get Google API client configuration.
     */
    protected function getGoogleClientConfig(): array
    {
        return [
            'client_id' => config('google-calendar.oauth.client_id'),
            'client_secret' => config('google-calendar.oauth.client_secret'),
            'redirect_uri' => config('google-calendar.oauth.redirect_uri'),
            // Scopes and other settings will be used from the config file by the package
            // or can be set on the client instance directly if needed.
        ];
    }

    /**
     * Redirect the user to the Google OAuth consent screen.
     */
    public function connect(Request $request)
    {
        try {
            // استخدام Google_Client مباشرة لإنشاء Auth URL بشكل أوضح
            $client = new GoogleClient();
            $client->setClientId(config('google-calendar.oauth.client_id'));
            $client->setClientSecret(config('google-calendar.oauth.client_secret'));
            $client->setRedirectUri(config('google-calendar.oauth.redirect_uri'));
            $client->setScopes(config('google-calendar.scopes', [\Google\Service\Calendar::CALENDAR_EVENTS]));
            $client->setAccessType(config('google-calendar.access_type', 'offline'));
            $client->setApprovalPrompt(config('google-calendar.approval_prompt', 'force'));
            // مهم لضمان أن Google تعرف أنك تريد رمز تفويض وليس فقط مفتاح وصول فوري
            $client->setIncludeGrantedScopes(true);


            $authUrl = $client->createAuthUrl();

            return redirect()->away($authUrl);

        } catch (\Exception $e) {
            Log::error('Google Calendar connect error: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('admin.settings.edit')
                             ->with('google_calendar_error', 'حدث خطأ أثناء محاولة الاتصال بـ Google Calendar. يرجى المحاولة مرة أخرى. التفاصيل: ' . $e->getMessage());
        }
    }

    /**
     * Handle the callback from Google after OAuth authorization.
     */
    public function callback(Request $request)
    {
        /** @var \App\Models\User $adminUser */
        $adminUser = Auth::user();

        if (!$adminUser || !$adminUser->is_admin) {
            Log::warning('Google Calendar callback attempt by non-admin or unauthenticated user.');
            return redirect()->route('home')->with('error', 'غير مصرح لك.');
        }

        if ($request->has('error')) {
            Log::error('Google Calendar callback error from Google: ' . $request->input('error'), $request->all());
            return redirect()->route('admin.settings.edit')
                             ->with('google_calendar_error', 'تم إلغاء عملية الربط مع Google Calendar أو حدث خطأ من جانب Google: ' . $request->input('error_description', $request->input('error')));
        }

        if (!$request->has('code')) {
            Log::error('Google Calendar callback - No authorization code received.');
            return redirect()->route('admin.settings.edit')
                             ->with('google_calendar_error', 'لم يتم استلام رمز التفويض من Google.');
        }

        try {
            // استخدام Google_Client مباشرة لتبادل الرمز بالمفتاح
            $client = new GoogleClient();
            $client->setClientId(config('google-calendar.oauth.client_id'));
            $client->setClientSecret(config('google-calendar.oauth.client_secret'));
            $client->setRedirectUri(config('google-calendar.oauth.redirect_uri')); // يجب أن يكون مطابقًا

            // استبدال رمز التفويض بمفتاح الوصول (ومفتاح التحديث)
            $accessToken = $client->fetchAccessTokenWithAuthCode($request->input('code'));

            if (isset($accessToken['error'])) {
                Log::error('Google Calendar callback - Error fetching access token: ', $accessToken);
                throw new \Exception('فشل في الحصول على مفتاح الوصول من Google: ' . ($accessToken['error_description'] ?? $accessToken['error']));
            }
            
            // الآن لدينا $accessToken كمصفوفة تحتوي على access_token, refresh_token, expires_in, etc.
            // نحتاج إلى حفظها باستخدام الـ AccessTokenRepository الخاص بنا.
            // الحزمة `spatie/laravel-google-calendar` عادة ما تتوقع أن يتم هذا تلقائيًا
            // إذا تم تكوين `access_token_repository` بشكل صحيح.
            // لنتأكد من أن `DatabaseTokenRepository` يتم استدعاؤه بشكل صحيح.

            $tokenRepository = app(config('google-calendar.access_token_repository'));
            if ($tokenRepository && $tokenRepository instanceof \Spatie\GoogleCalendar\GoogleCalendarAccesToken) {
                 $tokenRepository->saveAccessToken($accessToken); // حفظ المفتاح للمستخدم الحالي (Auth::user())
            } else {
                Log::error('Google Calendar callback - AccessTokenRepository not configured correctly or does not implement GoogleCalendarAccesToken interface.');
                throw new \Exception('خطأ في تكوين مستودع مفاتيح الوصول.');
            }


            $adminUser->refresh(); // تحديث بيانات المستخدم من قاعدة البيانات للتأكد من حفظ المفاتيح

            if (method_exists($adminUser, 'hasGoogleCalendarAccess') && $adminUser->hasGoogleCalendarAccess()) {
                if (empty($adminUser->google_calendar_id)) {
                    $adminUser->google_calendar_id = config('google-calendar.calendar_id', 'primary');
                    $adminUser->save();
                }
                return redirect()->route('admin.settings.edit')
                                 ->with('google_calendar_success', 'تم ربط حساب Google Calendar بنجاح!');
            } else {
                Log::error('Google Calendar callback - Token saved but hasGoogleCalendarAccess is false.', ['user_id' => $adminUser->id]);
                throw new \Exception('فشل في تأكيد حفظ مفاتيح Google Calendar.');
            }

        } catch (\Exception $e) {
            Log::error('Google Calendar callback - token exchange or save error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
            if ($adminUser && method_exists($adminUser, 'clearGoogleCalendarCredentials')) {
                $adminUser->clearGoogleCalendarCredentials();
            }
            return redirect()->route('admin.settings.edit')
                             ->with('google_calendar_error', 'حدث خطأ أثناء معالجة الرد من Google أو حفظ مفاتيح الوصول. يرجى المحاولة مرة أخرى. الخطأ: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Google Calendar for the admin user.
     */
    public function disconnect(Request $request)
    {
        /** @var \App\Models\User $adminUser */
        $adminUser = Auth::user();

        if ($adminUser && $adminUser->is_admin && method_exists($adminUser, 'clearGoogleCalendarCredentials')) {
            try {
                // اختياري: إلغاء صلاحية المفتاح من جانب Google (Revoke token)
                // هذا يتطلب أن يكون لدينا مفتاح صالح حاليًا (خاصة refresh_token)
                if ($adminUser->google_refresh_token) {
                    try {
                        $refreshTokenDecrypted = $adminUser->google_refresh_token_decrypted; // استخدم الـ accessor
                        if($refreshTokenDecrypted){
                            $client = new GoogleClient();
                            $client->setClientId(config('google-calendar.oauth.client_id'));
                            $client->setClientSecret(config('google-calendar.oauth.client_secret'));
                            $client->revokeToken($refreshTokenDecrypted); // Revoke using the refresh token
                            Log::info("Google Calendar token revoked for user ID {$adminUser->id}.");
                        }
                    } catch (\Exception $revokeException) {
                        Log::warning("Google Calendar disconnect: Failed to revoke token from Google for user ID {$adminUser->id}. Error: " . $revokeException->getMessage());
                        // لا توقف العملية إذا فشل الإلغاء من Google، سنقوم بمسح المفاتيح محليًا على أي حال
                    }
                }

                $adminUser->clearGoogleCalendarCredentials();

                return redirect()->route('admin.settings.edit')
                                 ->with('google_calendar_success', 'تم إلغاء ربط حساب Google Calendar بنجاح.');

            } catch (\Exception $e) {
                Log::error('Google Calendar disconnect error: ' . $e->getMessage());
                // حتى لو فشل الإلغاء من جانب Google، قم بإزالة المفاتيح من قاعدة بياناتنا
                $adminUser->clearGoogleCalendarCredentials();
                return redirect()->route('admin.settings.edit')
                                 ->with('google_calendar_warning', 'حدث خطأ أثناء محاولة إلغاء صلاحية المفتاح من Google، ولكن تم إزالة الربط من النظام.');
            }
        }
        return redirect()->route('admin.settings.edit')->with('google_calendar_error', 'حدث خطأ غير متوقع أو المستخدم غير مصرح له.');
    }
}