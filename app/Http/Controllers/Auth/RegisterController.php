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
use App\Models\Setting; 

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
        // *** التصحيح هنا ***
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // يجب أن يكون التعبير النمطي صحيحًا. للتأكد من أنه يبدأ بـ 05 ويتبعه 8 أرقام:
            'mobile_number' => ['required', 'string', 'regex:/^05[0-9]{8}$/', 'unique:users,mobile_number'], 
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
                ->route('register') // يفترض أن هذا هو المسار الصحيح لنموذج التسجيل
                ->withErrors($validator)
                ->withInput();
        }
        
        $mobileNumber = $request->input('mobile_number');

        Session::put('registration_data', [
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $mobileNumber,
        ]);
        Session::put('otp_mobile_for_verification', $mobileNumber);

        try {
            Log::info('Starting registration OTP process for: ' . $mobileNumber);
            $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'registration'); // استخدام purpose 'registration'

            // التحقق مما إذا كان مزود خدمة الرسائل معطل (none)
            $otpProvider = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';

            if (!$otpSentSuccessfully && $otpProvider !== 'none') {
                Session::forget('registration_data');
                Session::forget('otp_mobile_for_verification');
                return redirect()->route('register')
                    ->withInput()
                    ->with('error', 'فشل في إرسال رمز التحقق. يرجى المحاولة لاحقاً أو التواصل مع الدعم.');
            } elseif ($otpProvider === 'none' && !$otpSentSuccessfully) {
                // إذا كان المزود 'none' ولم يتم إرسال الرمز (لأنه لن يتم محاولة الإرسال)
                // قد ترغب في تسجيل هذا أو المتابعة إلى صفحة التحقق مع رسالة توضيحية
                Log::info("Registration OTP sending skipped for {$mobileNumber} as OTP provider is 'none'. User will proceed to verify (if OTP can be manually obtained).");
            }
            
            return redirect()->route('register.verify.form')->with('mobile_number', $mobileNumber);

        } catch (\Exception $e) {
            Log::error('Failed to send OTP during registration: ' . $e->getMessage(), ['exception' => $e]);
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

        if (!$registrationData || !$mobileNumber || ($registrationData['mobile_number'] ?? null) !== $mobileNumber) {
            return redirect()->route('register')
                ->with('error', 'يرجى إدخال بياناتك أولاً أو انتهت صلاحية الجلسة.');
        }
        return view('auth.register-verify', ['mobile_number' => $mobileNumber]);
    }

    public function verifyOtp(Request $request)
    {
        // *** التصحيح هنا ***
        $validator = Validator::make($request->all(), [ 
            'mobile_number' => ['required', 'string', 'regex:/^05[0-9]{8}$/'],
            'otp' => ['required', 'string', 'digits:4'], // افترضنا أن طول OTP هو 4 أرقام
        ],[
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
            'mobile_number.required' => 'رقم الجوال مطلوب للتحقق.',
            'mobile_number.regex' => 'صيغة رقم الجوال غير صحيحة.',
        ]);

        $mobileNumberFromInput = $request->input('mobile_number');
        $registrationData = Session::get('registration_data');

        if (!$registrationData || !isset($registrationData['mobile_number']) || $registrationData['mobile_number'] !== $mobileNumberFromInput) {
            return redirect()->route('register.verify.form') 
                ->with('mobile_number', $mobileNumberFromInput) // تمرير رقم الجوال مرة أخرى للنموذج
                ->withErrors(['otp' => 'بيانات الجلسة غير متطابقة أو مفقودة. حاول البدء من جديد.']) // استخدام withErrors أفضل
                ->withInput();
        }
        
        if ($validator->fails()) {
            return redirect()->route('register.verify.form')
                ->withErrors($validator)
                ->withInput()
                ->with('mobile_number', $mobileNumberFromInput);
        }
        
        $otpCode = $request->input('otp');

        Log::info("Attempting to verify OTP '{$otpCode}' for mobile '{$mobileNumberFromInput}' (registration).");

        if (!$this->verifyOtpForMobile($mobileNumberFromInput, $otpCode, 'registration')) { // استخدام purpose 'registration'
            return redirect()->route('register.verify.form')
                ->with('error', 'رمز التحقق غير صحيح أو انتهت صلاحيته.')
                ->withInput()
                ->with('mobile_number', $mobileNumberFromInput);
        }

        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'mobile_number' => $mobileNumberFromInput,
            'password' => Hash::make(Str::random(16)), // كلمة مرور عشوائية قوية
            'is_admin' => false, // المستخدمون الجدد ليسوا مدراء بشكل افتراضي
            'mobile_verified_at' => now(), // توثيق رقم الجوال
        ]);

        Session::forget('registration_data');
        Session::forget('otp_mobile_for_verification');
        Session::forget($this->getOtpCacheKey($mobileNumberFromInput, 'registration')); // مسح OTP المستخدم من الكاش
        Session::forget($this->getOtpAttemptCacheKey($mobileNumberFromInput, 'registration')); // مسح محاولات OTP


        Log::info("User {$user->id} ({$user->email}) created and verified successfully via OTP registration.");

        Auth::login($user);
        $request->session()->regenerate(); // مهم لأمان الجلسة

        $redirectPath = $user->is_admin ? route('admin.dashboard') : route('customer.dashboard');
        return redirect()->intended($redirectPath)
            ->with('success', 'تم تسجيل حسابك بنجاح!');
    }
    
    public function resendOtp(Request $request)
    {
        // *** التصحيح هنا ***
        $validator = Validator::make($request->all(), [ 
            'mobile_number' => ['required', 'string', 'regex:/^05[0-9]{8}$/'],
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
            ], 400); // Bad Request
        }

        try {
            // استخدام purpose مختلف لإعادة الإرسال إذا أردت تتبعها بشكل مختلف أو تطبيق قواعد مختلفة
            $otpSentSuccessfully = $this->generateAndSendOtp($mobileNumber, 'registration_resend'); 
            
            $otpProvider = Setting::where('key', 'sms_otp_provider')->value('value') ?? 'none';

            if (!$otpSentSuccessfully && $otpProvider !== 'none') {
                 return response()->json([
                    'success' => false,
                    'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة لاحقاً.'
                ], 500); // Internal Server Error
            } elseif ($otpSentSuccessfully) {
                Log::info("Resent OTP for registration to {$mobileNumber}.");
                return response()->json([
                    'success' => true,
                    'message' => 'تم إعادة إرسال رمز تحقق جديد بنجاح.'
                ]);
            } else { // $otpSentSuccessfully is false and provider is 'none'
                Log::info("OTP Resend skipped as provider is 'none' for {$mobileNumber}.");
                return response()->json([
                    'success' => true, //  أو false مع رسالة توضيحية أن الإرسال معطل
                    'message' => 'خدمة إرسال الرموز معطلة حالياً. تم إنشاء رمز جديد ويمكنك الحصول عليه يدوياً إذا كنت تقوم بالاختبار.' // رسالة أوضح للاختبار
                ], 200); 
            }

        } catch (\Exception $e) {
            Log::error('Failed to resend OTP: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }
}
