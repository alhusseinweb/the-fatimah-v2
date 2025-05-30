<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan; // لاستخدام أوامر artisan لمسح الكاش
use Illuminate\Validation\Rule;


class SettingController extends Controller
{
    // قائمة بمفاتيح الإعدادات التي يديرها هذا المتحكم
    // أضفنا مفاتيح تمارا الجديدة
    private array $settingKeys = [
        'site_name_ar', 'site_name_en', 'site_description_ar', 'site_description_en',
        'logo_path_light', 'logo_path_dark', 'favicon_path',
        'contact_email', 'contact_phone', 'contact_whatsapp', 'contact_address_ar', 'contact_address_en',
        'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_snapchat', 'social_tiktok',
        'maintenance_mode', 'maintenance_message_ar', 'maintenance_message_en',
        'default_currency_code', 'default_currency_symbol',
        'booking_availability_months', 'booking_buffer_time',
        'policy_ar', 'policy_en', 'terms_ar', 'terms_en',
        'seo_meta_title_ar', 'seo_meta_title_en', 'seo_meta_description_ar', 'seo_meta_description_en', 'seo_meta_keywords_ar', 'seo_meta_keywords_en',
        'google_analytics_id', 'facebook_pixel_id',
        'enable_bank_transfer', 'enable_tamara_payment', // مفتاح عام لتفعيل تمارا
        'homepage_slider_images', // لن يتم تعديله مباشرة هنا، ولكن جيد أن يكون معروفاً

        // مفاتيح إعدادات حدود رسائل SMS التي تمت إضافتها سابقاً
        'sms_monthly_limit', 'sms_stop_sending_on_limit',

        // --- MODIFICATION START: Tamara Setting Keys ---
        'tamara_enabled', // للتحكم العام في تفعيل/تعطيل تمارا
        'tamara_api_url',
        'tamara_api_token',
        'tamara_notification_token', // يستخدم للتحقق من صحة الويب هوك
        'tamara_webhook_verification_bypass', // لتجاوز التحقق من الويب هوك (لأغراض التطوير)
        // --- MODIFICATION END ---
    ];


    public function edit()
    {
        $settingsCollection = Setting::whereIn('key', $this->settingKeys)->get();
        $settings = [];
        foreach ($settingsCollection as $setting) {
            // التحقق إذا كانت القيمة JSON لـ homepage_slider_images
            if ($setting->key === 'homepage_slider_images') {
                $decodedValue = json_decode($setting->value, true);
                // إذا فشل التحويل أو لم يكن مصفوفة، استخدم مصفوفة فارغة
                $settings[$setting->key] = (is_array($decodedValue)) ? $decodedValue : [];
            } else {
                $settings[$setting->key] = $setting->value;
            }
        }

        // تعيين قيم افتراضية إذا لم تكن موجودة
        foreach ($this->settingKeys as $key) {
            if (!array_key_exists($key, $settings)) {
                if ($key === 'homepage_slider_images') {
                    $settings[$key] = [];
                } elseif (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'enable_tamara_payment', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    $settings[$key] = '0'; // الافتراضي للـ booleans هو '0' (false)
                } else {
                    $settings[$key] = '';
                }
            }
        }
        // التأكد من أن homepage_slider_images هي مصفوفة دائماً
        if (!is_array($settings['homepage_slider_images'])) {
            $settings['homepage_slider_images'] = [];
        }


        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        // ملاحظة: قواعد التحقق قد تحتاج إلى تعديل بناءً على متطلبات كل حقل
        $rules = [
            'site_name_ar' => 'nullable|string|max:100',
            'site_name_en' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_whatsapp' => 'nullable|string|max:20',
            'booking_availability_months' => 'nullable|integer|min:1|max:24',
            'booking_buffer_time' => 'nullable|integer|min:0|max:360', // بالدقائق
            'maintenance_mode' => 'nullable|boolean',
            'enable_bank_transfer' => 'nullable|boolean',
            // 'enable_tamara_payment' => 'nullable|boolean', // تم استبداله بـ tamara_enabled

            'logo_light_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_dark_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon_file' => 'nullable|image|mimes:ico,png|max:512',
            'slider_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096', // لكل صورة في السلايدر

            // قواعد التحقق لإعدادات حدود الرسائل النصية
            'sms_monthly_limit' => 'nullable|integer|min:0',
            'sms_stop_sending_on_limit' => 'nullable|boolean',

            // --- MODIFICATION START: Tamara Settings Validation ---
            'tamara_enabled' => 'nullable|boolean',
            'tamara_api_url' => 'nullable|string|url|max:255',
            'tamara_api_token' => 'nullable|string|max:255', // عادة ما يكون طويلاً
            'tamara_notification_token' => 'nullable|string|max:255', // عادة ما يكون طويلاً
            'tamara_webhook_verification_bypass' => 'nullable|boolean',
            // --- MODIFICATION END ---
        ];

        // إضافة باقي المفاتيح كـ nullable|string مبدئياً إذا لم يكن لها قواعد خاصة
        foreach ($this->settingKeys as $key) {
            if (!isset($rules[$key])) {
                 if (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    // هذه تم التعامل معها بالفعل
                } else if ($key === 'homepage_slider_images' || Str::endsWith($key, '_file') ) {
                    // هذه يتم التعامل معها بشكل خاص (ملفات)
                }
                else {
                    $rules[$key] = 'nullable|string|max:65535'; // استخدام TEXT قد يتطلب max كبير
                }
            }
        }

        $validatedData = $request->validate($rules, [
            'booking_availability_months.integer' => 'عدد أشهر التوافر يجب أن يكون رقماً صحيحاً.',
            'booking_buffer_time.integer' => 'فترة الراحة يجب أن تكون رقماً صحيحاً بالدقائق.',
            'tamara_api_url.url' => 'رابط API لتمارا يجب أن يكون رابطاً صحيحاً (URL).',
        ]);

        try {
            foreach ($validatedData as $key => $value) {
                // التعامل مع قيم checkbox (booleans)
                if (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    $valueToStore = $request->has($key) ? '1' : '0';
                } else {
                    $valueToStore = $value;
                }
                
                if (in_array($key, $this->settingKeys)) { // تأكد من أن المفتاح من ضمن المفاتيح المدارة
                     Setting::updateOrCreate(['key' => $key], ['value' => $valueToStore]);
                }
            }

            // التعامل مع رفع الملفات (الشعارات، الفافيكون)
            if ($request->hasFile('logo_light_file')) {
                $path = $request->file('logo_light_file')->store('logos', 'public');
                Setting::updateOrCreate(['key' => 'logo_path_light'], ['value' => 'storage/' . $path]);
            }
            if ($request->hasFile('logo_dark_file')) {
                $path = $request->file('logo_dark_file')->store('logos', 'public');
                Setting::updateOrCreate(['key' => 'logo_path_dark'], ['value' => 'storage/' . $path]);
            }
            if ($request->hasFile('favicon_file')) {
                $path = $request->file('favicon_file')->store('favicons', 'public');
                Setting::updateOrCreate(['key' => 'favicon_path'], ['value' => 'storage/' . $path]);
            }

            // التعامل مع صور السلايدر
            $sliderImagesPaths = json_decode(Setting::where('key', 'homepage_slider_images')->value('value') ?? '[]', true);
            if ($request->has('deleted_slider_images')) {
                foreach ($request->input('deleted_slider_images') as $deletedImage) {
                    // حذف الملف الفعلي (اختياري، لكن جيد)
                    // Storage::disk('public')->delete(str_replace('storage/', '', $deletedImage));
                    $sliderImagesPaths = array_diff($sliderImagesPaths, [$deletedImage]);
                }
            }
            if ($request->hasFile('slider_images')) {
                foreach ($request->file('slider_images') as $file) {
                    $path = $file->store('slider', 'public');
                    $sliderImagesPaths[] = 'storage/' . $path;
                }
            }
            Setting::updateOrCreate(['key' => 'homepage_slider_images'], ['value' => json_encode(array_values($sliderImagesPaths))]);


            Log::info('General settings updated by admin ID: ' . auth()->id());
            
            // مسح الكاش بعد تحديث الإعدادات لضمان تطبيقها
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Log::info('Configuration and application cache cleared after settings update.');


            return redirect()->route('admin.settings.edit')->with('success', 'تم تحديث الإعدادات العامة بنجاح.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Settings update validation failed.', ['errors' => $e->errors(), 'admin_id' => auth()->id()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update general settings: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace' => Str::limit($e->getTraceAsString(), 1000),
                'admin_id' => auth()->id()
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات العامة: حدث خطأ ما.')->withInput();
        }
    }
}
