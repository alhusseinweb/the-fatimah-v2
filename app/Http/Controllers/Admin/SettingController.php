<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; // تأكد من وجود هذا الاستيراد

class SettingController extends Controller
{
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
        'enable_bank_transfer', 'tamara_enabled', // تم تغيير enable_tamara_payment إلى tamara_enabled
        'homepage_slider_images',

        'sms_monthly_limit', 'sms_stop_sending_on_limit',

        'tamara_api_url',
        'tamara_api_token',
        'tamara_notification_token',
        'tamara_webhook_verification_bypass',
    ];

    public function edit()
    {
        $settingsCollection = Setting::whereIn('key', $this->settingKeys)->get();
        $settings = [];
        foreach ($settingsCollection as $setting) {
            if ($setting->key === 'homepage_slider_images') {
                $decodedValue = json_decode($setting->value, true);
                $settings[$setting->key] = (is_array($decodedValue)) ? $decodedValue : [];
            } else {
                $settings[$setting->key] = $setting->value;
            }
        }

        foreach ($this->settingKeys as $key) {
            if (!array_key_exists($key, $settings)) {
                if ($key === 'homepage_slider_images') {
                    $settings[$key] = [];
                } elseif (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    $settings[$key] = '0';
                } else {
                    $settings[$key] = '';
                }
            }
        }
        if (!is_array($settings['homepage_slider_images'])) {
            $settings['homepage_slider_images'] = [];
        }
        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $rules = [
            'site_name_ar' => 'nullable|string|max:100',
            'site_name_en' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_whatsapp' => 'nullable|string|max:20',
            'booking_availability_months' => 'nullable|integer|min:1|max:24',
            'booking_buffer_time' => 'nullable|integer|min:0|max:360',
            'maintenance_mode' => 'nullable|boolean',
            'enable_bank_transfer' => 'nullable|boolean',

            'logo_light_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_dark_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon_file' => 'nullable|image|mimes:ico,png|max:512',
            'slider_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',

            'sms_monthly_limit' => 'nullable|integer|min:0',
            'sms_stop_sending_on_limit' => 'nullable|boolean',

            // --- MODIFICATION START: Increase max length for Tamara tokens ---
            'tamara_enabled' => 'nullable|boolean',
            'tamara_api_url' => 'nullable|string|url|max:255',
            'tamara_api_token' => 'nullable|string|max:1000', // زيادة الحد الأقصى
            'tamara_notification_token' => 'nullable|string|max:1000', // زيادة الحد الأقصى
            'tamara_webhook_verification_bypass' => 'nullable|boolean',
            // --- MODIFICATION END ---
        ];

        foreach ($this->settingKeys as $key) {
            if (!isset($rules[$key])) {
                 if (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    // تم التعامل معها
                } else if ($key === 'homepage_slider_images' || Str::endsWith($key, '_file') ) {
                    // يتم التعامل معها بشكل خاص (ملفات)
                }
                else {
                    $rules[$key] = 'nullable|string|max:65535';
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
                if (in_array($key, ['maintenance_mode', 'enable_bank_transfer', 'tamara_enabled', 'tamara_webhook_verification_bypass', 'sms_stop_sending_on_limit'])) {
                    $valueToStore = $request->has($key) ? '1' : '0';
                } else {
                    $valueToStore = $value;
                }
                
                if (in_array($key, $this->settingKeys)) {
                     Setting::updateOrCreate(['key' => $key], ['value' => $valueToStore]);
                }
            }

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

            $sliderImagesPaths = json_decode(Setting::where('key', 'homepage_slider_images')->value('value') ?? '[]', true);
            if(!is_array($sliderImagesPaths)) $sliderImagesPaths = []; // تأكد أنها مصفوفة
            
            if ($request->has('deleted_slider_images')) {
                $deletedImagesRequest = $request->input('deleted_slider_images');
                // قد يكون deleted_slider_images عبارة عن سلسلة JSON إذا تم إرسالها من حقل hidden واحد
                if (is_string($deletedImagesRequest)) {
                    $deletedImagesRequest = json_decode($deletedImagesRequest, true);
                }

                if (is_array($deletedImagesRequest)) {
                    foreach ($deletedImagesRequest as $deletedImage) {
                        // Storage::disk('public')->delete(str_replace('storage/', '', $deletedImage)); // اختياري: حذف الملف الفعلي
                        $sliderImagesPaths = array_diff($sliderImagesPaths, [$deletedImage]);
                    }
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
            
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            // إذا كنت تستخدم opcache في الإنتاج، يمكنك مسحه أيضاً:
            // if (function_exists('opcache_reset')) { opcache_reset(); }
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
