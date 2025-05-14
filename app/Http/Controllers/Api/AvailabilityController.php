<?php

// المسار: app/Http/Controllers/Api/AvailabilityController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Setting;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // <-- إضافة Log

class AvailabilityController extends Controller
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * جلب الأوقات المتاحة لخدمة وتاريخ محددين.
     */
    public function getSlotsForServiceDate(Request $request, Service $service, string $dateString): JsonResponse
    {
        // ... (الكود السابق لهذه الدالة يبقى كما هو) ...

         // 1. التحقق من صحة التاريخ وتوافقه مع القيود
         $validator = Validator::make(['date' => $dateString], [
             'date' => 'required|date_format:Y-m-d',
         ]);

         if ($validator->fails()) {
             return response()->json(['error' => 'Invalid date format. Please use YYYY-MM-DD.'], 400);
         }

         try {
             $date = Carbon::parse($dateString)->startOfDay();
         } catch (\Exception $e) {
            Log::error("Invalid date string passed to getSlotsForServiceDate: {$dateString}", ['exception' => $e]);
            return response()->json(['error' => 'Invalid date provided.'], 400);
        }

         // 2. التحقق من أن الخدمة نشطة
         if (!$service->is_active) {
              return response()->json(['error' => 'Service not available.'], 404);
         }

         // 3. التحقق من أن التاريخ ليس في الماضي
         // مقارنة بداية اليوم لتجنب مشاكل المنطقة الزمنية
         if ($date->lt(Carbon::today()->startOfDay())) {
             return response()->json(['error' => 'Cannot check availability for past dates.'], 400);
         }

         // 4. التحقق من أن التاريخ ضمن المدى المسموح به
         $maxMonthsSetting = Setting::where('key', 'months_available_for_booking')->value('value');
         $maxMonths = $maxMonthsSetting ? (int)$maxMonthsSetting : 3;
         $latestAllowedDate = Carbon::today()->addMonthsNoOverflow($maxMonths)->endOfMonth();

         if ($date->gt($latestAllowedDate)) {
             return response()->json(['error' => 'Date is too far in the future.'], 400);
         }

         // 5. استدعاء خدمة حساب التوافر
         try {
             $availableSlots = $this->availabilityService->getAvailableSlots($service, $date);
             return response()->json([
                 'service_id' => $service->id,
                 'date' => $date->format('Y-m-d'),
                 'available_slots' => $availableSlots,
             ]);
         } catch (\Exception $e) {
             Log::error("Availability calculation failed for service {$service->id} on date {$dateString}: " . $e->getMessage(), ['exception' => $e]);
             return response()->json(['error' => 'Failed to retrieve availability. Please try again later.'], 500);
         }
    }

    // --- !!! الدالة الجديدة لجلب التوفر الشهري !!! ---
    /**
     * جلب حالة التوفر (هل يوجد أوقات أم لا) لكل يوم في شهر محدد.
     *
     * @param Request $request
     * @param Service $service
     * @param int $year
     * @param int $month
     * @return JsonResponse
     */
    public function getMonthAvailability(Request $request, Service $service, int $year, int $month): JsonResponse
    {
        // التحقق من صحة الشهر والسنة
        if ($month < 1 || $month > 12 || $year < Carbon::now()->year || $year > Carbon::now()->year + 2) { // تحديد مدى معقول للسنوات
             return response()->json(['error' => 'Invalid year or month.'], 400);
        }

        // التحقق من أن الخدمة نشطة
         if (!$service->is_active) {
             return response()->json(['error' => 'Service not available.'], 404);
         }

        $availabilityData = [];
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();
        $today = Carbon::today()->startOfDay();

        // جلب الحد الأقصى للأشهر مرة واحدة
        $maxMonthsSetting = Setting::where('key', 'months_available_for_booking')->value('value');
        $maxMonths = $maxMonthsSetting ? (int)$maxMonthsSetting : 3;
        $latestAllowedDate = Carbon::today()->addMonthsNoOverflow($maxMonths)->endOfMonth();

        // المرور على أيام الشهر
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            // التحقق المبدئي (ليس في الماضي وضمن المدى المسموح)
             if ($date->lt($today) || $date->gt($latestAllowedDate)) {
                 $availabilityData[$dateStr] = false; // الأيام غير الصالحة تعتبر غير متاحة
                 continue;
             }

             // **(المنطق الحالي - قد يكون بطيئاً)**
             // استدعاء الدالة الحالية لكل يوم للتحقق من وجود أوقات
             // يفضل تحسين هذا المنطق داخل AvailabilityService لاحقاً
             try {
                $availableSlots = $this->availabilityService->getAvailableSlots($service, $date->copy()); // استخدم نسخة لتجنب تعديل $date
                $availabilityData[$dateStr] = !empty($availableSlots); // true إذا كانت المصفوفة غير فارغة
             } catch (\Exception $e) {
                  Log::error("Error checking slots for date {$dateStr} in month view: " . $e->getMessage());
                  $availabilityData[$dateStr] = false; // افتراض عدم التوفر عند حدوث خطأ
             }
        }

        return response()->json($availabilityData);
    }
     // --- !!! نهاية الدالة الجديدة !!! ---

} // نهاية الكلاس