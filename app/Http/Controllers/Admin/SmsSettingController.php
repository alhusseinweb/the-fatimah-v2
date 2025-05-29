<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting; // تأكد من أن موديل Setting موجود ويستخدم لإدارة الإعدادات
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // لاستخدام Rule::in

class SmsSettingController extends Controller
{
    // قائمة بمفاتيح الإعدادات الخاصة بالـ SMS لتسهيل إدارتها
    // هذه المفاتيح يجب أن تكون معرفة في الواجهة (View) أيضاً
    private array $smsSettingKeys = [
        'sms_default_provider',         // المزود الافتراضي للرسائل العامة
        'sms_otp_provider',             // المزود لرسائل OTP

        // إعدادات HTTPSMS
        'httpsms_api_key',
        'httpsms_sender_phone',

        // إعدادات SMS Gateway App (Android) - افترضت هذه الحقول
        'smsgateway_device_id',         // قد يكون معرّف الجهاز أو رقم الهاتف الخاص بالبوابة
        'smsgateway_server_url',        // رابط الخادم لتطبيق البوابة
        'smsgateway_api_token',         // توكن المصادقة إذا كان مطلوباً

        // إعدادات Twilio (Programmable SMS)
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_sms_from_number',       // رقم Twilio المستخدم كمرسل
    ];

    /**
     * عرض نموذج تعديل إعدادات SMS.
     *
     * @return \Illuminate\View\View
     */
    public function edit()
    {
        $settingsCollection = Setting::whereIn('key', $this->smsSettingKeys)->get();
        $settingsData = [];

        // تحويل المجموعة إلى مصفوفة key => value
        foreach ($settingsCollection as $setting) {
            $settingsData[$setting->key] = $setting->value;
        }
        
        // تعيين قيم افتراضية إذا لم تكن موجودة في قاعدة البيانات
        foreach ($this->smsSettingKeys as $key) {
            if (!isset($settingsData[$key])) {
                $settingsData[$key] = ''; // قيمة افتراضية كسلسلة فارغة
            }
        }

        // قائمة المزودين للاختيار من بينهم في النموذج
        $availableProviders = [
            'none' => 'تعطيل / عدم الإرسال',
            'httpsms' => 'HTTPSMS.com',
            'smsgateway' => 'SMS Gateway App (Android)', // يمكنك تعديل الاسم ليتناسب مع ما تستخدمه
            'twilio' => 'Twilio (Programmable SMS)',
        ];
        
        // تعيين قيمة افتراضية لمحددات المزود إذا لم تكن معينة بعد
        if (empty($settingsData['sms_default_provider'])) {
            $settingsData['sms_default_provider'] = 'httpsms'; // أو 'none' كافتراضي أولي
        }
        if (empty($settingsData['sms_otp_provider'])) {
            $settingsData['sms_otp_provider'] = 'httpsms'; // أو 'none' كافتراضي أولي
        }


        return view('admin.settings.sms-edit', compact('settingsData', 'availableProviders'));
    }

    /**
     * تحديث إعدادات SMS في قاعدة البيانات.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $providerValues = ['none', 'httpsms', 'smsgateway', 'twilio'];

        $rules = [
            'sms_default_provider' => ['required', 'string', Rule::in($providerValues)],
            'sms_otp_provider' => ['required', 'string', Rule::in($providerValues)],
            
            'httpsms_api_key' => 'nullable|string|max:255',
            'httpsms_sender_phone' => 'nullable|string|max:20', // مثال: +9665XXXXXXXX
            
            'smsgateway_device_id' => 'nullable|string|max:255',
            'smsgateway_server_url' => 'nullable|string|url|max:255',
            'smsgateway_api_token' => 'nullable|string|max:255', // أو أي نوع آخر

            'twilio_account_sid' => 'nullable|string|max:255', // عادةً يبدأ بـ AC...
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_sms_from_number' => 'nullable|string|max:20', // مثال: +1234567890 أو اسم مرسل
        ];

        // رسائل التحقق المخصصة (اختياري)
        $messages = [
            'sms_default_provider.in' => 'قيمة مزود الخدمة العامة غير صالحة.',
            'sms_otp_provider.in' => 'قيمة مزود خدمة OTP غير صالحة.',
            'httpsms_sender_phone.max' => 'رقم هاتف مرسل httpsms طويل جداً.',
            'twilio_sms_from_number.max' => 'رقم Twilio المرسل طويل جداً.',
            'smsgateway_server_url.url' => 'رابط خادم SMS Gateway يجب أن يكون رابطاً صحيحاً.',
        ];

        $validatedData = $request->validate($rules, $messages);

        try {
            foreach ($this->smsSettingKeys as $key) {
                // حفظ القيمة فقط إذا كانت موجودة في الطلب، وإلا قد يحفظ null بشكل غير مقصود
                // إذا كان الحقل غير موجود في الطلب (مثلاً، لم يتم إرساله من النموذج)،
                // $request->input($key) ستكون null.
                // إذا أردت حذف المفتاح إذا كانت القيمة فارغة، ستحتاج لمنطق إضافي.
                // حالياً، سيتم حفظ القيمة كما هي (حتى لو كانت سلسلة فارغة).
                $valueToStore = $request->input($key, null); // قيمة افتراضية null إذا لم يكن موجوداً

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $valueToStore]
                );
            }
            Log::info('SMS settings updated by admin ID: ' . auth()->id(), ['settings_updated' => $validatedData]);
            return redirect()->route('admin.settings.sms.edit')->with('success', 'تم تحديث إعدادات الرسائل النصية بنجاح.');
        } catch (\Exception $e) {
            Log::error('Failed to update SMS settings: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['_token', '_method', 'twilio_auth_token']) // استثناء المعلومات الحساسة من السجل
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات: حدث خطأ ما.')->withInput();
        }
    }
}
