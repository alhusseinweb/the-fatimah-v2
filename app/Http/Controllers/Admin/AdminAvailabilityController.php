<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AvailabilityException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon; // غير مستخدم مباشرة في هذا المتحكم ولكنه جيد للإبقاء عليه إذا تم استخدامه لاحقًا
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
                'end' => Arr::get($schedule, $key . '.end', '17:00'), // كان 17:00، يمكنك تغييره إلى 23:59 إذا أردت كقيمة افتراضية جديدة
            ];
        }

        $exceptions = AvailabilityException::orderBy('date')->orderBy('start_time')->paginate(10);

        $settings = [];
        $settingKeys = ['booking_buffer_time'];
        $dbSettings = Setting::whereIn('key', $settingKeys)->pluck('value', 'key')->all();

        foreach ($settingKeys as $key) {
            if ($key === 'booking_buffer_time') {
                $settings[$key] = $dbSettings[$key] ?? 0;
            } else {
                $settings[$key] = $dbSettings[$key] ?? null;
            }
        }

        return view('admin.availability.index', compact('daysData', 'exceptions', 'settings'));
    }

    public function updateSchedule(Request $request)
    {
        $scheduleRules = [];
        $inputDays = $request->input('days', []);

        foreach ($this->daysOfWeek as $key => $name) {
            if (isset($inputDays[$key]['active'])) {
                $scheduleRules['days.' . $key . '.start'] = 'required|date_format:H:i';
                // --- تم التعديل هنا: إزالة قيد "after" ---
                $scheduleRules['days.' . $key . '.end'] = 'required|date_format:H:i';
            }
        }
        
        $settingsRules = [
            'settings.booking_buffer_time' => 'nullable|integer|min:0|max:240', // الحد الأقصى لفترة الراحة 4 ساعات
        ];

        // يمكنك إزالة رسالة الخطأ days.*.end.after أو تعديلها إذا كنت ستقوم بتحقق آخر من نوع مختلف
        $customMessages = [
            // 'days.*.end.after' => 'وقت الانتهاء يجب أن يكون بعد وقت البدء لليوم :day.', // هذه الرسالة لم تعد ضرورية للقاعدة التي أزلناها
            'settings.booking_buffer_time.integer' => 'فترة الراحة يجب أن تكون رقماً صحيحاً بالدقائق.',
            'settings.booking_buffer_time.min' => 'فترة الراحة لا يمكن أن تكون أقل من صفر دقيقة.',
            'settings.booking_buffer_time.max' => 'فترة الراحة يجب ألا تتجاوز 240 دقيقة.',
        ];

        // إضافة رسائل تحقق مخصصة لحقول الوقت إذا أردت (اختياري)
        foreach ($this->daysOfWeek as $key => $name) {
             if (isset($inputDays[$key]['active'])) {
                $scheduleRules['days.' . $key . '.start.required'] = "وقت البدء لليوم {$name} مطلوب.";
                $scheduleRules['days.' . $key . '.start.date_format'] = "صيغة وقت البدء لليوم {$name} غير صحيحة (مثال: 09:00).";
                $scheduleRules['days.' . $key . '.end.required'] = "وقت الانتهاء لليوم {$name} مطلوب.";
                $scheduleRules['days.' . $key . '.end.date_format'] = "صيغة وقت الانتهاء لليوم {$name} غير صحيحة (مثال: 17:00).";
             }
        }


        $validator = Validator::make($request->all(), array_merge($scheduleRules, $settingsRules), $customMessages);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $scheduleData = [];
        foreach ($this->daysOfWeek as $key => $name) {
            $isActive = isset($inputDays[$key]['active']);
            // القيم الافتراضية تبقى كما هي إذا لم يتم إرسال قيم جديدة أو إذا كان اليوم غير نشط وتم إلغاء تحديده
            $startTime = $inputDays[$key]['start'] ?? Arr::get($this->getDefaultSchedule(), $key . '.start', '09:00');
            $endTime = $inputDays[$key]['end'] ?? Arr::get($this->getDefaultSchedule(), $key . '.end', '17:00');


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

        $settingsInput = $request->input('settings', []);
        if (array_key_exists('booking_buffer_time', $settingsInput)) {
            Setting::updateOrCreate(
                ['key' => 'booking_buffer_time'],
                ['value' => $settingsInput['booking_buffer_time'] ?? 0]
            );
        }
        
        return redirect()->route('admin.availability.index')
                         ->with('success', 'تم تحديث جدول المواعيد والإعدادات بنجاح.');
    }

    /**
     * دالة مساعدة لجلب الجدول الافتراضي (يمكن استخدامها لتعبئة القيم إذا لم تكن موجودة)
     */
    protected function getDefaultSchedule(): array
    {
        $default = [];
        foreach ($this->daysOfWeek as $key => $name) {
            $default[$key] = ['active' => false, 'start' => '09:00', 'end' => '17:00'];
        }
        return $default;
    }

    public function storeException(Request $request)
    {
        $validatedData = $request->validate([
            'exception_date' => 'required|date_format:Y-m-d',
            'start_time' => 'nullable|date_format:H:i',
            // --- تعديل هنا: لا يمكن أن يكون وقت الانتهاء قبل وقت البدء في نفس اليوم للاستثناء ---
            // إلا إذا كنت تريد السماح باستثناءات تمتد عبر الأيام، وهو ما يتطلب منطقًا أكثر تعقيدًا
            'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'notes' => 'nullable|string|max:255',
        ],[
            'exception_date.required' => 'حقل التاريخ مطلوب.',
            'exception_date.date_format' => 'صيغة التاريخ يجب أن تكون على شكل سنة-شهر-يوم.',
            'start_time.date_format' => 'صيغة وقت البدء يجب أن تكون على شكل ساعة:دقيقة.',
            'end_time.date_format' => 'صيغة وقت الانتهاء يجب أن تكون على شكل ساعة:دقيقة.',
            'end_time.after_or_equal' => 'وقت الانتهاء للاستثناء يجب أن يكون بعد أو نفس وقت البدء (في نفس اليوم).',
        ]);

        // التحقق إذا كان start_time موجودًا ولكن end_time ليس كذلك، أو العكس (باستثناء حظر يوم كامل)
        if (($validatedData['start_time'] && !$validatedData['end_time']) || (!$validatedData['start_time'] && $validatedData['end_time'])) {
            return back()->withInput()->withErrors(['end_time' => 'يجب تحديد وقتي البدء والانتهاء معًا للاستثناء، أو تركهما فارغين لحظر اليوم بأكمله.']);
        }


        AvailabilityException::create([
            'date' => $validatedData['exception_date'],
            'start_time' => $validatedData['start_time'] ?? null,
            'end_time' => $validatedData['end_time'] ?? null,
            'is_blocked' => true, // افترض دائمًا أنه محظور عند الإنشاء من هذا النموذج
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
             Log::error("خطأ في حذف استثناء التوافر: {$exception->id} - {$e->getMessage()}");
            return redirect()->route('admin.availability.index')
                         ->with('error', 'حدث خطأ أثناء محاولة حذف الاستثناء.');
        }
    }
}
