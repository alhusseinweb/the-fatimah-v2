<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Notifications\SendOtpNotification; // استيراد إشعار OTP الجديد
use App\Notifications\Channels\HttpSmsChannel; // استيراد القناة
use Illuminate\Support\Facades\Notification as LaravelNotification; // اسم مستعار لـ Notification Facade
use Illuminate\Support\Facades\Session;


class OtpLoginController extends Controller
{
    // مدة صلاحية OTP بالدقائق (يمكنك وضعها في ملف config/auth.php)
    protected const OTP_VALIDITY_MINUTES = 5;

    /**
     * توليد وإرسال رمز OTP.
     */
    protected function generateAndSendOtp(string $mobileNumber, string $purpose = 'login'): string
    {
        $otpCode = (string) random_int(1000, 9999); // توليد 4 أرقام
        $validityMinutes = config('auth.otp_validity_minutes', self::OTP_VALIDITY_MINUTES);

        // تطبيع رقم الجوال (إزالة المسافات أو الـ + إذا كان نظام الرسائل لا يتطلبها أو يتعامل معها)
        // $normalizedMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
        // حالياً سنفترض أن رقم الجوال بالصيغة الصحيحة المطلوبة من httpsms

        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $mobileNumber;
        Cache::put($cacheKey, $otpCode, now()->addMinutes($validityMinutes));

        Log::info("Generated OTP {$otpCode} for {$mobileNumber} (purpose: {$purpose}). Stored in cache [{$cacheKey}] for {$validityMinutes} minutes.");

        try {
            // إرسال الإشعار باستخدام Notification facade و route() لتحديد المستلم والقناة
            LaravelNotification::route(HttpSmsChannel::class, $mobileNumber)
                             ->notify(new SendOtpNotification($otpCode, $mobileNumber));
            Log::info("SendOtpNotification dispatched for {$mobileNumber} (purpose: {$purpose}).");
        } catch (\Exception $e) {
            Log::error("Failed to send OTP SMS to {$mobileNumber} (purpose: {$purpose}).", ['error' => $e->getMessage()]);
            // يمكنك معالجة فشل الإرسال هنا إذا أردت، مثل إرجاع خطأ للمستخدم مباشرة
        }
        
        return $otpCode; // يُعاد للاختبار أو إذا احتاج الأمر، لكن الاعتماد الأساسي على الكاش
    }

    /**
     * التحقق من رمز OTP المدخل.
     */
    protected function verifyOtpForMobile(string $mobileNumber, string $otpCode, string $purpose = 'login'): bool
    {
        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $mobileNumber;
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            Log::warning("OTP verification failed: No OTP found in cache or expired for {$mobileNumber} (purpose: {$purpose}). Cache key: {$cacheKey}");
            return false;
        }

        if ($storedOtp === $otpCode) {
            Cache::forget($cacheKey); // تم استخدام الرمز، احذفه من الكاش
            Log::info("OTP verification successful for {$mobileNumber} (purpose: {$purpose}). OTP removed from cache.");
            return true;
        }

        Log::warning("OTP verification failed: Submitted OTP '{$otpCode}' does not match stored OTP '{$storedOtp}' for {$mobileNumber} (purpose: {$purpose}).");
        return false;
    }


    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }
        return view('auth.otp-login');
    }

    public function showOtpForm()
    {
        if (!Session::has('mobile_for_verification')) {
            return redirect()->route('login.otp.form')->with('error', 'يرجى إدخال رقم الجوال أولاً.');
        }
        $mobileNumber = Session::get('mobile_for_verification');
        return view('auth.otp-verify', compact('mobileNumber'));
    }

    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/'], // نمط الجوال السعودي
        ],[
            'mobile_number.required' => 'رقم الجوال مطلوب.',
            'mobile_number.regex' => 'صيغة رقم الجوال غير صحيحة (مثال: 05xxxxxxxx).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mobileNumber = $request->mobile_number;
        $user = User::where('mobile_number', $mobileNumber)->first();

        if (!$user) {
            Log::warning("Login OTP request: No account found for mobile {$mobileNumber}.");
            return response()->json([
                'message' => 'no_account_found',
                'mobile_number' => $mobileNumber
            ], 404);
        }
        
        // (اختياري) التحقق إذا كان المستخدم نشطاً
        // if (!$user->is_active) { return response()->json(['message' => 'هذا الحساب غير نشط.'], 403); }

        $this->generateAndSendOtp($mobileNumber, 'login');

        Session::put('mobile_for_verification', $mobileNumber);
        Log::info("Login OTP requested for {$mobileNumber}. User will be prompted for OTP.");
        
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى جوالك.'
            // لا حاجة لإعادة توجيه من هنا، الواجهة الأمامية تتعامل مع هذا
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_number_hidden' => ['required', 'regex:/^05\d{8}$/'],
            'otp_code' => ['required', 'string', 'digits:4'],
        ],[
            'mobile_number_hidden.required' => 'رقم الجوال مطلوب للتحقق.',
            'otp_code.required' => 'رمز التحقق مطلوب.',
            'otp_code.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('login.otp.verify.form')
                ->withErrors($validator)
                ->withInput($request->only('mobile_number_hidden'))
                ->with('mobile_number', $request->input('mobile_number_hidden')); // تمرير رقم الجوال مرة أخرى
        }

        $mobileNumber = $request->input('mobile_number_hidden');
        $otpCode = $request->input('otp_code');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumber}' (login).");

        if ($this->verifyOtpForMobile($mobileNumber, $otpCode, 'login')) {
            $user = User::where('mobile_number', $mobileNumber)->first();
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                Session::forget('mobile_for_verification');
                Log::info("User {$user->id} ({$user->email}) logged in successfully via OTP.");
                return $this->redirectBasedOnRole($user);
            }
            Log::error("OTP verified for {$mobileNumber}, but user could not be found for login.");
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'حدث خطأ غير متوقع. حاول مرة أخرى.')
                             ->with('mobile_number', $mobileNumber);
        } else {
            Log::warning("Login OTP verification failed for {$mobileNumber}.");
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'رمز التحقق الذي أدخلته غير صحيح أو انتهت صلاحيته.')
                             ->withInput($request->only('mobile_number_hidden', 'otp_code'))
                             ->with('mobile_number', $mobileNumber);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Log::info("User logged out.");
        return redirect('/');
    }

    protected function redirectBasedOnRole(User $user)
    {
        $redirectPath = $user->is_admin ? route('admin.dashboard') : route('customer.dashboard');
        return redirect()->intended($redirectPath);
    }
}
