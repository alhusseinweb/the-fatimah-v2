<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DiscountController extends Controller
{
    public function checkDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discount_code' => 'required|string',
            'service_id' => 'required|integer|exists:services,id',
            'booking_time' => 'nullable|date_format:H:i', // وقت الحجز للتحقق
            'selected_payment_method' => 'nullable|string', // طريقة الدفع المختارة للتحقق
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'البيانات المرسلة غير كافية للتحقق من كود الخصم.', // رسالة أوضح
                'errors' => $validator->errors()
            ], 422);
        }

        $code = $request->input('discount_code');
        $serviceId = $request->input('service_id');
        $bookingTimeInput = $request->input('booking_time');
        $selectedPaymentMethod = $request->input('selected_payment_method');

        try {
            $service = Service::findOrFail($serviceId);
            $originalPrice = $service->price_sar;

            $discountCode = DiscountCode::where('code', $code)
                ->active() // استخدام scope active
                ->first();

            if (!$discountCode) {
                return response()->json(['valid' => false, 'message' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'], 422); // استخدام 422 بشكل عام لأخطاء التحقق من الكود
            }

            if (!is_null($discountCode->max_uses) && $discountCode->current_uses >= $discountCode->max_uses) {
                return response()->json(['valid' => false, 'message' => 'لقد تم استخدام كود الخصم بالحد الأقصى.'], 422);
            }

            // التحقق من شروط طريقة الدفع
            // يتم التحقق فقط إذا تم إرسال طريقة دفع مختارة، وإذا كان للكود شروط على طرق الدفع
            if ($selectedPaymentMethod && !empty($discountCode->allowed_payment_methods)) {
                if (!in_array($selectedPaymentMethod, $discountCode->allowed_payment_methods)) {
                    Log::info("AJAX: Discount code {$code} not applicable for payment method {$selectedPaymentMethod}. Allowed: " . implode(', ', $discountCode->allowed_payment_methods));
                    return response()->json(['valid' => false, 'message' => 'كود الخصم غير صالح لطريقة الدفع المختارة حالياً.'], 422);
                }
            }

            // التحقق من شروط وقت الحجز
            // يتم التحقق فقط إذا تم إرسال وقت الحجز، وإذا كان للكود شروط على الوقت
            if ($bookingTimeInput && ($discountCode->applicable_from_time || $discountCode->applicable_to_time)) {
                try {
                    $bookingTimeCarbon = Carbon::createFromFormat('H:i', $bookingTimeInput);
                    $applicableFrom = $discountCode->applicable_from_time ? Carbon::parse($discountCode->applicable_from_time) : null;
                    $applicableTo = $discountCode->applicable_to_time ? Carbon::parse($discountCode->applicable_to_time) : null;

                    if ($applicableFrom && $bookingTimeCarbon->lt($applicableFrom)) {
                        Log::info("AJAX: Discount code {$code} not applicable at {$bookingTimeInput}. Starts at {$discountCode->applicable_from_time}");
                        return response()->json(['valid' => false, 'message' => 'كود الخصم غير صالح في هذا الوقت (يبدأ في وقت لاحق من اليوم).'], 422);
                    }
                    if ($applicableTo && $bookingTimeCarbon->gt($applicableTo)) {
                         Log::info("AJAX: Discount code {$code} not applicable at {$bookingTimeInput}. Ends at {$discountCode->applicable_to_time}");
                        return response()->json(['valid' => false, 'message' => 'كود الخصم غير صالح في هذا الوقت (انتهت صلاحيته لهذا اليوم).'], 422);
                    }
                } catch (\Exception $timeParseException){
                    Log::error("AJAX: Error parsing booking_time for discount check.", ['time_input' => $bookingTimeInput, 'error' => $timeParseException->getMessage()]);
                    // يمكن إرجاع خطأ عام هنا أو تجاهل شرط الوقت إذا كان التنسيق غير صحيح
                }
            }


            $discountAmount = 0;
            if ($discountCode->type === DiscountCode::TYPE_PERCENTAGE) {
                $discountAmount = ($originalPrice * $discountCode->value) / 100;
            } elseif ($discountCode->type === DiscountCode::TYPE_FIXED) {
                $discountAmount = $discountCode->value;
            }
            $discountAmount = min($discountAmount, $originalPrice); // تأكد أن الخصم لا يتجاوز سعر الخدمة
            $discountAmount = round($discountAmount, 2); // تقريب قيمة الخصم

            $newPrice = $originalPrice - $discountAmount;
            $newPrice = max(0, $newPrice); // تأكد أن السعر لا يقل عن صفر
            $newPrice = round($newPrice, 2);


            return response()->json([
                'valid' => true,
                'message' => 'تم تطبيق كود الخصم بنجاح!',
                'discount_amount' => number_format($discountAmount, 2, '.', ''), // قيمة الخصم المنسقة للعرض
                'discount_value_raw' => $discountAmount, // قيمة الخصم كرقم خام
                'new_price_raw' => $newPrice, // السعر الجديد بعد الخصم كرقم خام
                'currency' => 'ريال سعودي',
                'discount_type' => $discountCode->type,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             Log::warning('Discount Check API: Service not found.', ['service_id' => $serviceId, 'code' => $code]);
             return response()->json(['valid' => false, 'message' => 'الخدمة المطلوبة غير موجودة.'], 404);
        } catch (\Exception $e) {
            Log::error('Discount Check API Error: ' . $e->getMessage(), ['code' => $code, 'service_id' => $serviceId, 'exception_details' => $e]);
            return response()->json(['valid' => false, 'message' => 'حدث خطأ أثناء التحقق من الكود. يرجى المحاولة لاحقاً.'], 500);
        }
    }
}
