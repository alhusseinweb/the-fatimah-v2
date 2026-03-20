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
        
        // --- MODIFICATION START: Twilio Verify Settings ---
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_verify_sid',
        
        'whatsapp_enabled',
        'whatsapp_green_api_id_instance',
        'whatsapp_green_api_api_token_instance',
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
            'whatsapp' => 'WhatsApp (Green API)',
            'twilio' => 'Twilio Verify',
        ];
        
        if (empty($settingsData['sms_default_provider'])) {
            $settingsData['sms_default_provider'] = 'whatsapp';
        }
        if (empty($settingsData['sms_otp_provider'])) {
            $settingsData['sms_otp_provider'] = 'whatsapp';
        }

        return view('admin.settings.sms-edit', compact('settingsData', 'availableProviders'));
    }

    public function update(Request $request)
    {
        $providerValues = ['none', 'whatsapp', 'twilio'];

        $rules = [
            'sms_default_provider' => ['required', 'string', Rule::in($providerValues)],
            'sms_otp_provider' => ['required', 'string', Rule::in($providerValues)],
            

            // --- MODIFICATION START: Twilio Verify Validation ---
            'twilio_account_sid' => 'nullable|string|max:255|regex:/^AC[a-f0-9]{32}$/', // نمط SID
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_verify_sid' => 'nullable|string|max:255|regex:/^VA[a-f0-9]{32}$/', // نمط Verify Service SID
            
            'whatsapp_enabled' => 'nullable|boolean',
            'whatsapp_green_api_id_instance' => 'nullable|string|max:255',
            'whatsapp_green_api_api_token_instance' => 'nullable|string|max:255',
        ];

        $messages = [
            'sms_default_provider.in' => 'قيمة مزود الخدمة العامة غير صالحة.',
            'sms_otp_provider.in' => 'قيمة مزود خدمة OTP غير صالحة.',
            // --- MODIFICATION START: Twilio Verify Messages ---
            'twilio_account_sid.regex' => 'صيغة Twilio Account SID غير صحيحة (يجب أن يبدأ بـ AC).',
            'twilio_verify_sid.regex' => 'صيغة Twilio Verify Service SID غير صحيحة (يجب أن يبدأ بـ VA).',
            // --- MODIFICATION END ---
        ];

        $validatedData = $request->validate($rules, $messages);

        try {
            foreach ($this->smsSettingKeys as $key) {
                if ($request->has($key)) {
                    $val = $request->input($key);
                    if ($key === 'whatsapp_enabled') {
                        $val = $request->boolean($key) ? '1' : '0';
                    }
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $val]
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
