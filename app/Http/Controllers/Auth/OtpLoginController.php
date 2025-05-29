<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Http\Traits\ManagesOtp; // <-- *** استخدام الـ Trait ***

class OtpLoginController extends Controller
{
    use ManagesOtp; // <-- *** استخدام الـ Trait ***

    public function showLoginForm()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }
        return view('auth.otp-login');
    }

    public function showOtpForm() // لا تحتاج Request هنا إذا كنت تعتمد على الجلسة أو query param
    {
        $mobileNumber = Session::get('otp_mobile_for_verification') ?? request()->query('mobile_number');
        if (!$mobileNumber) {
            return redirect()->route('login.otp.form')->with('error', 'يرجى إدخال رقم الجوال أولاً.');
        }
        return view('auth.otp-verify', compact('mobileNumber'));
    }

    public function requestOtp(Request $request)
    {
        $validator = Validator::make(<span class="math-inline">request\-\>all\(\), \[
'mobile\_number' \=\> \['required', 'string', 'regex\:/^05\\d\{8\}</span>/'],
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
        
        $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'login');

        if (!$otpSentSuccessfully && (Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none') !== 'none') {
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة لاحقاً أو التواصل مع الدعم.'
            ], 500);
        }

        Session::put('otp_mobile_for_verification', $mobileNumber);
        Log::info("Login OTP requested for {$mobileNumber}. User will be prompted for OTP.");
        
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى جوالك.'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make(<span class="math-inline">request\-\>all\(\), \[
'mobile\_number\_hidden' \=\> \['required', 'regex\:/^05\\d\{8\}</span>/'],
            'otp_code' => ['required', 'string', 'digits:4'],
        ],[
            'mobile_number_hidden.required' => 'رقم الجوال مطلوب للتحقق.', // أو تأكد من وجوده في الجلسة
            'otp_code.required' => 'رمز التحقق مطلوب.',
            'otp_code.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);

        $mobileNumber = $request->input('mobile_number_hidden') ?? Session::get('otp_mobile_for_verification');

        if ($validator->fails() || !$mobileNumber) {
            return redirect()->route('login.otp.verify.form')
                ->withErrors($validator)
                ->withInput($request->only('mobile_number_hidden'))
                ->with('mobile_number', $mobileNumber);
        }
        
        $otpCode = $request->input('otp_code');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumber}' (login).");

        if ($this->verifyOtpForMobile($mobileNumber, $otpCode, 'login')) {
            $user = User::where('mobile_number', $mobileNumber)->first();
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                Session::forget('otp_mobile_for_verification');
                Session::forget('twilio_otp_e164_mobile_for_verification'); // تنظيف إذا استخدم Twilio
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
