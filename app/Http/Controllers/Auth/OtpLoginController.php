<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Http\Traits\ManagesOtp;
use App\Models\Setting;
use Illuminate\Support\Facades\Route; // <-- *** إضافة استيراد Route Facade ***

class OtpLoginController extends Controller
{
    use ManagesOtp;

    // ... (دوال showLoginForm, showOtpForm, requestOtp تبقى كما هي من الرد السابق) ...
    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }
        return view('auth.otp-login');
    }

    public function showOtpForm(Request $request)
    {
        $mobileNumber = $request->query('mobile_number', Session::get('otp_mobile_for_verification'));
        if (!$mobileNumber) {
            return redirect()->route('login.otp.form')->with('error', 'يرجى إدخال رقم الجوال أولاً.');
        }
        Session::put('otp_mobile_for_verification', $mobileNumber);
        return view('auth.otp-verify', compact('mobileNumber'));
    }

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
                'message' => 'no_account_found',
                'mobile_number' => $mobileNumber
            ], 404);
        }
        
        $otpPurpose = 'login';
        $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, $otpPurpose); // من الـ Trait

        $selectedOtpProvider = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';

        if (!$otpSentSuccessfully && $selectedOtpProvider !== 'none') {
            Log::error("Login OTP request: Failed to send OTP for {$mobileNumber} via provider '{$selectedOtpProvider}'.");
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. قد تكون هناك مشكلة في خدمة الرسائل أو الإعدادات. يرجى المحاولة لاحقاً أو التواصل مع الدعم.'
            ], 500);
        }
        
        Session::put('otp_mobile_for_verification', $mobileNumber);
        Log::info("Login OTP process initiated for {$mobileNumber}. User will be prompted for OTP. Provider: {$selectedOtpProvider}");
        
        $responseMessage = 'تم إرسال رمز التحقق إلى جوالك.';
        if ($selectedOtpProvider === 'whatsapp') {
            $responseMessage = 'تم إرسال رمز التحقق إلى رقمك عبر الواتساب (WhatsApp).';
        } elseif ($selectedOtpProvider === 'none' && $otpSentSuccessfully === false) {
            $responseMessage = 'خدمة إرسال OTP معطلة حالياً (لأغراض الاختبار، تحقق من السجلات).';
        }

        return response()->json([
            'success' => true,
            'message' => $responseMessage,
            'provider' => $selectedOtpProvider
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

        $mobileNumberFromSession = Session::get('otp_mobile_for_verification');
        $mobileNumberFromInput = $request->input('mobile_number_hidden');

        if (!$mobileNumberFromSession || ($mobileNumberFromInput && $mobileNumberFromSession !== $mobileNumberFromInput)) {
            Log::warning("OTP Verification: Mobile number mismatch between session and input or session missing.", [
                'session_mobile' => $mobileNumberFromSession, 'input_mobile' => $mobileNumberFromInput
            ]);
            return redirect()->route('login.otp.form')->with('error', 'جلسة التحقق غير صالحة. يرجى طلب رمز جديد.');
        }
        
        $mobileNumber = $mobileNumberFromSession;

        if ($validator->fails()) {
            return redirect()->route('login.otp.verify.form')
                ->withErrors($validator)
                ->withInput($request->only('mobile_number_hidden'))
                ->with('mobile_number', $mobileNumber);
        }
        
        $otpCode = $request->input('otp_code');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumber}' (login).");

        if ($this->verifyOtpForMobile($mobileNumber, $otpCode, 'login')) { // من الـ Trait
            $user = User::where('mobile_number', $mobileNumber)->first();
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                Session::forget('otp_mobile_for_verification');
                Session::forget('twilio_otp_e164_mobile_for_verification');
                Log::info("User {$user->id} ({$user->email}) logged in successfully via OTP.");
                return $this->redirectBasedOnRole($user); // <-- هنا سيتم استدعاء الدالة المصححة
            }
            Log::error("OTP verified for {$mobileNumber}, but user could not be found for login during verifyOtp.");
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'حدث خطأ غير متوقع. حاول مرة أخرى.')
                             ->with('mobile_number', $mobileNumber);
        } else {
            Log::warning("Login OTP verification failed for {$mobileNumber}.");
            return redirect()->route('login.otp.verify.form')
                             ->with('error', 'رمز التحقق الذي أدخلته غير صحيح أو انتهت صلاحيته.')
                             ->withInput($request->except('otp_code'))
                             ->with('mobile_number', $mobileNumber);
        }
    }

    public function resendOtpViaSms(Request $request)
    {
        $mobileNumber = $request->input('mobile_number_hidden') ?: Session::get('otp_mobile_for_verification');
        
        if (!$mobileNumber) {
            return response()->json(['success' => false, 'message' => 'جلسة التحقق غير صالحة.'], 400);
        }

        Log::info("User requested SMS fallback (Twilio) for OTP to {$mobileNumber}.");

        // فرض استخدام Twilio لإعادة الإرسال عبر SMS
        $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'login_sms_fallback', 'twilio');

        if ($otpSentSuccessfully) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة إرسال رمز التحقق عبر رسالة نصية (Twilio) بنجاح.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'فشل في إرسال الرسالة النصية. يرجى المحاولة لاحقاً.'
        ], 500);
    }

    public function logout(Request $request)
    {
        $userName = Auth::user() ? Auth::user()->name : 'User';
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Log::info("User {$userName} logged out.");
        return redirect('/');
    }

    protected function redirectBasedOnRole(User $user)
    {
        // --- MODIFICATION START: Use Route::has() ---
        $adminRouteName = 'admin.dashboard';
        $customerRouteName = 'customer.dashboard';

        $adminRoute = Route::has($adminRouteName) ? route($adminRouteName) : '/admin/dashboard';
        $customerRoute = Route::has($customerRouteName) ? route($customerRouteName) : '/customer/dashboard';
        // --- MODIFICATION END ---
        
        $redirectPath = $user->is_admin ? $adminRoute : $customerRoute;
        Log::info("Redirecting user {$user->id} to: {$redirectPath}");
        return redirect()->intended($redirectPath);
    }
}
