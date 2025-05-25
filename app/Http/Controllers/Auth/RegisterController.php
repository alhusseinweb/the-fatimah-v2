<?php

namespace App\Http{

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\SendOtpNotification; // استيراد إشعار OTP الجديد
use App\Notifications\Channels\HttpSmsChannel; // استيراد القناة
use Illuminate\Support\Facades\Notification as LaravelNotification; // اسم مستعار لـ Notification Facade
use Illuminate\Support\Facades\Cache;       // استيراد الكاش


class RegisterController extends Controller
{
    // مدة صلاحية OTP بالدقائق (يمكنك وضعها في ملف config/auth.php)
    protected const OTP_VALIDITY_MINUTES = 5;

    /**
     * توليد وإرسال رمز OTP.
     */
    protected function generateAndSendOtp(string $mobileNumber, string $purpose = 'registration'): string
    {
        $otpCode = (string) random_int(1000, 9999);
        $validityMinutes = config('auth.otp_validity_minutes', self::OTP_VALIDITY_MINUTES);

        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $mobileNumber;
        Cache::put($cacheKey, $otpCode, now()->addMinutes($validityMinutes));

        Log::info("Generated OTP {$otpCode} for {$mobileNumber} (purpose: {$purpose}). Stored in cache [{$cacheKey}] for {$validityMinutes} minutes.");
        
        try {
            LaravelNotification::route(HttpSmsChannel::class, $mobileNumber)
                             ->notify(new SendOtpNotification($otpCode, $mobileNumber));
            Log::info("SendOtpNotification dispatched for {$mobileNumber} (purpose: {$purpose}).");
        } catch (\Exception $e) {
            Log::error("Failed to send OTP SMS to {$mobileNumber} (purpose: {$purpose}).", ['error' => $e->getMessage()]);
        }
        return $otpCode;
    }

    /**
     * التحقق من رمز OTP المدخل.
     */
    protected function verifyOtpForMobile(string $mobileNumber, string $otpCode, string $purpose = 'registration'): bool
    {
        $cacheKey = 'otp_for_' . Str::slug($purpose) . '_' . $mobileNumber;
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            Log::warning("OTP verification failed: No OTP found in cache or expired for {$mobileNumber} (purpose: {$purpose}). Cache key: {$cacheKey}");
            return false;
        }

        if ($storedOtp === $otpCode) {
            Cache::forget($cacheKey);
            Log::info("OTP verification successful for {$mobileNumber} (purpose: {$purpose}). OTP removed from cache.");
            return true;
        }

        Log::warning("OTP verification failed: Submitted OTP '{$otpCode}' does not match stored OTP '{$storedOtp}' for {$mobileNumber} (purpose: {$purpose}).");
        return false;
    }

    public function showRegistrationForm(Request $request) // تمت إضافة Request هنا
    {
        $mobileNumber = $request->query('mobile_number', old('mobile_number'));
        return view('auth.register', ['mobile_number' => $mobileNumber]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/', 'unique:users,mobile_number'],
        ], [
            'mobile_number.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
            'mobile_number.unique' => 'رقم الجوال هذا مسجل بالفعل.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل بالفعل.',
            'name.required' => 'حقل الاسم مطلوب.',
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('register') // أو back()
                ->withErrors($validator)
                ->withInput(); // لإعادة ملء الحقول بالقيم القديمة
        }
        
        $mobileNumber = $request->input('mobile_number');

        // تخزين بيانات التسجيل المؤقتة في الجلسة
        Session::put('registration_data', [
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $mobileNumber, // استخدام الرقم المفلتر
        ]);

        // إرسال OTP باستخدام النظام الجديد
        try {
            Log::info('Starting registration OTP process for: ' . $mobileNumber);
            $this->generateAndSendOtp($mobileNumber, 'registration');

            return redirect()->route('register.verify.form')
                             ->with('mobile_number', $mobileNumber); // تمرير رقم الجوال للواجهة التالية
        } catch (\Exception $e) {
            Log::error('Failed to send OTP during registration: ' . $e->getMessage());
            return redirect()->route('register')
                ->withInput()
                ->with('error', 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.');
        }
    }

    public function showVerifyForm(Request $request) // تمت إضافة Request
    {
        // استخدام رقم الجوال من query parameter إذا وجد، أو من الجلسة
        $mobileNumber = $request->query('mobile_number', Session::get('registration_data.mobile_number'));

        if (!Session::has('registration_data') || !$mobileNumber) {
            return redirect()->route('register')
                ->with('error', 'يرجى إدخال بياناتك أولاً أو انتهت صلاحية الجلسة.');
        }
        // تمرير رقم الجوال للعرض
        return view('auth.register-verify', ['mobile_number' => $mobileNumber]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/'], // رقم الجوال من الحقل المخفي
            'otp' => ['required', 'string', 'digits:4'], // اسم الحقل هو 'otp' في register-verify.blade.php
        ],[
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);

        $mobileNumberFromInput = $request->input('mobile_number'); // الرقم من الحقل المخفي
        $registrationData = Session::get('registration_data');

        // التحقق من تطابق رقم الجوال في الجلسة مع الرقم المرسل من النموذج
        if (!$registrationData || $registrationData['mobile_number'] !== $mobileNumberFromInput) {
            return redirect()->route('register.verify.form')
                ->with('mobile_number', $mobileNumberFromInput) // إعادة إرسال الرقم للنموذج
                ->with('error', 'بيانات الجلسة غير متطابقة. حاول مرة أخرى.');
        }
        
        if ($validator->fails()) {
            return redirect()->route('register.verify.form')
                ->withErrors($validator)
                ->withInput() // إعادة المدخلات السابقة
                ->with('mobile_number', $mobileNumberFromInput); // إعادة تمرير الرقم للواجهة
        }
        
        $otpCode = $request->input('otp');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumberFromInput}' (registration).");

        if (!$this->verifyOtpForMobile($mobileNumberFromInput, $otpCode, 'registration')) {
            return redirect()->route('register.verify.form')
                ->with('error', 'رمز التحقق غير صحيح أو انتهت صلاحيته.')
                ->withInput()
                ->with('mobile_number', $mobileNumberFromInput);
        }

        // إنشاء المستخدم بعد التحقق الناجح
        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'mobile_number' => $mobileNumberFromInput,
            'password' => Hash::make(Str::random(16)), // كلمة مرور عشوائية قوية
            'is_admin' => false,
            'mobile_verified_at' => now(),
        ]);

        Session::forget('registration_data'); // تنظيف بيانات الجلسة
        Log::info("User {$user->id} ({$user->email}) created and verified successfully via OTP registration.");

        Auth::login($user); // تسجيل دخول المستخدم الجديد
        $request->session()->regenerate();

        $redirectPath = $user->is_admin ? route('admin.dashboard') : route('customer.dashboard');
        return redirect()->intended($redirectPath)
            ->with('success', 'تم تسجيل حسابك بنجاح!');
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('mobile_number') ?? 'رقم الجوال غير صالح.'
            ], 422);
        }

        $mobileNumber = $request->input('mobile_number');

        // التأكد من وجود بيانات تسجيل لهذا الرقم في الجلسة
        $registrationData = Session::get('registration_data');
        if (!$registrationData || $registrationData['mobile_number'] !== $mobileNumber) {
            Log::warning("Resend OTP attempt for non-matching or missing registration session for {$mobileNumber}.");
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إعادة إرسال الرمز. يرجى التأكد من أنك بدأت عملية التسجيل أو حاول البدء من جديد.'
            ], 400); // Bad Request
        }

        try {
            $this->generateAndSendOtp($mobileNumber, 'registration_resend'); // يمكنك استخدام غرض مختلف للتتبع
            Log::info("Resent OTP for registration to {$mobileNumber}.");
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة إرسال رمز تحقق جديد بنجاح.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resend OTP: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }
}
