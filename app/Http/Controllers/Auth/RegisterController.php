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
use Twilio\Rest\Client as TwilioClient;

class RegisterController extends Controller
{
    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle the initial registration step - validate inputs and send OTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function register(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_number' => ['required', 'string', 'unique:users', 'regex:/^05\d{8}$/'], // تأكد من صحة هذا النمط لرقم الجوال السعودي
        ], [
            'mobile_number.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
            'mobile_number.unique' => 'رقم الجوال مسجل مسبقاً.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('register')
                ->withErrors($validator)
                ->withInput();
        }

        // Store registration data in session to use after OTP verification
        Session::put('registration_data', [
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
        ]);

        // Send OTP using Twilio Verify
        try {
            Log::info('Starting registration OTP process for: ' . $request->mobile_number);
            $this->sendVerificationCode($request->mobile_number);

            // Redirect to OTP verification page
            return redirect()->route('register.verify.form')
                ->with('mobile_number', $request->mobile_number); // تمرير رقم الجوال للواجهة التالية
        } catch (\Exception $e) {
            Log::error('Failed to send OTP during registration: ' . $e->getMessage());

            return redirect()->route('register')
                ->withInput()
                ->with('error', 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.');
        }
    }

    /**
     * Show the OTP verification form for registration.
     *
     * @return \Illuminate\View\View
     */
    public function showVerifyForm()
    {
        // If no registration data in session, redirect back to registration
        if (!Session::has('registration_data')) {
            return redirect()->route('register')
                ->with('error', 'يرجى إدخال بياناتك أولاً');
        }

        $mobileNumber = Session::get('registration_data.mobile_number');
        // تأكد من تمرير المتغير للواجهة
        return view('auth.register-verify', ['mobile_number' => $mobileNumber]);
    }

    /**
     * Verify OTP and complete registration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyOtp(Request $request)
    {
        // Validate OTP
        // --- تعديل هنا: تغيير إلى size:4 ---
        $validator = Validator::make($request->all(), [
            'otp' => ['required', 'string', 'size:4'],
        ], [
            'otp.required' => 'حقل رمز التحقق مطلوب.',
            'otp.size' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);
        // --- نهاية التعديل ---

        if ($validator->fails()) {
            // تمرير رقم الجوال مرة أخرى للواجهة في حالة الخطأ
            $mobileNumber = Session::get('registration_data.mobile_number');
            return redirect()->route('register.verify.form')
                ->withErrors($validator)
                ->with('mobile_number', $mobileNumber); // إعادة تمرير الرقم للواجهة
        }

        // Get registration data from session
        if (!Session::has('registration_data')) {
            return redirect()->route('register')
                ->with('error', 'جلسة التسجيل منتهية. يرجى البدء من جديد.');
        }

        $registrationData = Session::get('registration_data');
        $mobileNumber = $registrationData['mobile_number'];

        // Verify OTP code using Twilio Verify
        if (!$this->verifyCode($mobileNumber, $request->otp)) {
            // تمرير رقم الجوال مرة أخرى للواجهة في حالة الخطأ
            return redirect()->route('register.verify.form')
                ->with('error', 'رمز التحقق غير صحيح. يرجى المحاولة مرة أخرى.')
                ->with('mobile_number', $mobileNumber); // إعادة تمرير الرقم للواجهة
        }

        // Create the user after successful verification
        $user = User::create([
            'name' => $registrationData['name'],
            'email' => $registrationData['email'],
            'mobile_number' => $mobileNumber,
            'password' => Hash::make(Str::random(10)), // Generate a random password
            'is_admin' => false, // Regular users are not admins by default
            'mobile_verified_at' => now(), // Set mobile verification timestamp
        ]);

        // Clean up session data
        Session::forget('registration_data');

        // Log the user in
        Auth::login($user);

        // Redirect to the intended page or a default page (e.g., customer dashboard)
        // تم تغيير التوجيه إلى لوحة تحكم العميل بدلاً من services.index
        $redirectPath = $user->is_admin ? '/admin/dashboard' : '/customer/dashboard'; // تأكد من مسار لوحة تحكم العميل
        return redirect()->intended($redirectPath) // استخدام intended للحفاظ على التوجيه السابق إن وجد
            ->with('success', 'تم تسجيل حسابك بنجاح!');
    }

    /**
     * Resend OTP code for registration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendOtp(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'mobile_number' => ['required', 'string', 'regex:/^05\d{8}$/'], // تأكد من صحة هذا النمط
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الجوال غير صالح'
            ], 422);
        }

        // Check if we have registration data in session (or just use the provided mobile number)
        // يمكنك الاعتماد على الرقم المرسل بدلاً من الجلسة هنا لتبسيط إعادة الإرسال
        $mobileNumber = $request->input('mobile_number');


        try {
            // Send new OTP
            $this->sendVerificationCode($mobileNumber);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رمز تحقق جديد بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resend OTP: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'فشل في إرسال رمز التحقق. يرجى المحاولة مرة أخرى.'
            ], 500);
        }
    }

    /**
     * Send verification code via Twilio Verify.
     *
     * @param  string  $mobileNumber
     * @return void
     * @throws \Exception
     */
    protected function sendVerificationCode($mobileNumber)
    {
        $formattedNumber = $this->formatPhoneNumberForTwilio($mobileNumber);

        try {
            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioVerifySid = config('services.twilio.verify_sid');

            if (empty($twilioSid) || empty($twilioToken) || empty($twilioVerifySid)) {
                Log::error('Twilio configuration is missing');
                throw new \Exception('Twilio configuration is incomplete');
            }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            // ملاحظة: تأكد من ضبط طول الرمز إلى 4 في إعدادات خدمة Twilio Verify
            $verification = $twilio->verify->v2->services($twilioVerifySid)
                ->verifications
                ->create($formattedNumber, "sms");

            Log::info('Twilio verification sent', [
                'mobile' => $mobileNumber,
                'formatted_mobile' => $formattedNumber,
                'status' => $verification->status
            ]);

            if ($verification->status !== 'pending') {
                // قد تحتاج لمعالجة حالات أخرى مثل 'canceled' إذا كان الرقم غير صالح
                 Log::warning('Twilio verification status not pending', ['status' => $verification->status, 'mobile' => $formattedNumber]);
                 // يمكنك رمي استثناء مخصص هنا إذا أردت
                 // throw new \Exception('Verification status not pending: ' . $verification->status);
            }
        } catch (\Exception $e) {
            Log::error('Error sending Twilio verification: ' . $e->getMessage(), [
                'mobile' => $mobileNumber,
                'formatted_mobile' => $formattedNumber ?? 'formatting_failed'
            ]);
            throw $e; // أعد رمي الاستثناء ليتم التقاطه في الدالة register أو resendOtp
        }
    }

    /**
     * Verify verification code via Twilio Verify.
     *
     * @param  string  $mobileNumber
     * @param  string  $code
     * @return bool
     */
    protected function verifyCode($mobileNumber, $code)
    {
        $formattedNumber = $this->formatPhoneNumberForTwilio($mobileNumber);

        try {
            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioVerifySid = config('services.twilio.verify_sid');

             if (empty($twilioSid) || empty($twilioToken) || empty($twilioVerifySid)) {
                 Log::error('Twilio configuration is missing for verification check');
                 return false;
             }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            $verificationCheck = $twilio->verify->v2->services($twilioVerifySid)
                ->verificationChecks
                ->create([
                    'to' => $formattedNumber,
                    'code' => $code
                ]);

            Log::info('Twilio verification check result', [
                'mobile' => $mobileNumber,
                'formatted_mobile' => $formattedNumber,
                'status' => $verificationCheck->status
            ]);

            return $verificationCheck->status === 'approved';
        } catch (\Exception $e) {
            // تجنب تسجيل الخطأ إذا كان "Not Found" لأنه يعني أن الرمز غير صحيح أو منتهي الصلاحية
            // Twilio throws a 404 Not Found exception for incorrect codes.
            if ($e->getCode() == 404) {
                Log::warning('Twilio verification check failed (Incorrect Code/Expired/Not Found)', [
                     'mobile' => $mobileNumber,
                     'formatted_mobile' => $formattedNumber ?? 'formatting_failed'
                 ]);
            } else {
                 Log::error('Error verifying Twilio code: ' . $e->getMessage(), [
                     'mobile' => $mobileNumber,
                     'formatted_mobile' => $formattedNumber ?? 'formatting_failed'
                 ]);
            }
            return false;
        }
    }

    /**
     * Format phone number for Twilio (E.164 format).
     *
     * @param  string  $mobileNumber
     * @return string
     */
    protected function formatPhoneNumberForTwilio($mobileNumber)
    {
        // إزالة أي محارف غير رقمية أولاً
        $mobileNumber = preg_replace('/[^0-9]/', '', $mobileNumber);

        // إذا كان الرقم يبدأ بـ 05 وطوله 10 أرقام (صيغة محلية سعودية)
        if (preg_match('/^05\d{8}$/', $mobileNumber)) {
            return '+966' . substr($mobileNumber, 1);
        }

         // إذا كان الرقم يبدأ بـ 5 وطوله 9 أرقام (صيغة محلية سعودية بدون 0)
        if (preg_match('/^5\d{8}$/', $mobileNumber)) {
             return '+966' . $mobileNumber;
        }

        // إذا كان الرقم يبدأ بـ 966 (قد يكون مع أو بدون + في الأصل)
        if (strpos($mobileNumber, '966') === 0) {
             // التأكد من إضافة + إذا لم تكن موجودة
            if (strpos($mobileNumber, '+') !== 0) {
                 return '+' . $mobileNumber;
            }
             return $mobileNumber; // موجودة بالفعل
        }

        // إذا لم يكن بصيغة سعودية معروفة، حاول إضافة + كافتراض
        if (strpos($mobileNumber, '+') !== 0) {
            return '+' . $mobileNumber;
        }

        // إذا كان بصيغة دولية أخرى تبدأ بـ + بالفعل
        return $mobileNumber;
    }
}