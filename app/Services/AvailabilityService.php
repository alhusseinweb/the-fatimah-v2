<?php

// المسار: app/Services/AvailabilityService.php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterval; // لاستخدام الفترات الزمنية
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log; // استيراد Log

class AvailabilityService
{
    // --- الإعدادات والافتراضات ---
    protected const WEEKLY_SCHEDULE_SETTING_KEY = 'availability_schedule';
    // SLOT_INTERVAL_MINUTES: يحدد دقة البحث عن الأوقات. إذا كانت الخدمات تبدأ كل نصف ساعة، فهذا مناسب.
    // إذا كان لديك خدمات تبدأ كل 15 دقيقة، يجب تغييره إلى 15.
    protected const SLOT_INTERVAL_MINUTES = 15; // تم تعديله ليكون أكثر دقة
    protected const BOOKING_BLOCKING_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_PENDING, // أو أي حالات أخرى تعتبر الوقت محجوزًا
        // Booking::STATUS_PENDING_CONFIRMATION, // إذا كانت هذه الحالة تحجز الوقت أيضًا
    ];
    protected const BOOKING_BUFFER_TIME_KEY = 'booking_buffer_time'; // مفتاح فترة الراحة

    /**
     * الدالة الرئيسية لجلب الأوقات المتاحة لخدمة معينة في يوم محدد.
     */
    public function getAvailableSlots(Service $service, Carbon $date): array
    {
        // 1. جلب البيانات اللازمة
        $weeklySchedule = $this->getWeeklySchedule();
        $exceptions = $this->getExceptionsForDate($date);
        $bookings = $this->getBookingsForDate($date); // جلب جميع الحجوزات لهذا اليوم

        $serviceDurationMinutes = is_numeric($service->duration_hours) ? ($service->duration_hours * 60) : 0;
        if ($serviceDurationMinutes <= 0) {
            Log::warning('Invalid service duration for service ID: ' . $service->id . '. Duration: ' . $service->duration_hours);
            return [];
        }

        // *** جلب فترة الراحة من الإعدادات ***
        $bufferTimeSetting = Setting::where('key', self::BOOKING_BUFFER_TIME_KEY)->first();
        $bufferMinutes = $bufferTimeSetting ? (int)$bufferTimeSetting->value : 0;
        // تأكد أن فترة الراحة ليست سالبة
        if ($bufferMinutes < 0) $bufferMinutes = 0;


        // 2. توليد الفترات الزمنية المبدئية بناءً على جدول العمل
        $potentialSlots = $this->generateTimeSlots($date, $weeklySchedule);
        if (empty($potentialSlots)) {
            Log::info('No potential slots generated based on weekly schedule for date: ' . $date->toDateString());
            return [];
        }

        // 3. فلترة الفترات بناءً على الاستثناءات (الأوقات المحظورة يدويًا)
        // هذه الدالة تزيل أي نقطة بداية تقع ضمن استثناء
        $slotsAfterExceptions = $this->filterSlotsByExceptions($potentialSlots, $exceptions, $date);
        if (empty($slotsAfterExceptions)) {
            Log::info('All potential slots removed by exceptions for date: ' . $date->toDateString());
            return [];
        }

        // 4. الفلترة النهائية للتأكد من وجود مدة كافية للخدمة وفترة الراحة بعدها،
        // مع مراعاة الاستثناءات والحجوزات الأخرى (التي تشمل فترات الراحة الخاصة بها).
        $finalAvailableSlots = $this->filterSlotsByDurationAndBuffer(
            $slotsAfterExceptions, // هذه هي نقاط البداية المحتملة بعد إزالة ما بداخل الاستثناءات
            $date,
            $serviceDurationMinutes,
            $bufferMinutes, // تمرير فترة الراحة هنا
            $exceptions, // تمرير الاستثناءات الأصلية
            $bookings      // تمرير الحجوزات الأصلية
        );
        
        // 5. إزالة الأوقات التي فاتت
        $now = Carbon::now();
        $finalAvailableSlots = array_filter($finalAvailableSlots, function (Carbon $slot) use ($now) {
            return $slot->gt($now); // يجب أن يكون الوقت أكبر من الوقت الحالي
        });


        // 6. تنسيق المخرجات وإزالة التكرار
        return array_values(array_unique(array_map(fn($slot) => $slot->format('H:i'), $finalAvailableSlots)));
    }

    protected function getWeeklySchedule(): ?array
    {
        $scheduleSetting = Setting::where('key', self::WEEKLY_SCHEDULE_SETTING_KEY)->value('value');
        if (!$scheduleSetting) {
            Log::warning('Weekly schedule setting not found or is null.');
            return null;
        }
        $schedule = json_decode($scheduleSetting, true);
        if (!is_array($schedule)) {
            Log::error('Failed to decode weekly schedule JSON or it is not an array.', ['json_value' => $scheduleSetting]);
            return null;
        }
        return $schedule;
    }

    protected function getExceptionsForDate(Carbon $date): Collection
    {
        return AvailabilityException::where('date', $date->format('Y-m-d'))
                                    // is_blocked يمكن أن يكون true افتراضيًا إذا لم يتم تحديده بشكل آخر
                                    // ->where('is_blocked', true) // أزل هذا إذا كنت تريد التعامل مع كل الاستثناءات هنا
                                    ->get(['start_time', 'end_time', 'is_blocked']); // جلب is_blocked
    }

    protected function getBookingsForDate(Carbon $date): Collection
    {
        // لا نحتاج لجلب الخدمة مع كل حجز هنا إذا كنا سنحصل على فترة الراحة بشكل منفصل
        return Booking::with('service:id,duration_hours') // ما زلنا نحتاج مدة الخدمة المحجوزة
                      ->whereDate('booking_datetime', $date->format('Y-m-d'))
                      ->whereIn('status', self::BOOKING_BLOCKING_STATUSES)
                      ->orderBy('booking_datetime') // مهم للترتيب
                      ->get(['booking_datetime', 'service_id']);
    }

    protected function generateTimeSlots(Carbon $date, ?array $schedule): array
    {
        $slots = [];
        if (!$schedule) {
            Log::info('generateTimeSlots called with null schedule for date: ' . $date->toDateString());
            return $slots;
        }
        $dayName = strtolower($date->englishDayOfWeek); // 'saturday', 'sunday', etc.

        if (isset($schedule[$dayName]) && ($schedule[$dayName]['active'] ?? false) && !empty($schedule[$dayName]['start']) && !empty($schedule[$dayName]['end'])) {
            try {
                $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule[$dayName]['start']);
                $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule[$dayName]['end']);

                if ($startTime->gte($endTime)) {
                    Log::warning("Start time ({$schedule[$dayName]['start']}) is greater than or equal to end time ({$schedule[$dayName]['end']}) for day: {$dayName} on {$date->toDateString()}");
                    return [];
                }

                $currentSlot = $startTime->copy();
                $intervalMinutes = is_numeric(self::SLOT_INTERVAL_MINUTES) && self::SLOT_INTERVAL_MINUTES > 0 ? self::SLOT_INTERVAL_MINUTES : 30;

                while ($currentSlot->lt($endTime)) {
                    $slots[] = $currentSlot->copy();
                    $currentSlot->addMinutes($intervalMinutes);
                }
            } catch (\Exception $e) {
                Log::error("Error in generateTimeSlots for {$dayName} on {$date->toDateString()}: " . $e->getMessage(), [
                    'schedule_day_data' => $schedule[$dayName],
                    'exception' => $e
                ]);
                return [];
            }
        } else {
            Log::info("Schedule for day '{$dayName}' on {$date->toDateString()} is not active or times are missing.", ['schedule_day_data' => $schedule[$dayName] ?? null]);
        }
        return $slots;
    }


    protected function filterSlotsByExceptions(array $slots, Collection $exceptions, Carbon $date): array
    {
        if ($exceptions->isEmpty()) {
            return $slots;
        }

        $blockedIntervals = $exceptions->map(function ($exception) use ($date) {
            // نأخذ فقط الاستثناءات التي هي محظورة بالفعل
            if (!($exception->is_blocked ?? true)) return null; // افترض أن is_blocked هو true إذا لم يكن موجودًا أو null

            // إذا لم يكن هناك وقت بداية أو نهاية، افترض أنه يوم كامل محظور
            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            $start = $exception->start_time ? Carbon::parse($date->format('Y-m-d') . ' ' . $exception->start_time) : $startOfDay;
            $end = $exception->end_time ? Carbon::parse($date->format('Y-m-d') . ' ' . $exception->end_time) : $endOfDay;
            
            if ($start->gte($end) && !($start->isSameDay($startOfDay) && $end->isSameDay($endOfDay))) { // إذا لم يكن يومًا كاملاً وكان البدء بعد النهاية
                 Log::warning("Exception with invalid time range: Start {$exception->start_time}, End {$exception->end_time} on {$date->toDateString()}");
                 return null;
            }
            return ['start' => $start, 'end' => $end];
        })->filter();

        if ($blockedIntervals->isEmpty()) {
            return $slots;
        }

        return array_filter($slots, function (Carbon $slot) use ($blockedIntervals) {
            foreach ($blockedIntervals as $interval) {
                // $slot >= $interval['start'] && $slot < $interval['end']
                if ($slot->between($interval['start'], $interval['end'], true, false)) { // strict less than for end
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * الفلترة النهائية للتأكد من أن كل فترة زمنية متبقية لديها وقت كافٍ بعدها
     * لاستيعاب مدة الخدمة المطلوبة + فترة الراحة، مع مراعاة الاستثناءات والحجوزات الأخرى.
     */
    protected function filterSlotsByDurationAndBuffer(
        array $potentialStartSlots,
        Carbon $date,
        int $serviceDurationMinutes,
        int $bufferMinutes, // فترة الراحة المحددة من الإعدادات
        Collection $rawExceptions, // الاستثناءات الأصلية
        Collection $rawBookings    // الحجوزات الأصلية
    ): array {
        $availableSlots = [];

        // 1. إنشاء قائمة موحدة بكل الفترات الزمنية المحظورة (Busy Intervals)
        // هذه المرة، الحجوزات ستشمل فترة الراحة الخاصة بها.
        $busyIntervals = $this->getBusyIntervalsWithBuffer($date, $rawExceptions, $rawBookings, $bufferMinutes);

        // 2. المرور على كل وقت بدء محتمل متبقي
        foreach ($potentialStartSlots as $slotStart) {
            // حساب وقت الانتهاء المتوقع لهذه الخدمة (بدون فترة الراحة الخاصة بها، لأن الراحة تضاف للحجوزات السابقة)
            $slotEnd = $slotStart->copy()->addMinutes($serviceDurationMinutes);

            // الفلترة الأولية: تأكد أن نهاية الخدمة لا تتجاوز نهاية يوم العمل المحدد في الجدول الأسبوعي
            // هذا التحقق مهم هنا قبل الدخول في حلقة الفترات المحظورة
            $dayName = strtolower($date->englishDayOfWeek);
            $schedule = $this->getWeeklySchedule(); // يجب أن تكون هذه الدالة فعالة (ربما كاش)
            if (isset($schedule[$dayName]) && ($schedule[$dayName]['active'] ?? false) && !empty($schedule[$dayName]['end'])) {
                $workEndTimeForDay = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule[$dayName]['end']);
                // إذا كانت نهاية الخدمة + فترة الراحة التي تليها (إذا كانت هناك فترة راحة) تتجاوز نهاية يوم العمل
                if ($slotEnd->copy()->addMinutes($bufferMinutes)->gt($workEndTimeForDay)) {
                     // وإذا كانت نهاية الخدمة فقط (بدون فترة الراحة) تتجاوز نهاية يوم العمل، فهذا بالتأكيد غير متاح
                    if($slotEnd->gt($workEndTimeForDay)){
                        continue; // انتقل إلى الفترة التالية المقترحة
                    }
                    // أما إذا كانت نهاية الخدمة ضمن وقت العمل، ولكن إضافة فترة الراحة بعدها تتجاوز وقت العمل،
                    // فسنسمح بهذا الموعد فقط إذا كانت فترة الراحة صفرًا.
                    // وإلا، فإنه لا يمكن حجز هذا الموعد لأنه لن تكون هناك فترة راحة كافية بعده ضمن ساعات العمل.
                    if ($bufferMinutes > 0) {
                        continue;
                    }
                }
            } else {
                // لا يوجد جدول عمل لهذا اليوم أو غير فعال، لا يجب أن نصل إلى هنا إذا كانت generateTimeSlots تعمل بشكل صحيح
                continue;
            }


            $isSlotAvailable = true;
            foreach ($busyIntervals as $busyInterval) {
                // التحقق من التداخل بين [slotStart, slotEnd) و [busyIntervalStart, busyIntervalEnd)
                $overlapStarts = $slotStart->max($busyInterval['start']);
                $overlapEnds = $slotEnd->min($busyInterval['end']);

                if ($overlapStarts->lt($overlapEnds)) {
                    $isSlotAvailable = false;
                    break;
                }
            }

            if ($isSlotAvailable) {
                $availableSlots[] = $slotStart->copy();
            }
        }
        return $availableSlots;
    }

    /**
     * دالة مساعدة لتجميع فترات الاستثناءات والحجوزات في قائمة واحدة من الفترات المحظورة.
     * الحجوزات هنا ستشمل فترة الراحة التي تليها.
     */
    protected function getBusyIntervalsWithBuffer(Carbon $date, Collection $exceptions, Collection $bookings, int $globalBufferMinutes): array
    {
        $busy = [];

        // إضافة فترات الاستثناءات (كما هي، بدون فترة راحة إضافية إلا إذا كانت جزءًا من تعريف الاستثناء نفسه)
        foreach ($exceptions as $exception) {
            if (!($exception->is_blocked ?? true)) continue;
            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();
            $start = $exception->start_time ? Carbon::parse($date->format('Y-m-d') . ' ' . $exception->start_time) : $startOfDay;
            $end = $exception->end_time ? Carbon::parse($date->format('Y-m-d') . ' ' . $exception->end_time) : $endOfDay;
            if ($start->lt($end)) {
                $busy[] = ['start' => $start, 'end' => $end];
            } else if ($start->isSameDay($startOfDay) && $end->isSameDay($endOfDay) && $start->eq($end) && $exception->start_time && $exception->end_time) {
                 // حالة خاصة: إذا كان وقت البدء والنهاية متساويين (ويمثلان يوم كامل محظور بناءً على عدم وجود أوقات محددة)
                 // تجاهل هذا النوع من الاستثناءات هنا إذا كنا نريد حظر اليوم بالكامل بشكل مختلف
                 // أو إذا كان الاستثناء يعني "محظور طوال اليوم"، فسيتم التعامل معه في generateTimeSlots أو filterSlotsByExceptions
            } else if ($start->gte($end) && !($start->isSameDay($startOfDay) && $end->isSameDay($endOfDay))) {
                 Log::warning("BusyInterval (Exception): Start time is GTE end time and not a full day block.", ['start' => $exception->start_time, 'end' => $exception->end_time, 'date' => $date->toDateString()]);
            }
        }

        // إضافة فترات الحجوزات مع فترة الراحة المحددة بعدها
        foreach ($bookings as $booking) {
            if (!$booking->service) {
                Log::warning("Booking ID {$booking->id} does not have an associated service.");
                continue;
            }
            $bookingStart = Carbon::parse($booking->booking_datetime); // تأكد من أنه Carbon
            $serviceDurationMinutes = is_numeric($booking->service->duration_hours) ? ($booking->service->duration_hours * 60) : 0;

            if ($serviceDurationMinutes <= 0) {
                Log::warning("Booking ID {$booking->id} has a service with invalid duration: " . $booking->service->duration_hours);
                continue;
            }
            // نهاية الحجز تشمل مدة الخدمة + فترة الراحة العامة
            $bookingEndWithBuffer = $bookingStart->copy()->addMinutes($serviceDurationMinutes + $globalBufferMinutes);
            $busy[] = ['start' => $bookingStart, 'end' => $bookingEndWithBuffer];
        }
        
        // (اختياري متقدم) يمكن دمج الفترات المتداخلة أو المتجاورة في $busy لتقليل عدد المقارنات لاحقاً
        // هذا يمكن أن يحسن الأداء إذا كان هناك عدد كبير من الفترات المحظورة.
        // For now, we will sort them to make conflict detection potentially a bit more orderly, though not strictly necessary with the current overlap logic.
        usort($busy, function ($a, $b) {
            return $a['start']->timestamp - $b['start']->timestamp;
        });

        return $busy;
    }
}