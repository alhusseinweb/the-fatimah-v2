<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // لإدارة الملفات

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
        'enable_bank_transfer', 'tamara_enabled',
        'homepage_slider_images',
        'sms_monthly_limit', 'sms_stop_sending_on_limit',
        'tamara_api_url',
        'tamara_api_token',
        'tamara_notification_token',
        'tamara_webhook_verification_bypass',
        'contact_instagram_url',
        'display_whatsapp_contact',
        'display_instagram_contact',
        'outside_ahsa_fee',
        // --- MODIFICATION START: New settings for Bank Transfer Discount ---
        'enable_bank_transfer_discount_popup',
        'bank_transfer_discount_popup_message_ar',
        'bank_transfer_discount_popup_message_en',
        'bank_transfer_discount_code',
        // --- MODIFICATION END ---
        'paylink_enabled',
        'paylink_api_id',
        'paylink_secret_key',
        'paylink_test_mode',
    ];

    private array $booleanSettingKeys = [
        'maintenance_mode',
        'enable_bank_transfer',
        'tamara_enabled',
        'tamara_webhook_verification_bypass',
        'sms_stop_sending_on_limit',
        'display_whatsapp_contact',
        'display_instagram_contact',
        // --- MODIFICATION START: Add new boolean key ---
        'enable_bank_transfer_discount_popup',
        // --- MODIFICATION END ---
        'paylink_enabled',
        'paylink_test_mode',
    ];

    public function edit()
    {
        $settingsCollection = Setting::whereIn('key', $this->settingKeys)->get()->keyBy('key');
        $settings = [];

        foreach ($this->settingKeys as $key) {
            if ($settingsCollection->has($key)) {
                $settingModel = $settingsCollection->get($key);
                if ($key === 'homepage_slider_images') {
                    $decodedValue = json_decode($settingModel->value, true);
                    $settings[$key] = (is_array($decodedValue)) ? $decodedValue : [];
                } else {
                    $settings[$key] = $settingModel->value;
                }
            } else {
                if ($key === 'homepage_slider_images') {
                    $settings[$key] = [];
                } elseif (in_array($key, $this->booleanSettingKeys)) {
                    // --- MODIFICATION START: Default for new boolean ---
                    if ($key === 'display_whatsapp_contact' || $key === 'display_instagram_contact' || $key === 'enable_bank_transfer_discount_popup') {
                         // Default to enabled for display toggles, or disabled for discount popup for safety
                        $settings[$key] = ($key === 'enable_bank_transfer_discount_popup') ? '0' : '1';
                    } else {
                        $settings[$key] = '0';
                    }
                    // --- MODIFICATION END ---
                } elseif ($key === 'outside_ahsa_fee') { 
                    $settings[$key] = '300'; 
                } 
                // --- MODIFICATION START: Default for new text settings ---
                elseif ($key === 'bank_transfer_discount_popup_message_ar') {
                    $settings[$key] = 'لا تفوت الفرصة! استخدم كود الخصم الخاص بالتحويل البنكي.';
                } elseif ($key === 'bank_transfer_discount_code') {
                    $settings[$key] = ''; // اترك الكود فارغًا بشكل افتراضي
                }
                // --- MODIFICATION END ---
                else {
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
        Log::debug('Settings Update Request - Raw Data:', $request->all());

        $rules = [
            'site_name_ar' => 'nullable|string|max:100',
            'site_name_en' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:25',
            'contact_whatsapp' => 'nullable|string|max:25',
            'booking_availability_months' => 'nullable|integer|min:1|max:24',
            'booking_buffer_time' => 'nullable|integer|min:0|max:360',
            'logo_light_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_dark_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon_file' => 'nullable|image|mimes:ico,png|max:512',
            'slider_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'deleted_slider_images_json' => 'nullable|json',
            'sms_monthly_limit' => 'nullable|integer|min:0',
            'tamara_api_url' => 'nullable|string|url|max:255',
            'tamara_api_token' => 'nullable|string|max:1000',
            'tamara_notification_token' => 'nullable|string|max:1000',
            'contact_instagram_url' => 'nullable|url|max:255',
            'outside_ahsa_fee' => 'nullable|numeric|min:0|max:10000',
            // --- MODIFICATION START: Validation for new settings ---
            'bank_transfer_discount_popup_message_ar' => 'nullable|string|max:500',
            'bank_transfer_discount_popup_message_en' => 'nullable|string|max:500',
            'bank_transfer_discount_code' => 'nullable|string|max:50', // يمكن إضافة exists:discount_codes,code إذا أردت التأكد من وجود الكود
            // --- MODIFICATION END ---
            'paylink_api_id' => 'nullable|string|max:255',
            'paylink_secret_key' => 'nullable|string|max:255',
        ];

        foreach ($this->settingKeys as $key) {
            if (!isset($rules[$key])) {
                if (in_array($key, $this->booleanSettingKeys)) {
                    $rules[$key] = 'sometimes|boolean';
                } else if ($key === 'homepage_slider_images' || Str::endsWith($key, '_file') || $key === 'deleted_slider_images_json' ) {
                    // No general rule needed
                }
                else if (Str::contains($key, ['policy_', 'terms_', 'description_', 'message_']) && !Str::contains($key, 'bank_transfer_discount_popup_message')) { // استثناء رسالة الخصم الجديدة إذا أردت لها max مختلف
                     $rules[$key] = 'nullable|string|max:10000';
                }
                else if (!Str::contains($key, 'bank_transfer_discount_popup_message') && $key !== 'bank_transfer_discount_code'){ // تجنب إعادة تعريف القواعد المضافة بالفعل
                    $rules[$key] = 'nullable|string|max:65535'; 
                }
            }
        }
        
        $validatedData = $request->validate($rules);
        Log::debug('Settings Update Request - Validated Data (subset shown):', collect($validatedData)->only([
            'contact_instagram_url', 'display_whatsapp_contact', 'display_instagram_contact', 'outside_ahsa_fee',
            'enable_bank_transfer_discount_popup', 'bank_transfer_discount_popup_message_ar', 'bank_transfer_discount_code' // إضافة المفاتيح الجديدة هنا لتسجيلها
        ])->toArray());


        try {
            DB::beginTransaction();

            foreach ($this->settingKeys as $key) {
                if (Str::endsWith($key, '_file') || $key === 'homepage_slider_images' || $key === 'deleted_slider_images_json') {
                    continue;
                }

                $valueToStore = null;
                if (in_array($key, $this->booleanSettingKeys)) {
                    $valueToStore = $request->has($key) ? '1' : '0';
                } elseif (array_key_exists($key, $validatedData)) { 
                    $valueToStore = $validatedData[$key];
                } elseif ($request->exists($key)) { 
                    // هذا الشرط قد يكون غير ضروري إذا اعتمدنا فقط على $validatedData للقيم النصية
                    // ولكن نبقيه للحقول التي قد لا تكون في $rules بشكل صريح ولكنها في $settingKeys
                    $valueToStore = $request->input($key);
                }
                
                // يتم التحديث فقط إذا كان المفتاح موجودًا في الطلب (للحقول العادية) أو دائمًا للحقول البوليانية
                if ($request->exists($key) || in_array($key, $this->booleanSettingKeys) ) {
                     Setting::updateOrCreate(['key' => $key], ['value' => $valueToStore ?? '']);
                     if (in_array($key, [
                         'enable_bank_transfer', 'tamara_enabled', 'display_whatsapp_contact', 
                         'display_instagram_contact', 'outside_ahsa_fee', 
                         'enable_bank_transfer_discount_popup', 'bank_transfer_discount_code' // إضافة لتسجيل التغيير
                        ])) { 
                        Log::info("Setting '{$key}' updated to: " . ($valueToStore ?? 'EMPTY_STRING'));
                    }
                }
            }

            // ... (باقي كود رفع الملفات كما هو) ...
            $fileUploads = [
                'logo_light_file' => 'logo_path_light',
                'logo_dark_file' => 'logo_path_dark',
                'favicon_file' => 'favicon_path',
            ];
            foreach ($fileUploads as $fileInputName => $settingKeyName) {
                if ($request->hasFile($fileInputName)) { 
                    try {
                        $oldFilePath = Setting::where('key', $settingKeyName)->value('value');
                        if ($oldFilePath && Storage::disk('public')->exists(Str::after($oldFilePath, 'storage/'))) {
                            Storage::disk('public')->delete(Str::after($oldFilePath, 'storage/'));
                        }
                        $filePath = $request->file($fileInputName)->store($settingKeyName, 'public');
                        Setting::updateOrCreate(['key' => $settingKeyName], ['value' => 'storage/' . $filePath]);
                    } catch (\Exception $e) {
                        Log::error("File upload failed for {$fileInputName}: " . $e->getMessage());
                    }
                }
            }
            
            if ($request->has('deleted_slider_images_json') || $request->hasFile('slider_images')) {
                $currentSliderImagesPaths = json_decode(Setting::where('key', 'homepage_slider_images')->value('value') ?? '[]', true);
                $currentSliderImagesPaths = is_array($currentSliderImagesPaths) ? $currentSliderImagesPaths : [];

                if ($request->filled('deleted_slider_images_json')) {
                    $deletedImagesInput = json_decode($request->input('deleted_slider_images_json'), true);
                    if (is_array($deletedImagesInput)) {
                        foreach($deletedImagesInput as $pathToDelete){
                             if (Storage::disk('public')->exists(Str::after($pathToDelete, 'storage/'))) {
                                Storage::disk('public')->delete(Str::after($pathToDelete, 'storage/'));
                            }
                        }
                        $currentSliderImagesPaths = array_diff($currentSliderImagesPaths, $deletedImagesInput);
                    }
                }

                if ($request->hasFile('slider_images')) {
                    foreach ($request->file('slider_images') as $file) {
                        try {
                            $path = $file->store('slider_uploads', 'public'); 
                            $currentSliderImagesPaths[] = 'storage/' . $path;
                        } catch (\Exception $e) {
                             Log::error("Slider image upload failed for one file: " . $e->getMessage());
                        }
                    }
                }
                Setting::updateOrCreate(['key' => 'homepage_slider_images'], ['value' => json_encode(array_values($currentSliderImagesPaths))]);
            }


            DB::commit();
            Log::info('General settings updated by admin ID: ' . auth()->id());
            
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear'); 
            Log::info('Configuration, application, and view caches cleared after settings update.');

            return redirect()->route('admin.settings.edit')->with('success', 'تم تحديث الإعدادات العامة بنجاح.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::warning('Settings update validation failed.', ['errors' => $e->errors(), 'admin_id' => auth()->id()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update general settings: ' . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_limit' => Str::limit($e->getTraceAsString(), 1500),
                'admin_id' => auth()->id()
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات العامة: حدث خطأ ما. يرجى مراجعة سجلات الأخطاء.')->withInput();
        }
    }
}
