<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SmsSettingController extends Controller
{
    private array $smsSettingKeys = [
        'sms_default_provider',
        'sms_otp_provider',
        
        'httpsms_api_key',
        'httpsms_sender_phone',
        
        'smsgateway_device_id',
        'smsgateway_server_url',
        'smsgateway_api_token',

        // --- MODIFICATION START: Twilio Verify Settings ---
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_verify_sid', // تم استبدال twilio_sms_from_number بهذا
        // --- MODIFICATION END ---
    ];

    public function edit()
    {
        $settingsCollection = Setting::whereIn('key', $this->smsSettingKeys)->get();
        $settingsData = [];

        foreach ($settingsCollection as $setting) {
            $settingsData[$setting->key] = $setting->value;
        }
        
        foreach ($this->smsSettingKeys as $key) {
            if (!isset($settingsData[$key])) {
                $settingsData[$key] = '';
            }
        }

        $availableProviders = [
            'none' => 'تعطيل / عدم الإرسال',
            'httpsms' => 'HTTPSMS.com',
            'smsgateway' => 'SMS Gateway App (Android)',
            'twilio' => 'Twilio Verify', // تم تحديث الاسم ليعكس الخدمة
        ];
        
        if (empty($settingsData['sms_default_provider'])) {
            $settingsData['sms_default_provider'] = 'httpsms';
        }
        if (empty($settingsData['sms_otp_provider'])) {
            $settingsData['sms_otp_provider'] = 'httpsms';
        }

        return view('admin.settings.sms-edit', compact('settingsData', 'availableProviders'));
    }

    public function update(Request $request)
    {
        $providerValues = ['none', 'httpsms', 'smsgateway', 'twilio'];

        $rules = [
            'sms_default_provider' => ['required', 'string', Rule::in($providerValues)],
            'sms_otp_provider' => ['required', 'string', Rule::in($providerValues)],
            
            'httpsms_api_key' => 'nullable|string|max:255',
            'httpsms_sender_phone' => 'nullable|string|max:20',
            
            'smsgateway_device_id' => 'nullable|string|max:255',
            'smsgateway_server_url' => 'nullable|string|url|max:255',
            'smsgateway_api_token' => 'nullable|string|max:255',

            // --- MODIFICATION START: Twilio Verify Validation ---
            'twilio_account_sid' => 'nullable|string|max:255|regex:/^AC[a-f0-9]{32}$/', // نمط SID
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_verify_sid' => 'nullable|string|max:255|regex:/^VA[a-f0-9]{32}$/', // نمط Verify Service SID
            // --- MODIFICATION END ---
        ];

        $messages = [
            'sms_default_provider.in' => 'قيمة مزود الخدمة العامة غير صالحة.',
            'sms_otp_provider.in' => 'قيمة مزود خدمة OTP غير صالحة.',
            'httpsms_sender_phone.max' => 'رقم هاتف مرسل httpsms طويل جداً.',
            // --- MODIFICATION START: Twilio Verify Messages ---
            'twilio_account_sid.regex' => 'صيغة Twilio Account SID غير صحيحة (يجب أن يبدأ بـ AC).',
            'twilio_verify_sid.regex' => 'صيغة Twilio Verify Service SID غير صحيحة (يجب أن يبدأ بـ VA).',
            // --- MODIFICATION END ---
            'smsgateway_server_url.url' => 'رابط خادم SMS Gateway يجب أن يكون رابطاً صحيحاً.',
        ];

        $validatedData = $request->validate($rules, $messages);

        try {
            foreach ($this->smsSettingKeys as $key) {
                if ($request->has($key)) {
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $request->input($key)]
                    );
                }
            }
            Log::info('SMS settings updated by admin ID: ' . auth()->id(), ['settings_updated' => $validatedData]);
            return redirect()->route('admin.settings.sms.edit')->with('success', 'تم تحديث إعدادات الرسائل النصية بنجاح.');
        } catch (\Exception $e) {
            Log::error('Failed to update SMS settings: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['_token', '_method', 'twilio_auth_token', 'httpsms_api_key'])
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات: حدث خطأ ما.')->withInput();
        }
    }
}
