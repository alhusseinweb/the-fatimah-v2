<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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
        // أضف أي مفاتيح إعدادات أخرى هنا
    ];

    private array $booleanSettingKeys = [ // مفاتيح الإعدادات المنطقية
        'maintenance_mode',
        'enable_bank_transfer',
        'tamara_enabled',
        'tamara_webhook_verification_bypass',
        'sms_stop_sending_on_limit',
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
                } elseif (in_array($key, $this->booleanSettingKeys)) {
                    $settings[$key] = '0'; // الافتراضي للـ booleans هو '0' (false)
                } else {
                    $settings[$key] = '';
                }
            }
        }
        if (!is_array($settings['homepage_slider_images'])) {
            $settings['homepage_slider_images'] = [];
        }

        // --- Debugging: Log the value being sent to the view ---
        if (isset($settings['enable_bank_transfer'])) {
            Log::debug("Settings Edit Page Load: 'enable_bank_transfer' value being passed to view:", ['value' => $settings['enable_bank_transfer']]);
        }
        // --- End Debugging ---

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        // --- Debugging: Log all request data ---
        Log::debug('Settings Update Request - Raw Data:', $request->all());
        // --- End Debugging ---

        $rules = [
            'site_name_ar' => 'nullable|string|max:100',
            'site_name_en' => 'nullable|string|max:100',
            // ... (باقي قواعد التحقق كما هي) ...
            'booking_availability_months' => 'nullable|integer|min:1|max:24',
            'booking_buffer_time' => 'nullable|integer|min:0|max:360',
            
            'logo_light_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_dark_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon_file' => 'nullable|image|mimes:ico,png|max:512',
            'slider_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',

            'sms_monthly_limit' => 'nullable|integer|min:0',
            
            'tamara_api_url' => 'nullable|string|url|max:255',
            'tamara_api_token' => 'nullable|string|max:1000',
            'tamara_notification_token' => 'nullable|string|max:1000',
        ];

        // إضافة قواعد التحقق للحقول المنطقية و باقي الحقول النصية
        foreach ($this->settingKeys as $key) {
            if (!isset($rules[$key])) { // إذا لم يكن للحقل قاعدة تحقق مخصصة بالفعل
                if (in_array($key, $this->booleanSettingKeys)) {
                    $rules[$key] = 'nullable|boolean'; // Laravel سيتعامل مع 'on', 1, true كـ true, وعدم وجوده أو '0', false كـ false
                } else if ($key === 'homepage_slider_images' || Str::endsWith($key, '_file') ) {
                    // تم التعامل معها بالفعل أو لا تحتاج لقاعدة هنا
                }
                else {
                    // افترض أن باقي المفاتيح هي نصوص
                    $rules[$key] = 'nullable|string|max:65535'; 
                }
            }
        }
        
        $validatedData = $request->validate($rules, [ /* ... رسائل الخطأ ... */ ]);
        Log::debug('Settings Update Request - Validated Data:', $validatedData);


        try {
            DB::beginTransaction();

            foreach ($this->settingKeys as $key) {
                if (!Str::endsWith($key, '_file') && $key !== 'homepage_slider_images') { // تجاهل مفاتيح الملفات هنا
                    $valueToStore = null;
                    if (in_array($key, $this->booleanSettingKeys)) {
                        // لـ checkbox/switch, إذا لم يتم إرسال المفتاح، فهذا يعني أنه '0' (false)
                        // إذا تم إرساله، فقيمة $request->input($key) ستكون '1' (لأن value="1" في الـ HTML)
                        $valueToStore = $request->has($key) ? '1' : '0';
                        Log::debug("Processing boolean setting '{$key}': request->has='{$request->has($key)}', valueToStore='{$valueToStore}'");
                    } elseif (array_key_exists($key, $validatedData)) { 
                        // لباقي الحقول التي تم التحقق منها وموجودة في $validatedData
                        $valueToStore = $validatedData[$key];
                    } elseif ($request->exists($key)) {
                        // إذا كان المفتاح موجوداً في الطلب ولكنه لم يكن في $validatedData (مثلاً، حقل نصي فارغ ولم يكن nullable بشكل صريح في rules لسبب ما)
                        // هذا أقل احتمالاً إذا كانت قواعد التحقق شاملة
                        $valueToStore = $request->input($key);
                    }
                    // إذا كان $valueToStore لا يزال null هنا، فهذا يعني أن الحقل لم يتم إرساله ولم يكن boolean
                    // قد ترغب في عدم تحديثه أو تعيين قيمة افتراضية. حالياً سيتم حفظ null.

                    if ($valueToStore !== null || array_key_exists($key, $validatedData)) { // تحديث فقط إذا كانت هناك قيمة أو كان ضمن البيانات المتحقق منها
                        Setting::updateOrCreate(['key' => $key], ['value' => $valueToStore]);
                         if ($key === 'enable_bank_transfer' || $key === 'tamara_enabled') {
                            Log::info("Setting '{$key}' updated to: " . ($valueToStore ?? 'NULL'));
                        }
                    }
                }
            }

            // ... (منطق حفظ الملفات كما هو) ...
            if ($request->hasFile('logo_light_file')) { /* ... */ }
            if ($request->hasFile('logo_dark_file')) { /* ... */ }
            if ($request->hasFile('favicon_file')) { /* ... */ }
            if ($request->has('deleted_slider_images_json') || $request->hasFile('slider_images')) {
                $currentSliderImages = json_decode(Setting::where('key', 'homepage_slider_images')->value('value') ?? '[]', true);
                if (!is_array($currentSliderImages)) $currentSliderImages = [];

                $deletedImages = json_decode($request->input('deleted_slider_images_json', '[]'), true);
                if (is_array($deletedImages)) {
                    $currentSliderImages = array_filter($currentSliderImages, function ($path) use ($deletedImages) {
                        // إذا كنت تريد حذف الملفات الفعلية من storage, افعل ذلك هنا
                        // if (in_array($path, $deletedImages)) { Storage::disk('public')->delete(str_replace('storage/', '', $path)); return false; }
                        return !in_array($path, $deletedImages);
                    });
                }

                if ($request->hasFile('slider_images')) {
                    foreach ($request->file('slider_images') as $file) {
                        $path = $file->store('slider', 'public');
                        $currentSliderImages[] = 'storage/' . $path;
                    }
                }
                Setting::updateOrCreate(['key' => 'homepage_slider_images'], ['value' => json_encode(array_values($currentSliderImages))]);
            }


            DB::commit();
            Log::info('General settings updated by admin ID: ' . auth()->id());
            
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear'); // إضافة مسح كاش الواجهات
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
                'trace' => Str::limit($e->getTraceAsString(), 1000),
                'admin_id' => auth()->id()
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات العامة: حدث خطأ ما.')->withInput();
        }
    }
}
