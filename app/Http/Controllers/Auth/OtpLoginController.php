<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Twilio\Rest\Client as TwilioClient; // استخدام اسم مستعار لتجنب التعارض
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log; // استيراد Log لتسجيل الأخطاء
use Illuminate\Validation\ValidationException;
use Exception; // لاستقبال الأخطاء العامة
use Illuminate\Support\Facades\Session; // تم إضافة استيراد Session

class OtpLoginController extends Controller
{
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
    public function showOtpForm()
    {
        // التحقق مما إذا كان رقم الجوال موجوداً في الجلسة
        if (!Session::has('mobile_for_verification')) {
             // إذا لم يكن موجوداً، أعد التوجيه لصفحة إدخال الجوال مع رسالة خطأ
            return redirect()->route('login.otp.form')->with('error', 'يرجى إدخال رقم الجوال أولاً.');
        }
         // قم بتمرير رقم الجوال إلى الواجهة ليظهر في حقل مخفي مثلاً
        $mobileNumber = Session::get('mobile_for_verification');
        return view('auth.otp-verify', compact('mobileNumber'));
    }


    /**
     * معالجة طلب إرسال رمز OTP إلى رقم الجوال.
     */
    public function requestOtp(Request $request)
    {
        // قواعد التحقق لرقم الجوال
        $request->validate([
            'mobile_number' => 'required|string|max:255', // أضف قواعد التحقق المناسبة لرقم الجوال
        ]);

        $mobileNumber = $request->mobile_number;

        // التحقق مما إذا كان المستخدم موجوداً في قاعدة البيانات
        $user = User::where('mobile_number', $mobileNumber)->first();

        if (!$user) {
            // إذا لم يتم العثور على المستخدم، قم بإرجاع استجابة JSON خاصة للواجهة الأمامية
            return response()->json([
                'success' => false,
                'message' => 'no_account_found', // رسالة مخصصة للتعرف عليها في الواجهة الأمامية
                'mobile_number' => $mobileNumber, // إرسال رقم الجوال
            ], 404); // استخدام 404 أو 409 Conflict
        }

        // --- إذا تم العثور على المستخدم، تابع هنا لإرسال رمز التحقق ---
        try {
            // تهيئة عميل Twilio باستخدام بيانات الاعتماد من ملف config/services.php
            $twilio = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            // تنسيق رقم الجوال ليطابق صيغة E.164 المطلوبة من Twilio (مثال: +9665xxxxxxxx)
            $formattedMobile = $this->formatMobileForTwilio($mobileNumber);

            // إرسال رمز التحقق باستخدام خدمة Twilio Verify
            // ملاحظة: تأكد من ضبط طول الرمز إلى 4 في إعدادات خدمة Twilio Verify
            $verification = $twilio->verify->v2->services(config('services.twilio.verify_sid'))
                ->verifications
                ->create($formattedMobile, "sms"); // إرسال الرمز كرسالة نصية

            // تسجيل نجاح طلب إرسال الرمز (للتشخيص)
            Log::info('Twilio verification request sent successfully to: ' . $formattedMobile);

            // تخزين رقم الجوال في الجلسة لخطوة التحقق التالية
            Session::put('mobile_for_verification', $mobileNumber);
            // يمكنك أيضاً تخزين $verification->sid إذا كنت تحتاجه لاحقاً للتحقق

            // إرجاع استجابة JSON بنجاح للواجهة الأمامية
            return response()->json([
                'success' => true,
                'message' => 'OTP requested successfully',
                'verification_sid' => $verification->sid, // إرجاع verification SID إذا لزم الأمر
            ], 200); // إرجاع رمز حالة 200 OK للدلالة على النجاح

        } catch (Exception $e) {
            // التعامل مع أي أخطاء تحدث أثناء الاتصال بـ Twilio أو إرسال الرمز
            Log::error('Twilio OTP Request Error: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());

            // إرجاع استجابة JSON بخطأ للواجهة الأمامية
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage(), // يمكن إرجاع رسالة الخطأ الفعلية للتنقيح في وضع التطوير
            ], 500); // إرجاع رمز حالة 500 Internal Server Error
        }
        // --- نهاية منطق إرسال رمز التحقق ---

    }

    /**
     * معالجة التحقق من رمز OTP وتسجيل دخول المستخدم.
     */
    public function verifyOtp(Request $request)
    {
        // قواعد التحقق لرمز التحقق
        $validator = Validator::make($request->all(), [
            // تأكد أن 'mobile_number_hidden' موجود في النموذج أو استخدم رقم الجوال من الجلسة
            'mobile_number_hidden' => 'required_without:mobile_for_verification', // مطلوب إذا لم يكن في الجلسة
            // --- تعديل هنا: تغيير إلى digits:4 ---
            'otp_code' => 'required|string|digits:4',
        ], [
            'mobile_number_hidden.required_without' => 'حدث خطأ، يرجى إعادة طلب الرمز أو التأكد من تمكين الكوكيز.',
            'otp_code.required' => 'حقل رمز التحقق مطلوب.',
            // --- تعديل هنا: تحديث رسالة الخطأ ---
            'otp_code.digits' => 'رمز التحقق يجب أن يتكون من 4 أرقام.',
        ]);
        // --- نهاية التعديل ---

        if ($validator->fails()) {
            // إعادة التوجيه مع الأخطاء والمدخلات القديمة (حافظ على رقم الجوال المخفي في الإدخال القديم)
            return redirect()->route('login.otp.verify.form')
                ->withErrors($validator)
                ->withInput($request->only('mobile_number_hidden'));
        }

        // الحصول على رقم الجوال (يفضل من الجلسة لتجنب التلاعب)
        $mobileNumber = Session::get('mobile_for_verification', $request->input('mobile_number_hidden'));
        $otpCode = $request->input('otp_code');
        $formattedMobile = $this->formatMobileForTwilio($mobileNumber); // تنسيق الرقم للتحقق

        // --- تسجيل معلومات محاولة التحقق (للتشخيص) ---
         Log::info('Attempting to verify OTP for: ' . $formattedMobile . ' with code: ' . $otpCode);
        // -------------------------------------------

        try {
            // تهيئة عميل Twilio
            $twilio = new TwilioClient(config('services.twilio.sid'), config('services.twilio.token'));

            // التحقق من رمز التحقق باستخدام خدمة Twilio Verify
            // إذا كنت قد خزنت verification_sid في الجلسة عند الطلب، يمكنك استخدامه هنا
            $verification_check = $twilio->verify->v2->services(config('services.twilio.verify_sid'))
                ->verificationChecks
                ->create(['to' => $formattedMobile, 'code' => $otpCode]); // التحقق باستخدام الرقم والرمز


            if ($verification_check->status == "approved") {
                // إذا تم التحقق بنجاح
                 Log::info('OTP verification successful for: ' . $formattedMobile); // تسجيل النجاح

                // البحث عن المستخدم
                $user = User::where('mobile_number', $mobileNumber)->first();

                 // التأكد من وجود المستخدم قبل تسجيل الدخول (احترازاً)
                 if(!$user){
                      Log::error('User not found after successful OTP verification for mobile: ' . $mobileNumber);
                       return redirect()->route('login.otp.form')->with('error', 'خطأ داخلي: المستخدم غير موجود بعد التحقق.');
                 }


                // تحديث حالة التحقق من رقم الجوال إذا لم يكن محدثاً بالفعل
                if (!$user->mobile_verified_at) {
                    $user->forceFill(['mobile_verified_at' => now()])->save();
                }

                // تسجيل دخول المستخدم
                Auth::login($user);
                $request->session()->regenerate(); // تجديد معرف الجلسة للحماية
                Session::forget('mobile_for_verification'); // إزالة رقم الجوال من الجلسة بعد التحقق

                // إعادة التوجيه بناءً على دور المستخدم
                return $this->redirectBasedOnRole($user);

            } else {
                // إذا فشل التحقق
                 Log::warning('OTP verification failed for: ' . $formattedMobile . ' with status: ' . $verification_check->status); // تسجيل الفشل
                 Session::keep('mobile_for_verification'); // حافظ على رقم الجوال في الجلسة لإعادة المحاولة

                // إعادة التوجيه إلى نموذج التحقق مع رسالة خطأ
                return redirect()->route('login.otp.verify.form')
                    ->with('error', 'رمز التحقق الذي أدخلته غير صحيح.')
                    ->withInput($request->only('mobile_number_hidden')); // حافظ على الرقم المخفي في الإدخال القديم
            }

        } catch (Exception $e) {
            // التعامل مع أي أخطاء تحدث أثناء الاتصال بـ Twilio أو التحقق
             // --- تسجيل الخطأ الفعلي بالتفصيل ---
             Log::error('Twilio OTP Verify Exception: ' . $e->getMessage() . ' - Mobile: ' . $mobileNumber . ' - Trace: ' . $e->getTraceAsString());
             // -----------------------------------
             Session::keep('mobile_for_verification'); // حافظ على رقم الجوال في الجلسة

            // إعادة التوجيه مع رسالة خطأ عامة
             return redirect()->route('login.otp.verify.form')
                 ->with('error', 'حدث خطأ أثناء التحقق من الرمز. يرجى المحاولة مرة أخرى.')
                 ->withInput($request->only('mobile_number_hidden')); // حافظ على الرقم المخفي في الإدخال القديم
        }
    }


    /**
     * تسجيل خروج المستخدم.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    /**
     * إعادة توجيه المستخدم بعد تسجيل الدخول بناءً على دوره.
     */
    protected function redirectBasedOnRole(User $user)
    {
        if ($user->is_admin) {
            return redirect()->intended('/admin/dashboard');
        }
        // مسار لوحة تحكم العميل الافتراضي
        return redirect()->intended('/customer/dashboard'); // تأكد من وجود هذا المسار أو قم بتغييره للمسار الصحيح
    }

    /**
     * تنسيق رقم الجوال ليطابق صيغة E.164 المطلوبة من Twilio.
     * يضيف رمز الدولة +966 إذا كان الرقم بصيغة محلية (05xxxx) أو (5xxxx).
     * يفترض أن الأرقام الأخرى هي بصيغة دولية مع رمز الدولة (مع أو بدون +).
     *
     * @param string $mobile الرقم المدخل من المستخدم.
     * @return string الرقم المنسق بصيغة E.164.
     */
    protected function formatMobileForTwilio(string $mobile): string
    {
         // إزالة أي محارف غير رقمية
         $mobile = preg_replace('/[^0-9]/', '', $mobile);

         // إذا كان الرقم يبدأ بـ 05 وطوله 10 أرقام (صيغة محلية سعودية)
        if (substr($mobile, 0, 2) === '05' && strlen($mobile) === 10) {
            return '+966' . substr($mobile, 1); // إزالة الصفر الأول وإضافة +966
        }
         // إذا كان الرقم يبدأ بـ 5 وطوله 9 أرقام (صيغة محلية سعودية بدون 0)
        if (substr($mobile, 0, 1) === '5' && strlen($mobile) === 9) {
             return '+966' . $mobile; // إضافة +966
        }
        // إذا كان الرقم يبدأ بـ 966 (صيغة دولية مع رمز الدولة)
        if (substr($mobile, 0, 3) === '966') {
             // التأكد من عدم وجود علامة + مزدوجة
            if (substr($mobile, 0, 1) !== '+') {
                 return '+' . $mobile;
            }
             return $mobile;
        }

         // كحالة افتراضية، حاول إضافة +
         // قد تحتاج لتحسين هذا المنطق بناءً على الدول التي تخدمها.
         if (substr($mobile, 0, 1) !== '+') {
             return '+' . $mobile;
         }
         return $mobile;

    }
}