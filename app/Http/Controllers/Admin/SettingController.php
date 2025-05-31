<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB; // تم التأكد من وجود هذا الاستيراد

class SettingController extends Controller
{
    // قائمة بجميع مفاتيح الإعدادات المتوقعة والمستخدمة في النظام
    private array $settingKeys = [
        'site_name_ar', 'site_name_en', 'site_description_ar', 'site_description_en',
        'logo_path_light', 'logo_path_dark', 'favicon_path',
        'contact_email', 'contact_phone', 'contact_whatsapp', 'contact_address_ar', 'contact_address_en',
        'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_snapchat', 'social_tiktok', // مفاتيح السوشيال ميديا العامة
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
        // --- START: NEW SETTING KEYS ---
        'contact_instagram_url',         // رابط الإنستقرام
        'display_whatsapp_contact',      // تفعيل/تعطيل عرض الواتساب
        'display_instagram_contact',     // تفعيل/تعطيل عرض الإنستقرام
        // --- END: NEW SETTING KEYS ---
    ];

    // قائمة بمفاتيح الإعدادات التي هي من النوع البولياني (true/false)
    private array $booleanSettingKeys = [
        'maintenance_mode',
        'enable_bank_transfer',
        'tamara_enabled',
        'tamara_webhook_verification_bypass',
        'sms_stop_sending_on_limit',
        // --- START: NEW BOOLEAN SETTING KEYS ---
        'display_whatsapp_contact',
        'display_instagram_contact',
        // --- END: NEW BOOLEAN SETTING KEYS ---
    ];

    public function edit()
    {
        // جلب جميع الإعدادات المحددة في $settingKeys دفعة واحدة
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
                // إذا لم يكن المفتاح موجوداً في قاعدة البيانات، قم بتعيين قيمة افتراضية
                if ($key === 'homepage_slider_images') {
                    $settings[$key] = [];
                } elseif (in_array($key, $this->booleanSettingKeys)) {
                    // القيم الافتراضية للحقول البوليانية الجديدة (للعرض)
                    if ($key === 'display_whatsapp_contact' || $key === 'display_instagram_contact') {
                        $settings[$key] = '1'; // افتراضياً مفعلة
                    } else {
                        $settings[$key] = '0'; // بقية الحقول البوليانية غير الموجودة تكون غير مفعلة افتراضياً
                    }
                } else {
                    $settings[$key] = ''; // قيمة نصية فارغة افتراضياً
                }
            }
        }
        // تأكيد إضافي أن homepage_slider_images هي مصفوفة دائماً
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
            'contact_phone' => 'nullable|string|max:25', // زيادة طفيفة للطول
            'contact_whatsapp' => 'nullable|string|max:25', // زيادة طفيفة للطول
            'booking_availability_months' => 'nullable|integer|min:1|max:24',
            'booking_buffer_time' => 'nullable|integer|min:0|max:360',
            
            'logo_light_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_dark_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon_file' => 'nullable|image|mimes:ico,png|max:512',
            'slider_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'deleted_slider_images_json' => 'nullable|json', // للتحقق من أنه JSON صالح إذا تم إرساله

            'sms_monthly_limit' => 'nullable|integer|min:0',
            
            'tamara_api_url' => 'nullable|string|url|max:255',
            'tamara_api_token' => 'nullable|string|max:1000', // زيادة الطول إذا كان التوكن طويلاً
            'tamara_notification_token' => 'nullable|string|max:1000', // زيادة الطول

            // --- START: VALIDATION FOR NEW FIELDS ---
            'contact_instagram_url' => 'nullable|url|max:255',
            // الحقول البوليانية سيتم التحقق منها تلقائياً من خلال الحلقة أدناه
            // --- END: VALIDATION FOR NEW FIELDS ---
        ];

        // إضافة قواعد التحقق الديناميكية لبقية المفاتيح
        foreach ($this->settingKeys as $key) {
            if (!isset($rules[$key])) { // إذا لم يكن هناك قاعدة تحقق صريحة معرفة أعلاه
                if (in_array($key, $this->booleanSettingKeys)) {
                    $rules[$key] = 'sometimes|boolean'; // sometimes يجعلها اختيارية، boolean يتحقق من 0,1,true,false,"0","1"
                } else if ($key === 'homepage_slider_images' || Str::endsWith($key, '_file') || $key === 'deleted_slider_images_json' ) {
                    // هذه الحقول يتم التعامل معها بشكل خاص أو لديها قواعد تحقق خاصة بها بالفعل
                }
                // للحقول النصية الطويلة مثل السياسات أو الأوصاف
                else if (Str::contains($key, ['policy_', 'terms_', 'description_', 'message_'])) {
                     $rules[$key] = 'nullable|string|max:10000'; // حد أقصى كبير للنصوص الطويلة
                }
                else {
                    // قاعدة عامة للحقول النصية الأخرى
                    $rules[$key] = 'nullable|string|max:65535'; 
                }
            }
        }
        
        $validatedData = $request->validate($rules);
        Log::debug('Settings Update Request - Validated Data (subset shown):', collect($validatedData)->only(['contact_instagram_url', 'display_whatsapp_contact', 'display_instagram_contact'])->toArray());

        try {
            DB::beginTransaction();

            // تحديث الإعدادات النصية والبوليانية
            foreach ($this->settingKeys as $key) {
                // تجاهل مفاتيح تحميل الملفات هنا، سيتم التعامل معها بشكل منفصل
                if (Str::endsWith($key, '_file') || $key === 'homepage_slider_images' || $key === 'deleted_slider_images_json') {
                    continue;
                }

                $valueToStore = null;
                if (in_array($key, $this->booleanSettingKeys)) {
                    // للمفاتيح البوليانية، القيمة تكون '1' إذا كان المفتاح موجوداً في الطلب، وإلا '0'
                    $valueToStore = $request->has($key) ? '1' : '0';
                    Log::debug("Processing boolean setting '{$key}': request->has='{$request->has($key)}', valueToStore='{$valueToStore}'");
                } elseif (array_key_exists($key, $validatedData)) { 
                    // إذا كان المفتاح موجوداً في البيانات التي تم التحقق من صحتها (وليس بولياني)
                    $valueToStore = $validatedData[$key];
                } elseif ($request->exists($key)) {
                    // كحل أخير، إذا كان المفتاح موجوداً في الطلب ولكن ليس في البيانات المتحقق منها (قد لا يحدث هذا كثيراً مع التحقق الشامل)
                    $valueToStore = $request->input($key);
                }
                
                // قم بالتحديث فقط إذا كانت هناك قيمة لتخزينها أو إذا كان المفتاح بولياني (لضمان حفظ '0')
                if ($valueToStore !== null || in_array($key, $this->booleanSettingKeys)) {
                    Setting::updateOrCreate(['key' => $key], ['value' => $valueToStore ?? '']); // استخدام '' بدلاً من null للقيم النصية الفارغة
                     if (in_array($key, ['enable_bank_transfer', 'tamara_enabled', 'display_whatsapp_contact', 'display_instagram_contact'])) { // إضافة مفاتيح جديدة للتسجيل
                        Log::info("Setting '{$key}' updated to: " . ($valueToStore ?? 'EMPTY_STRING'));
                    }
                }
            }

            // منطق حفظ الملفات (الشعارات، الأيقونة)
            $fileUploads = [
                'logo_light_file' => 'logo_path_light',
                'logo_dark_file' => 'logo_path_dark',
                'favicon_file' => 'favicon_path',
            ];
            foreach ($fileUploads as $fileInputName => $settingKeyName) {
                if ($request->hasFile($fileInputName)) { 
                    try {
                        $filePath = $request->file($fileInputName)->store($settingKeyName, 'public'); // استخدام اسم المفتاح كمجلد فرعي
                        Setting::updateOrCreate(['key' => $settingKeyName], ['value' => 'storage/' . $filePath]);
                    } catch (\Exception $e) {
                        Log::error("File upload failed for {$fileInputName}: " . $e->getMessage());
                        // يمكنك إضافة رسالة خطأ هنا إذا أردت
                    }
                }
            }

            // التعامل مع صور السلايدر (الحذف والإضافة)
            if ($request->has('deleted_slider_images_json') || $request->hasFile('slider_images')) {
                $currentSliderImagesPaths = json_decode(Setting::where('key', 'homepage_slider_images')->value('value') ?? '[]', true);
                $currentSliderImagesPaths = is_array($currentSliderImagesPaths) ? $currentSliderImagesPaths : [];

                // حذف الصور
                if ($request->filled('deleted_slider_images_json')) {
                    $deletedImagesInput = json_decode($request->input('deleted_slider_images_json'), true);
                    if (is_array($deletedImagesInput)) {
                        // هنا يجب حذف الملفات فعلياً من الـ storage إذا أردت
                        // foreach ($deletedImagesInput as $pathToDelete) {
                        //     \Illuminate\Support\Facades\Storage::disk('public')->delete(Str::after($pathToDelete, 'storage/'));
                        // }
                        $currentSliderImagesPaths = array_diff($currentSliderImagesPaths, $deletedImagesInput);
                    }
                }

                // إضافة الصور الجديدة
                if ($request->hasFile('slider_images')) {
                    foreach ($request->file('slider_images') as $file) {
                        try {
                            $path = $file->store('slider_uploads', 'public'); // مجلد مخصص لصور السلايدر
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
            
            // مسح الكاش لضمان تطبيق الإعدادات الجديدة فوراً
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
                'trace_limit' => Str::limit($e->getTraceAsString(), 1500), // زيادة طول التتبع قليلاً
                'admin_id' => auth()->id()
            ]);
            return back()->with('error', 'فشل تحديث الإعدادات العامة: حدث خطأ ما. يرجى مراجعة سجلات الأخطاء.')->withInput();
        }
    }
}
