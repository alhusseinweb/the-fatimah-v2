<?php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AvailabilityService
{
    protected const WEEKLY_SCHEDULE_SETTING_KEY = 'availability_schedule';
    protected const SLOT_INTERVAL_MINUTES = 15;
    protected const BOOKING_BLOCKING_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_PENDING,
    ];
    protected const BOOKING_BUFFER_TIME_KEY = 'booking_buffer_time';

    public function getAvailableSlots(Service $service, Carbon $date): array
    {
        $weeklySchedule = $this->getWeeklySchedule();
        $exceptions = $this->getExceptionsForDate($date);
        $bookings = $this->getBookingsForDate($date);

        $serviceDurationMinutes = is_numeric($service->duration_hours) ? ((float)$service->duration_hours * 60) : 0;
        if ($serviceDurationMinutes <= 0) {
            Log::warning('Invalid service duration for service ID: ' . $service->id . '. Duration: ' . $service->duration_hours);
            return [];
        }

        $bufferTimeSetting = Setting::where('key', self::BOOKING_BUFFER_TIME_KEY)->first();
        $bufferMinutes = $bufferTimeSetting ? (int)$bufferTimeSetting->value : 0;
        if ($bufferMinutes < 0) $bufferMinutes = 0;

        // *** التعديل هنا: generateTimeSlotsForDay قد تحتاج إلى تعديل أو الاعتماد على getWorkingHoursForDay ***
        // سنقوم بتعديل generateTimeSlots ليعيد فترات تمتد عبر الأيام إذا لزم الأمر
        // ولكن بشكل أبسط، سنجلب ساعات العمل التي قد تمتد لليوم التالي
        $workingHours = $this->getWorkingHoursForDay($date, $weeklySchedule);
        if (empty($workingHours)) {
            Log::info('No working hours defined or day is not active for date: ' . $date->toDateString());
            return [];
        }

        // $potentialSlots لا تزال نقاط بداية محتملة
        $potentialSlots = $this->generatePotentialStartTimes($workingHours['start'], $workingHours['end']);
        
        if (empty($potentialSlots)) {
            Log::info('No potential start times generated for date: ' . $date->toDateString());
            return [];
        }
        
        $slotsAfterExceptions = $this->filterSlotsByExceptions($potentialSlots, $exceptions, $date, $workingHours['start'], $workingHours['end']);
         if (empty($slotsAfterExceptions)) {
             Log::info('All potential slots removed by exceptions for date: ' . $date->toDateString());
             return [];
         }

        $finalAvailableSlots = $this->filterSlotsByDurationAndBuffer(
            $slotsAfterExceptions,
            $date, // تاريخ اليوم الذي نبحث فيه
            $serviceDurationMinutes,
            $bufferMinutes,
            $exceptions, // الاستثناءات الأصلية لهذا اليوم
            $bookings,   // الحجوزات الأصلية لهذا اليوم
            $workingHours['end'] // نهاية يوم العمل الفعلية (قد تكون في اليوم التالي)
        );

        $now = Carbon::now(config('app.timezone')); // استخدام المنطقة الزمنية للتطبيق
        $finalAvailableSlots = array_filter($finalAvailableSlots, function (Carbon $slot) use ($now) {
            return $slot->gt($now);
        });

        return array_values(array_unique(array_map(fn($slot) => $slot->format('H:i'), $finalAvailableSlots)));
    }

    protected function getWeeklySchedule(): ?array
    {
        $scheduleSetting = Setting::where('key', self::WEEKLY_SCHEDULE_SETTING_KEY)->value('value');
        if (!$scheduleSetting) return null;
        return json_decode($scheduleSetting, true) ?: null;
    }

    protected function getExceptionsForDate(Carbon $date): Collection
    {
        return AvailabilityException::where('date', $date->format('Y-m-d'))
                                    ->get(['start_time', 'end_time', 'is_blocked']);
    }

    protected function getBookingsForDate(Carbon $date): Collection
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay()->addDay(); // جلب حجوزات اليوم الحالي وحتى نهاية اليوم التالي (لتغطية الحجوزات التي تبدأ متأخرًا)

        return Booking::with('service:id,duration_hours')
                      ->whereBetween('booking_datetime', [$dayStart, $dayEnd]) // جلب الحجوزات التي قد تؤثر على اليوم الحالي
                      ->whereIn('status', self::BOOKING_BLOCKING_STATUSES)
                      ->orderBy('booking_datetime')
                      ->get(['id','booking_datetime', 'service_id']);
    }
    
    /**
     * دالة جديدة لجلب وقت بدء وانتهاء العمل الفعلي ليوم معين، مع مراعاة امتداد الوقت لليوم التالي.
     * @return array|null ['start' => Carbon, 'end' => Carbon] أو null إذا لم يكن اليوم فعالاً.
     */
    protected function getWorkingHoursForDay(Carbon $targetDate, ?array $weeklySchedule): ?array
    {
        if (!$weeklySchedule) return null;
        $dayKey = strtolower($targetDate->englishDayOfWeek);

        if (isset($weeklySchedule[$dayKey]) && ($weeklySchedule[$dayKey]['active'] ?? false) &&
            !empty($weeklySchedule[$dayKey]['start']) && !empty($weeklySchedule[$dayKey]['end'])) {
            
            $daySettings = $weeklySchedule[$dayKey];
            $startTime = Carbon::parse($daySettings['start'], config('app.timezone'));
            $endTime = Carbon::parse($daySettings['end'], config('app.timezone'));

            $actualStartDateTime = $targetDate->copy()->setTimeFrom($startTime);
            $actualEndDateTime = $targetDate->copy()->setTimeFrom($endTime);

            if ($endTime->lt($startTime)) { // إذا كان وقت الانتهاء قبل وقت البدء (يمتد لليوم التالي)
                $actualEndDateTime->addDay();
            }
            return ['start' => $actualStartDateTime, 'end' => $actualEndDateTime];
        }
        return null; // اليوم غير فعال أو أوقاته غير محددة
    }

    /**
     * توليد نقاط بداية محتملة بناءً على ساعات العمل الفعلية.
     */
    protected function generatePotentialStartTimes(Carbon $actualStartDateTime, Carbon $actualEndDateTime): array
    {
        $slots = [];
        $currentSlot = $actualStartDateTime->copy();
        $intervalMinutes = self::SLOT_INTERVAL_MINUTES;

        while ($currentSlot->lt($actualEndDateTime)) {
            $slots[] = $currentSlot->copy();
            $currentSlot->addMinutes($intervalMinutes);
        }
        return $slots;
    }


    protected function filterSlotsByExceptions(array $slots, Collection $exceptions, Carbon $targetDate, Carbon $workDayStart, Carbon $workDayEnd): array
    {
        if ($exceptions->isEmpty()) {
            return $slots;
        }

        $blockedIntervals = $exceptions->map(function ($exception) use ($targetDate, $workDayStart, $workDayEnd) {
            if (!($exception->is_blocked ?? true)) return null;

            $exceptionDate = Carbon::parse($exception->date, config('app.timezone'));

            // نهتم فقط بالاستثناءات التي تقع في نفس يوم $targetDate
            // هذا مهم لأن $workDayEnd قد يكون في اليوم التالي.
            if (!$exceptionDate->isSameDay($targetDate)) return null;


            $startOfDay = $targetDate->copy()->startOfDay();
            $endOfTargetDay = $targetDate->copy()->endOfDay(); // نهاية اليوم المستهدف (23:59:59)

            $start = $exception->start_time ? $targetDate->copy()->setTimeFrom(Carbon::parse($exception->start_time, config('app.timezone'))) : $startOfDay;
            $end = $exception->end_time ? $targetDate->copy()->setTimeFrom(Carbon::parse($exception->end_time, config('app.timezone'))) : $endOfTargetDay; // نهاية اليوم المستهدف كحد أقصى

             // إذا كان الاستثناء يمتد لليوم التالي (غير مدعوم حاليًا بهذا المنطق البسيط للاستثناءات، نفترض أن الاستثناء ضمن نفس اليوم)
             // لكن إذا كان وقت انتهاء الاستثناء قبل بدايته (مثلاً حظر من 10م إلى 2ص في نفس اليوم، فهذا غير منطقي كسجل استثناء واحد)
             // هنا نفترض أن start_time و end_time للاستثناء تقع ضمن يوم $exception->date
            if ($start->gte($end) && !($start->isSameTime($startOfDay) && $end->isSameTime($endOfTargetDay))) { //  تجاهل إذا كان المقصود يوم كامل
                 Log::warning("Exception with invalid time range for a single day: Start {$exception->start_time}, End {$exception->end_time} on {$exception->date}");
                 return null;
            }

            // تأكد أن فترة الاستثناء لا تتجاوز بداية ونهاية يوم العمل الفعلي
            $start = $start->max($workDayStart);
            $end = $end->min($workDayEnd);

            if ($start->lt($end)) {
                 return ['start' => $start, 'end' => $end];
            }
            return null;

        })->filter();

        if ($blockedIntervals->isEmpty()) {
            return $slots;
        }

        return array_filter($slots, function (Carbon $slot) use ($blockedIntervals) {
            foreach ($blockedIntervals as $interval) {
                if ($slot->between($interval['start'], $interval['end'], true, false)) {
                    return false;
                }
            }
            return true;
        });
    }

    protected function filterSlotsByDurationAndBuffer(
        array $potentialStartSlots,
        Carbon $targetDate, // تاريخ اليوم الذي نبحث فيه عن مواعيد
        int $serviceDurationMinutes,
        int $globalBufferMinutes,
        Collection $rawExceptions,
        Collection $rawBookings,
        Carbon $workDayActualEnd // نهاية يوم العمل الفعلية (قد تكون في اليوم التالي)
    ): array {
        $availableSlots = [];
        $busyIntervals = $this->getBusyIntervalsWithBuffer($targetDate, $rawExceptions, $rawBookings, $globalBufferMinutes, $workDayActualEnd);

        foreach ($potentialStartSlots as $slotStart) {
            // وقت انتهاء الخدمة المطلوبة (بدون فترة الراحة التي تليها، لأنها تُحسب مع الحجوزات الأخرى)
            $requestedServiceEnd = $slotStart->copy()->addMinutes($serviceDurationMinutes);

            // 1. تأكد أن نهاية الخدمة المطلوبة لا تتجاوز نهاية يوم العمل الفعلية
            if ($requestedServiceEnd->gt($workDayActualEnd)) {
                continue; // هذا الموعد غير متاح لأنه يتجاوز نهاية العمل
            }

            // 2. تأكد أن نهاية الخدمة + فترة الراحة بعدها لا تتجاوز نهاية يوم العمل
            // هذا الشرط مهم إذا كان هناك bufferMinutes > 0
            if ($globalBufferMinutes > 0) {
                if ($requestedServiceEnd->copy()->addMinutes($globalBufferMinutes)->gt($workDayActualEnd)) {
                    // لا يمكن إضافة فترة الراحة ضمن يوم العمل، لذا هذا الموعد غير متاح
                    continue;
                }
            }

            $isSlotAvailable = true;
            foreach ($busyIntervals as $busyInterval) {
                // [slotStart, requestedServiceEnd)
                // [busyInterval['start'], busyInterval['end'])
                
                // هل بداية الفترة المقترحة تقع ضمن فترة مشغولة؟
                // أو هل نهاية الفترة المقترحة تقع ضمن فترة مشغولة؟
                // أو هل الفترة المقترحة تحتوي بالكامل على فترة مشغولة؟
                // أو هل الفترة المشغولة تحتوي بالكامل على الفترة المقترحة؟

                // A overlaps B if A.start < B.end AND A.end > B.start
                if ($slotStart->lt($busyInterval['end']) && $requestedServiceEnd->gt($busyInterval['start'])) {
                    $isSlotAvailable = false;
                    break;
                }
            }

            if ($isSlotAvailable) {
                // تأكد أن الوقت المتاح يقع ضمن اليوم المستهدف للعرض
                // إذا كان slotStart يبدأ في اليوم التالي للتاريخ المستهدف، لا تعرضه لهذا اليوم
                // هذا مهم إذا كان يوم العمل السابق يمتد لليوم الحالي (مثلاً، العمل يبدأ الاثنين 10م وينتهي الثلاثاء 2ص، ونحن نبحث عن مواعيد ليوم الثلاثاء)
                // في هذه الحالة، getWorkingHoursForDay سترجع ساعات العمل ليوم الثلاثاء (مثلاً من منتصف الليل حتى 2ص).
                // slotStart يجب أن يكون ضمن اليوم targetDate (أو اليوم التالي إذا كان workDayActualEnd في اليوم التالي و slotStart بعد منتصف الليل)
                if ($slotStart->isSameDay($targetDate) || ($workDayActualEnd->isAfter($targetDate->copy()->endOfDay()) && $slotStart->isSameDay($workDayActualEnd))) {
                     $availableSlots[] = $slotStart->copy();
                }
            }
        }
        return $availableSlots;
    }

    protected function getBusyIntervalsWithBuffer(Carbon $targetDate, Collection $exceptions, Collection $bookings, int $globalBufferMinutes, Carbon $workDayActualEnd): array
    {
        $busy = [];

        // الاستثناءات: يجب أن تكون ضمن نطاق يوم العمل الفعلي
        $workDayActualStart = $this->getWorkingHoursForDay($targetDate, $this->getWeeklySchedule())['start'] ?? $targetDate->copy()->startOfDay();


        foreach ($exceptions as $exception) {
            if (!($exception->is_blocked ?? true)) continue;

            $exceptionDate = Carbon::parse($exception->date, config('app.timezone'));
            if (!$exceptionDate->isSameDay($targetDate)) continue; // نهتم فقط باستثناءات اليوم المطلوب


            $startOfDay = $targetDate->copy()->startOfDay();
            $endOfTargetDay = $targetDate->copy()->endOfDay();

            $start = $exception->start_time ? $targetDate->copy()->setTimeFrom(Carbon::parse($exception->start_time, config
