<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Http\Traits\ManagesOtp; // <-- *** استيراد الـ Trait ***
use App\Models\Setting; // لاستخدامه في التحقق من إعدادات المزود قبل إرجاع الخطأ

class OtpLoginController extends Controller
{
    use ManagesOtp; // <-- *** استخدام الـ Trait ***

    /**
     * عرض نموذج إدخال رقم الجوال لتسجيل الدخول.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }
        return view('auth.otp-login');
    }

    /**
     * عرض نموذج إدخال رمز التحقق OTP.
     */
    public function showOtpForm(Request $request) // أضفت Request هنا للوصول إلى query parameter
    {
        // محاولة الحصول على رقم الجوال من query parameter أولاً، ثم من الجلسة
        $mobileNumber = $request->query('mobile_number', Session::get('otp_mobile_for_verification'));

        if (!$mobileNumber) {
            return redirect()->route('login.otp.form')->with('error', 'يرجى إدخال رقم الجوال أولاً.');
        }
        // تخزين الرقم في الجلسة إذا جاء من query parameter لضمان استمراريته في صفحة التحقق
        Session::put('otp_mobile_for_verification', $mobileNumber);

        return view('auth.otp-verify', compact('mobileNumber'));
    }

    /**
     * معالجة طلب إرسال رمز OTP إلى رقم الجوال.
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/'],
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
                'message' => 'no_account_found', // للتعامل معها في الواجهة الأمامية لعرض مودال إنشاء حساب
                'mobile_number' => $mobileNumber
            ], 404); // Not Found
        }
        
        // (اختياري) يمكنك إضافة تحقق هنا إذا كان حساب المستخدم نشطاً أم لا
        // if (!$user->is_active) { 
        //     return response()->json(['success' => false, 'message' => 'هذا الحساب غير نشط.'], 403); // Forbidden
        // }

        $otpPurpose = 'login';
        $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, $otpPurpose);

        $selectedOtpProvider = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';

        if (!$otpSentSuccessfully && $selectedOtpProvider !== 'none') {
            // فشل إرسال الـ OTP ولم يكن المزود معيناً على 'none' (تعطيل)
            Log::error("Login OTP request: Failed to send OTP for {$mobileNumber} via provider '{$selectedOtpProvider}'.");
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. قد تكون هناك مشكلة في خدمة الرسائل أو الإعدادات. يرجى المحاولة لاحقاً أو التواصل مع الدعم.'
            ], 500); // خطأ في الخادم
        }
        
        // حتى لو كان المزود 'none'، سنقوم بتخزين الرقم في الجلسة للسماح للمستخدم بالانتقال لصفحة التحقق
        // (في هذه الحالة، يمكن للمطور إيجاد الرمز في السجلات للاختبار)
        Session::put('otp_mobile_for_verification', $mobileNumber);
        Log::info("Login OTP process initiated for {$mobileNumber}. User will be prompted for OTP. Provider: {$selectedOtpProvider}");
        
        $responseMessage = ($selectedOtpProvider === 'none' && $otpSentSuccessfully === false) // $otpSentSuccessfully سيكون false إذا كان المزود none
                            ? 'خدمة إرسال OTP معطلة حالياً (لأغراض الاختبار، تحقق من السجلات).'
                            : 'تم إرسال رمز التحقق إلى جوالك.';

        return response()->json([
            'success' => true, // نرجع true للسماح بالانتقال لصفحة التحقق، حتى لو كان الإرسال معطلاً (للاختبار)
            'message' => $responseMessage
        ]);
    }

    /**
     * معالجة التحقق من رمز OTP وتسجيل دخول المستخدم.
     */
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

        // محاولة استرداد رقم الجوال من الجلسة كقيمة افتراضية أكثر أماناً
        $mobileNumberFromSession = Session::get('otp_mobile_for_verification');
        $mobileNumberFromInput = $request->input('mobile_number_hidden');

        // التحقق من تطابق رقم الجوال في الجلسة مع الرقم المرسل من النموذج
        if (!$mobileNumberFromSession || ($mobileNumberFromInput && $mobileNumberFromSession !== $mobileNumberFromInput)) {
            Log::warning("OTP Verification: Mobile number mismatch between session and input or session missing.", [
                'session_mobile' => $mobileNumberFromSession,
                'input_mobile' => $mobileNumberFromInput
            ]);
            return redirect()->route('login.otp.form')->with('error', 'جلسة التحقق غير صالحة. يرجى طلب رمز جديد.');
        }
        
        // استخدام رقم الجوال من الجلسة كمصدر موثوق
        $mobileNumber = $mobileNumberFromSession;

        if ($validator->fails()) {
            return redirect()->route('login.otp.verify.form')
                ->withErrors($validator)
                ->withInput($request->only('mobile_number_hidden')) // للحفاظ على القيمة إذا كان هناك خطأ آخر
                ->with('mobile_number', $mobileNumber); // تمرير الرقم الصحيح للعرض
        }
        
        $otpCode = $request->input('otp_code');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumber}' (login).");

        if ($this->verifyOtpForMobile($mobileNumber, $otpCode, 'login')) {
            $user = User::where('mobile_number', $mobileNumber)->first();
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate(); // مهم لأمان الجلسة
                Session::forget('otp_mobile_for_verification'); // تنظيف الجلسة
                Session::forget('twilio_otp_e164_mobile_for_verification'); // تنظيف إذا استخدم Twilio
                Log::info("User {$user->id} ({$user->email}) logged in successfully via OTP.");
                return $this->redirectBasedOnRole($user);
            }
            // هذه الحالة نادرة جداً إذا تم التحقق من وجود المستخدم في requestOtp
            Log::error("OTP verified for {$mobileNumber}, but user could not be found for login during verifyOtp.");
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'حدث خطأ غير متوقع. حاول مرة أخرى.')
                             ->with('mobile_number', $mobileNumber);
        } else {
            Log::warning("Login OTP verification failed for {$mobileNumber}.");
            // لا تقم بإزالة otp_mobile_for_verification من الجلسة هنا للسماح بإعادة المحاولة على نفس الصفحة
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'رمز التحقق الذي أدخلته غير صحيح أو انتهت صلاحيته.')
                             ->withInput($request->except('otp_code')) // أعد ملء الرقم ولكن ليس الرمز الخاطئ
                             ->with('mobile_number', $mobileNumber);
        }
    }

    /**
     * تسجيل خروج المستخدم.
     */
    public function logout(Request $request)
    {
        $userName = Auth::user() ? Auth::user()->name : 'User';
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Log::info("User {$userName} logged out.");
        return redirect('/');
    }

    /**
     * إعادة توجيه المستخدم بعد تسجيل الدخول بناءً على دوره.
     */
    protected function redirectBasedOnRole(User $user)
    {
        // التأكد من أن المسارات موجودة
        $adminRoute = route_exists('admin.dashboard') ? route('admin.dashboard') : '/admin/dashboard';
        $customerRoute = route_exists('customer.dashboard') ? route('customer.dashboard') : '/customer/dashboard';
        
        $redirectPath = $user->is_admin ? $adminRoute : $customerRoute;
        return redirect()->intended($redirectPath);
    }
}
