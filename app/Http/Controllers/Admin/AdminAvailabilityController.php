<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AvailabilityException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator; // استيراد Validator

class AdminAvailabilityController extends Controller
{
    protected $daysOfWeek = [
        'saturday' => 'السبت',
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
    ];

    public function index()
    {
        $scheduleSetting = Setting::where('key', 'availability_schedule')->first();
        $schedule = $scheduleSetting && $scheduleSetting->value ? json_decode($scheduleSetting->value, true) : [];

        $daysData = [];
        foreach ($this->daysOfWeek as $key => $name) {
            $daysData[$key] = [
                'name' => $name,
                'active' => Arr::get($schedule, $key . '.active', false),
                'start' => Arr::get($schedule, $key . '.start', '09:00'),
                'end' => Arr::get($schedule, $key . '.end', '17:00'),
            ];
        }

        $exceptions = AvailabilityException::orderBy('date')->orderBy('start_time')->paginate(10);

        // جلب الإعدادات المطلوبة وتمريرها مباشرة
        // سنستخدم اسم 'settings' للمصفوفة التي نمررها للواجهة
        $settings = [];
        $settingKeys = ['booking_buffer_time']; // يمكنك إضافة مفاتيح إعدادات أخرى هنا إذا لزم الأمر
        $dbSettings = Setting::whereIn('key', $settingKeys)->pluck('value', 'key')->all();

        foreach ($settingKeys as $key) {
            // إذا كان المفتاح booking_buffer_time ولم يوجد، ضع القيمة الافتراضية 0
            if ($key === 'booking_buffer_time') {
                $settings[$key] = $dbSettings[$key] ?? 0;
            } else {
                $settings[$key] = $dbSettings[$key] ?? null; // أو أي قيمة افتراضية أخرى مناسبة
            }
        }

        // الآن المتغير $settings يحتوي على ['booking_buffer_time' => value_from_db_or_0]
        return view('admin.availability.index', compact('daysData', 'exceptions', 'settings'));
    }

    public function updateSchedule(Request $request)
    {
        $scheduleRules = [];
        $inputDays = $request->input('days', []);

        foreach ($this->daysOfWeek as $key => $name) {
            if (isset($inputDays[$key]['active'])) {
                $scheduleRules['days.' . $key . '.start'] = 'required|date_format:H:i';
                $scheduleRules['days.' . $key . '.end'] = 'required|date_format:H:i|after:days.' . $key . '.start';
            }
        }
        
        $settingsRules = [
            // لاحظ أننا نتوقع 'settings.booking_buffer_time' من النموذج
            'settings.booking_buffer_time' => 'nullable|integer|min:0|max:240',
        ];

        $validator = Validator::make($request->all(), array_merge($scheduleRules, $settingsRules), [
            'days.*.end.after' => 'وقت الانتهاء يجب أن يكون بعد وقت البدء لليوم :day.', // يمكنك تخصيص رسالة الخطأ لليوم
            'settings.booking_buffer_time.integer' => 'فترة الراحة يجب أن تكون رقماً صحيحاً.',
            'settings.booking_buffer_time.min' => 'فترة الراحة لا يمكن أن تكون أقل من صفر.',
            'settings.booking_buffer_time.max' => 'فترة الراحة كبيرة جداً (الحد الأقصى 240 دقيقة).',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $scheduleData = [];
        foreach ($this->daysOfWeek as $key => $name) {
            $isActive = isset($inputDays[$key]['active']);
            $startTime = $inputDays[$key]['start'] ?? '09:00';
            $endTime = $inputDays[$key]['end'] ?? '17:00';

            $scheduleData[$key] = [
                'active' => $isActive,
                'start'  => $isActive ? $startTime : null,
                'end'    => $isActive ? $endTime : null,
            ];
        }
        Setting::updateOrCreate(
            ['key' => 'availability_schedule'],
            ['value' => json_encode($scheduleData)]
        );

        // حفظ إعدادات فترة الراحة
        // النموذج يرسل 'settings' كمصفوفة
        $settingsInput = $request->input('settings', []);
        if (array_key_exists('booking_buffer_time', $settingsInput)) { // تحقق من وجود المفتاح
            Setting::updateOrCreate(
                ['key' => 'booking_buffer_time'],
                // تأكد من حفظ القيمة حتى لو كانت صفرًا أو فارغة (لكن التحقق يضمن أنها رقم)
                ['value' => $settingsInput['booking_buffer_time'] ?? 0]
            );
        }
        
        return redirect()->route('admin.availability.index')
                         ->with('success', 'تم تحديث جدول المواعيد والإعدادات بنجاح.');
    }

    public function storeException(Request $request)
    {
        $validatedData = $request->validate([
            'exception_date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'notes' => 'nullable|string|max:255',
        ],[
            'exception_date.required' => 'حقل التاريخ مطلوب.',
            'exception_date.date_format' => 'صيغة التاريخ يجب أن تكون على شكل سنة-شهر-يوم.',
            'start_time.date_format' => 'صيغة وقت البدء يجب أن تكون على شكل ساعة:دقيقة.',
            'end_time.date_format' => 'صيغة وقت الانتهاء يجب أن تكون على شكل ساعة:دقيقة.',
            'end_time.after_or_equal' => 'وقت الانتهاء يجب أن يكون بعد أو نفس وقت البدء.',
        ]);

        AvailabilityException::create([
            'date' => $validatedData['exception_date'],
            'start_time' => $validatedData['start_time'] ?? null,
            'end_time' => $validatedData['end_time'] ?? null,
            'notes' => $validatedData['notes'] ?? null,
        ]);

        return redirect()->route('admin.availability.index')
                         ->with('success', 'تمت إضافة الاستثناء بنجاح.');
    }

    public function destroyException(AvailabilityException $exception)
    {
        try {
            $exception->delete();
            return redirect()->route('admin.availability.index')
                         ->with('success', 'تم حذف الاستثناء بنجاح.');
        } catch (\Exception $e) {
             \Log::error("خطأ في حذف استثناء التوافر: {$exception->id} - {$e->getMessage()}");
            return redirect()->route('admin.availability.index')
                         ->with('error', 'حدث خطأ أثناء محاولة حذف الاستثناء.');
        }
    }
}