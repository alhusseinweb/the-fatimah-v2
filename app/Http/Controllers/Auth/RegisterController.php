<?php

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
use App\Http\Traits\ManagesOtp; // <-- *** استخدام الـ Trait ***
use App\Models\Setting; // <-- استيراد Setting

class RegisterController extends Controller
{
    use ManagesOtp; // <-- *** استخدام الـ Trait ***

    public function showRegistrationForm(Request $request)
    {
        $mobileNumber = $request->query('mobile_number', old('mobile_number'));
        return view('auth.register', ['mobile_number' => $mobileNumber]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make(<span class="math-inline">request\-\>all\(\), \[
'name' \=\> \['required', 'string', 'max\:255'\],
'email' \=\> \['required', 'string', 'email', 'max\:255', 'unique\:users,email'\],
'mobile\_number' \=\> \['required', 'string', 'regex\:/^05\\d\{8\}</span>/', 'unique:users,mobile_number'],
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
                ->route('register')
                ->withErrors($validator)
                ->withInput();
        }
        
        $mobileNumber = $request->input('mobile_number');

        Session::put('registration_data', [
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $mobileNumber,
        ]);
        Session::put('otp_mobile_for_verification', $mobileNumber); // لتمريرها لصفحة التحقق

        try {
            Log::info('Starting registration OTP process for: ' . $mobileNumber);
            $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'registration');

            if (!$otpSentSuccessfully && (Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none') !== 'none') {
                Session::forget('registration_data');
                Session::forget('otp_mobile_for_verification');
                return redirect()->route('register')
                    ->withInput()
                    ->with('error', 'فشل في إرسال رمز التحقق. يرجى المحاولة لاحقاً أو التواصل مع الدعم.');
            }
            
            return redirect()->route('register.verify.form')->with('mobile_number', $mobileNumber);

        } catch (\Exception $e) { // قد لا يتم الوصول لهذا إذا عالجت generateAndSendOtp كل الأخطاء
            Log::error('Failed to send OTP during registration: ' . $e->getMessage());
            Session::forget('registration_data');
            Session::forget('otp_mobile_for_verification');
            return redirect()->route('register')
                ->withInput()
                ->with('error', 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.');
        }
    }

    public function showVerifyForm(Request $request)
    {
        $mobileNumber = $request->query('mobile_number', Session::get('otp_mobile_for_verification'));
        $registrationData = Session::get('registration_data');

        if (!Session::has('registration_data') || !$mobileNumber || $registrationData['mobile_number'] !== $mobileNumber) {
            return redirect()->route('register')
                ->with('error', 'يرجى إدخال بياناتك أولاً أو انتهت صلاحية الجلسة.');
        }
        return view('auth.register-verify', ['mobile_number' => $mobileNumber]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make(<span class="math-inline">request\-\>all\(\), \[
'mobile\_number' \=\> \['required', 'string', 'regex\:/^05\\d\{8\}</span>/'],
            'otp' => ['required', 'string', 'digits:4'],
        ],[
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);

        $mobileNumberFromInput = $request->input('mobile_number');
        $registrationData = Session::get('registration_data');

        if (!$registrationData || !isset($registrationData['mobile_number']) || $registrationData['mobile_number'] !== $mobileNumberFromInput) {
            return redirect()->route('register.verify.form')
                ->with('mobile_number', $mobileNumberFromInput)
                ->with('error', 'بيانات الجلسة غير متطابقة أو مفقودة. حاول مرة أخرى.');
        }
        
        if ($validator->fails()) {
            return redirect()->route('register.verify.form')
                ->withErrors($validator)
                ->withInput()
                ->with('mobile_number', $mobileNumberFromInput);
        }
        
        $otpCode = $request->input('otp');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumberFromInput}' (registration).");

        if (!$this->verifyOtpForMobile($mobileNumberFromInput, $otpCode, 'registration')) {
            return redirect()->route('register.verify.form')
                ->with('error', 'رمز التحقق غير صحيح أو انتهت صلاحيته.')
                ->withInput()
                ->with('mobile_number', $mobileNumberFromInput);
        }

        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'mobile_number' => $mobileNumberFromInput,
            'password' => Hash::make(Str::random(16)),
            'is_admin' => false,
            'mobile_verified_at' => now(),
        ]);

        Session::forget('registration_data');
        Session::forget('otp_mobile_for_verification');
        Session::forget('twilio_otp_e164_mobile_for_verification'); // تنظيف إذا استخدم Twilio
        Log::info("User {$user->id} ({$user->email}) created and verified successfully via OTP registration.");

        Auth::login($user);
        $request->session()->regenerate();

        $redirectPath = $user->is_admin ? route('admin.dashboard') : route('customer.dashboard');
        return redirect()->intended($redirectPath)
            ->with('success', 'تم تسجيل حسابك بنجاح!');
    }
    
    public function resendOtp(Request $request)
    {
        $validator = Validator::make(<span class="math-inline">request\-\>all\(\), \[
'mobile\_number' \=\> \['required', 'string', 'regex\:/^05\\d\{8\}</span>/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('mobile_number') ?? 'رقم الجوال غير صالح.'
            ], 422);
        }

        $mobileNumber = $request->input('mobile_number');
        $registrationData = Session::get('registration_data');

        if (!$registrationData || !isset($registrationData['mobile_number']) || $registrationData['mobile_number'] !== $mobileNumber) {
            Log::warning("Resend OTP attempt for non-matching or missing registration session for {$mobileNumber}.");
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إعادة إرسال الرمز. يرجى التأكد من أنك بدأت عملية التسجيل أو حاول البدء من جديد.'
            ], 400);
        }

        try {
            $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'registration_resend');
            
            if (!$otpSentSuccessfully && (Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none') !== 'none') {
                 return response()->json([
                    'success' => false,
                    'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة لاحقاً.'
                ], 500);
            } elseif ($otpSentSuccessfully) {
                Log::info("Resent OTP for registration to {$mobileNumber}.");
                return response()->json([
                    'success' => true,
                    'message' => 'تم إعادة إرسال رمز تحقق جديد بنجاح.'
                ]);
            } else { // $otpSentSuccessfully is false and provider is 'none'
                 Log::info("OTP Resend skipped as provider is 'none' for {$mobileNumber}.");
                 return response()->json([
                    'success' => false, // أو true مع رسالة توضيحية أن الإرسال معطل
                    'message' => 'خدمة إرسال الرموز معطلة حالياً.'
                ], 200); // أو 503
            }

        } catch (\Exception $e) {
            Log::error('Failed to resend OTP: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }
}
