<?php

namespace App\Http\Controllers\Frontend; // <-- مسار Namespace للواجهة الأمامية

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Service; // تأكد من استيراد موديل الخدمة
use Illuminate\Http\JsonResponse; // لاستخدام JsonResponse بشكل صريح
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // استخدام Validator بشكل صريح
// use Illuminate\Validation\ValidationException; // لا نحتاج هذا هنا

class DiscountController extends Controller
{
    /**
     * Check the validity of a discount code via AJAX request.
     * التحقق من صلاحية كود الخصم عبر طلب AJAX.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function checkDiscount(Request $request): JsonResponse
    {
        // 1. التحقق المبدئي من المدخلات
        $validator = Validator::make($request->all(), [
            'discount_code' => 'required|string',
            'service_id' => 'required|integer|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'البيانات المرسلة غير كافية للتحقق.',
                'errors' => $validator->errors()
            ], 422); // Unprocessable Entity
        }

        $code = $request->input('discount_code');
        $serviceId = $request->input('service_id');

        try {
            $service = Service::findOrFail($serviceId); // جلب الخدمة لمعرفة السعر الأصلي
            $originalPrice = $service->price_sar;

            $discountCode = DiscountCode::where('code', $code)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', Carbon::today())
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhereDate('end_date', '>=', Carbon::today());
                })
                ->first();

            // 2. التحقق من وجود الكود وصلاحيته
            if (!$discountCode) {
                return response()->json([
                    'valid' => false,
                    'message' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'
                ], 404); // Not Found أو 422
            }

            // 3. التحقق من حد الاستخدام
            if (!is_null($discountCode->max_uses) && $discountCode->current_uses >= $discountCode->max_uses) {
                return response()->json([
                    'valid' => false,
                    'message' => 'لقد تم استخدام كود الخصم بالحد الأقصى.'
                ], 422); // Unprocessable Entity
            }

            // --- الكود صالح ---

            // 4. حساب قيمة الخصم
            $discountAmount = 0;
            if ($discountCode->type === DiscountCode::TYPE_PERCENTAGE) { // استخدام الثابت مباشرة
                $discountAmount = ($originalPrice * $discountCode->value) / 100;
            } elseif ($discountCode->type === DiscountCode::TYPE_FIXED) { // استخدام الثابت مباشرة
                $discountAmount = $discountCode->value;
            }
            // تأكد أن الخصم لا يتجاوز سعر الخدمة
            $discountAmount = min($discountAmount, $originalPrice);

            // --- !!! حساب السعر الجديد !!! ---
            $newPrice = $originalPrice - $discountAmount;
            // تأكد من أن السعر لا يقل عن صفر
            $newPrice = max(0, $newPrice);


            // 5. إرجاع رد ناجح مع قيمة الخصم والسعر الجديد
            return response()->json([
                'valid' => true,
                'message' => 'تم تطبيق كود الخصم بنجاح!',
                 // قيمة الخصم المنسقة للعرض
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                 // قيمة الخصم كرقم خام لتستخدمها JS إذا احتاجت
                'discount_value_raw' => $discountAmount,
                // !!! السعر الجديد بعد الخصم كرقم خام !!!
                'new_price_raw' => round($newPrice, 2), // التقريب لأقرب هللتين
                'currency' => 'ريال سعودي', // العملة
                'discount_type' => $discountCode->type // نوع الخصم (قد يفيد في JS)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             // التعامل مع حالة عدم العثور على الخدمة
             Log::warning('Discount Check API: Service not found.', ['service_id' => $serviceId]);
             return response()->json([
                 'valid' => false,
                 'message' => 'الخدمة المطلوبة غير موجودة.'
             ], 404); // Not Found
        } catch (\Exception $e) {
            // تسجيل الخطأ للرجوع إليه
            Log::error('Discount Check API Error: ' . $e->getMessage(), ['code' => $code, 'service_id' => $serviceId, 'exception' => $e]);
            return response()->json([
                'valid' => false,
                'message' => 'حدث خطأ أثناء التحقق من الكود. يرجى المحاولة لاحقاً.'
            ], 500); // Internal Server Error
        }
    }
}