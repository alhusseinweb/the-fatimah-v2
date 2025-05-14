<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    // قائمة بمفاتيح الإعدادات المتوقعة، مع إضافة مفاتيح SMS
    protected $settingKeys = [
        'policy_ar',
        'policy_en', // سيبقى هذا المفتاح إذا كان لا يزال مستخدماً في مكان ما، وإلا يمكن إزالته
        'twilio_account_sid', // إذا كنت لا تزال تستخدم Twilio لأي شيء آخر
        'twilio_auth_token',
        'twilio_verify_sid',
        'sendgrid_api_key', // إذا كنت لا تزال تستخدم SendGrid
        'tamara_api_key',   // إذا كنت لا تزال تستخدم Tamara

        // مفاتيح إعدادات الصفحة الرئيسية المرئية
        'homepage_logo_path',
        'homepage_slider_images',

        // مفاتيح إعدادات SMS
        'sms_monthly_limit',
        'sms_stop_sending_on_limit',

        'months_available_for_booking',
        'reminder_notifications_enabled', // إذا كنت لا تزال تستخدم هذا الخيار
        // يمكنك إضافة مفاتيح أخرى هنا
        'contact_whatsapp', // مثال لمفتاح قد يكون لديك
        'logo_path_dark',   // مثال لمفتاح شعار آخر
    ];

    public function edit()
    {
        $settingsArray = Setting::pluck('value', 'key')->all();

        // معالجة خاصة لصور السلايدر (JSON)
        $settingsArray['homepage_slider_images'] = json_decode($settingsArray['homepage_slider_images'] ?? '[]', true) ?? [];

        // معالجة خاصة لخيار إيقاف إرسال SMS (boolean)
        $settingsArray['sms_stop_sending_on_limit'] = filter_var($settingsArray['sms_stop_sending_on_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // معالجة خاصة لخيار تفعيل إشعارات التذكير (boolean)
        $settingsArray['reminder_notifications_enabled'] = filter_var($settingsArray['reminder_notifications_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);


        // قم بتمرير المصفوفة بأكملها إلى الـ view. الـ view ستستخدم $settings['key_name']
        return view('admin.settings.edit', ['settings' => $settingsArray]);
    }

    public function update(Request $request)
    {
        $rules = [
            'policy_ar' => 'nullable|string',
            // 'policy_en' => 'nullable|string', // إذا تم حذفه من الواجهة، لا داعي للتحقق منه
            // ... قواعد التحقق الأخرى للمفاتيح النصية ...
            'months_available_for_booking' => 'required|integer|min:1|max:12',
            'contact_whatsapp' => 'nullable|string|max:20', // مثال

            // قواعد التحقق لملفات الصور (إذا كانت لا تزال موجودة في النموذج)
            'homepage_logo_path' => 'nullable|image|max:2048',
            'logo_path_dark' => 'nullable|image|max:2048', // مثال لشعار آخر
            'homepage_slider_images' => 'nullable|array',
            'homepage_slider_images.*' => 'nullable|image|max:2048', // اسمح بأن تكون بعض العناصر فارغة إذا كان المستخدم يحذف صوراً

            // قواعد التحقق لإعدادات SMS
            'sms_monthly_limit' => 'required|integer|min:0', // 0 يعني لا يوجد حد
        ];

        $messages = [
            'homepage_logo_path.image' => 'ملف الشعار يجب أن يكون صورة.',
            'homepage_logo_path.max' => 'حجم ملف الشعار لا يجب أن يتجاوز 2 ميجابايت.',
            'logo_path_dark.image' => 'ملف الشعار الداكن يجب أن يكون صورة.',
            'logo_path_dark.max' => 'حجم ملف الشعار الداكن لا يجب أن يتجاوز 2 ميجابايت.',
            'homepage_slider_images.*.image' => 'كل ملف سلايد يجب أن يكون صورة.',
            'homepage_slider_images.*.max' => 'حجم ملف السلايد لا يجب أن يتجاوز 2 ميجابايت.',
            'sms_monthly_limit.required' => 'الحد الشهري للرسائل مطلوب.',
            'sms_monthly_limit.integer' => 'الحد الشهري للرسائل يجب أن يكون رقماً صحيحاً.',
            'sms_monthly_limit.min' => 'الحد الشهري للرسائل لا يمكن أن يكون سالباً.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Settings update validation failed', ['errors' => $validator->errors()->all()]);
            return redirect()->back()
                        ->withErrors($validator)
                        ->withInput();
        }

        $currentSettings = Setting::pluck('value', 'key')->toArray();

        // معالجة رفع ملف الشعار الرئيسي (إذا تم إرساله)
        if ($request->hasFile('homepage_logo_path')) {
            $logoFile = $request->file('homepage_logo_path');
            if (isset($currentSettings['homepage_logo_path']) && $currentSettings['homepage_logo_path']) {
                Storage::disk('public')->delete(str_replace(Storage::url(''), '', $currentSettings['homepage_logo_path']));
            }
            $logoPath = $logoFile->store('settings/logos', 'public');
            Setting::updateOrCreate(['key' => 'homepage_logo_path'], ['value' => Storage::url($logoPath)]);
            Log::info('Homepage logo updated', ['path' => Storage::url($logoPath)]);
        }

        // معالجة رفع ملف الشعار الداكن (إذا تم إرساله)
        if ($request->hasFile('logo_path_dark')) {
            $logoFileDark = $request->file('logo_path_dark');
            if (isset($currentSettings['logo_path_dark']) && $currentSettings['logo_path_dark']) {
                Storage::disk('public')->delete(str_replace(Storage::url(''), '', $currentSettings['logo_path_dark']));
            }
            $logoPathDark = $logoFileDark->store('settings/logos', 'public');
            Setting::updateOrCreate(['key' => 'logo_path_dark'], ['value' => Storage::url($logoPathDark)]);
            Log::info('Dark logo updated', ['path' => Storage::url($logoPathDark)]);
        }


        // معالجة صور السلايدر
        $existingSliderImages = json_decode($currentSettings['homepage_slider_images'] ?? '[]', true) ?? [];
        $updatedSliderImagePaths = $existingSliderImages; // ابدأ بالصور الموجودة

        // حذف الصور التي تم تحديدها للحذف
        if ($request->has('delete_slider_images') && is_array($request->delete_slider_images)) {
            foreach ($request->delete_slider_images as $imagePathToDelete) {
                // تأكد من أن المسار يبدأ بـ storage/ لحذفه بشكل صحيح من قرص public
                $storagePathToDelete = str_replace(Storage::url(''), '', $imagePathToDelete); // تحويل URL إلى مسار تخزين
                if (Storage::disk('public')->exists($storagePathToDelete)) {
                    Storage::disk('public')->delete($storagePathToDelete);
                    Log::info('Slider image deleted', ['path' => $storagePathToDelete]);
                }
                // إزالة الصورة من المصفوفة
                $updatedSliderImagePaths = array_filter($updatedSliderImagePaths, function ($path) use ($imagePathToDelete) {
                    return $path !== $imagePathToDelete;
                });
            }
        }

        // إضافة صور جديدة
        if ($request->hasFile('homepage_slider_images')) {
            foreach ($request->file('homepage_slider_images') as $sliderImage) {
                if ($sliderImage->isValid()) {
                    $sliderImagePath = $sliderImage->store('settings/slider', 'public');
                    $updatedSliderImagePaths[] = Storage::url($sliderImagePath);
                    Log::info('New slider image uploaded', ['path' => Storage::url($sliderImagePath)]);
                }
            }
        }
        // إعادة ترتيب الفهارس إذا تم حذف عناصر
        $updatedSliderImagePaths = array_values($updatedSliderImagePaths);
        Setting::updateOrCreate(['key' => 'homepage_slider_images'], ['value' => json_encode($updatedSliderImagePaths)]);


        // تحديث باقي الإعدادات النصية و checkbox
        // استخدام $this->settingKeys لضمان تحديث المفاتيح المعرفة فقط
        $booleanSettings = ['reminder_notifications_enabled', 'sms_stop_sending_on_limit'];

        foreach ($this->settingKeys as $key) {
            if (in_array($key, ['homepage_logo_path', 'logo_path_dark', 'homepage_slider_images'])) {
                continue; // تم التعامل معها بالفعل
            }

            if (in_array($key, $booleanSettings)) {
                $value = $request->has($key) ? '1' : '0';
            } elseif ($request->filled($key) || $request->input($key) === null || $request->input($key) === '') {
                // اسمح بحفظ القيم الفارغة للحقول النصية
                 $value = $request->input($key);
            } else {
                // إذا لم يكن الحقل موجوداً في الطلب (وليس boolean)، لا نغير قيمته الحالية
                // هذا يمنع مسح القيم إذا لم يتم إرسالها (مثلاً مفاتيح API)
                if(isset($currentSettings[$key])) {
                    $value = $currentSettings[$key]; // الحفاظ على القيمة القديمة
                } else {
                    $value = null; // أو قيمة افتراضية مناسبة
                }
            }
             // معالجة خاصة لـ null إذا كان الحقل يمكن أن يكون فارغاً
             if ($value === null && in_array($key, ['policy_ar', 'policy_en', /* حقول نصية أخرى اختيارية */])) {
                 Setting::updateOrCreate(['key' => $key], ['value' => null]);
             } elseif ($value !== null || in_array($key, $booleanSettings) || $key === 'sms_monthly_limit' || $key === 'months_available_for_booking') {
                 Setting::updateOrCreate(['key' => $key], ['value' => $value]);
             }
        }

        Cache::forget('app_settings'); // مفتاح الكاش العام للإعدادات

        return redirect()->route('admin.settings.edit')->with('success', 'تم تحديث الإعدادات بنجاح.');
    }
}