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
use Illuminate\Validation\Rule; // تأكد من استيراد Rule

class DiscountController extends Controller
{
    // --- MODIFICATION START: New function to get available discounts ---
    /**
     * Get available discount codes for a given booking context.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableDiscounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'payment_method' => 'nullable|string',
            'booking_time' => 'nullable|date_format:H:i',
        ]);

        $now = Carbon::now();
        $bookingTime = isset($validated['booking_time']) ? Carbon::parse($validated['booking_time']) : null;
        $paymentMethod = $validated['payment_method'] ?? null;

        $query = DiscountCode::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereRaw('current_uses < max_uses');
            });

        // Filter by booking time if provided
        if ($bookingTime) {
            $query->where(function ($q) use ($bookingTime) {
                $q->whereNull('applicable_from_time')
                  ->orWhereTime('applicable_from_time', '<=', $bookingTime);
            })
            ->where(function ($q) use ($bookingTime) {
                $q->whereNull('applicable_to_time')
                  ->orWhereTime('applicable_to_time', '>=', $bookingTime);
            });
        }
            
        // Filter by payment method if provided
        if ($paymentMethod) {
            $query->where(function ($q) use ($paymentMethod) {
                $q->whereNull('allowed_payment_methods') // Available for all methods
                  ->orWhereJsonContains('allowed_payment_methods', $paymentMethod); // Or available for the selected method
            });
        }
            
        // Here you can add more logic in the future, e.g., if a discount is tied to specific services.
        // For now, it gets all discounts matching the criteria above.

        $discounts = $query->get();

        // Format data for the frontend
        $formattedDiscounts = $discounts->map(function ($discount) {
            return [
                'code' => $discount->code,
                'description' => $this->generateDiscountDescription($discount), // Helper function to generate a description
            ];
        });

        return response()->json(['available_discounts' => $formattedDiscounts]);
    }

    /**
     * Helper function to generate a user-friendly description for a discount.
     * @param DiscountCode $discount
     * @return string
     */
    private function generateDiscountDescription(DiscountCode $discount): string
    {
        if ($discount->type === DiscountCode::TYPE_FIXED) {
            return "خصم بقيمة " . number_format($discount->value, 0) . " ريال";
        } elseif ($discount->type === DiscountCode::TYPE_PERCENTAGE) {
            return "خصم بنسبة " . number_format($discount->value, 0) . "%";
        }
        return "خصم خاص: " . $discount->code; // Fallback description
    }
    // --- MODIFICATION END ---


    /**
     * Check the validity of a specific discount code.
     * (Your existing function remains largely the same)
     */
    public function checkDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'discount_code' => 'required|string',
            'service_id' => 'required|integer|exists:services,id',
            'booking_time' => 'nullable|date_format:H:i',
            'selected_payment_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'البيانات المرسلة غير كافية للتحقق من كود الخصم.',
                'errors' => $validator->errors()
            ], 422);
        }

        $code = $request->input('discount_code');
        $serviceId = $request->input('service_id');
        $bookingTimeInput = $request->input('booking_time');
        $selectedPaymentMethod = $request->input('selected_payment_method');

        try {
            $service = Service::findOrFail($serviceId);
            $originalPrice = (float) $service->price_sar;

            $discountCode = DiscountCode::where('code', $code)
                ->active()
                ->first();

            if (!$discountCode) {
                return response()->json(['valid' => false, 'message' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'], 422);
            }
            
            // Re-using the logic from the model if these methods exist, which is good practice
            if (method_exists($discountCode, 'isExpired') && $discountCode->isExpired()) {
                 return response()->json(['valid' => false, 'message' => 'هذا الكود منتهي الصلاحية.'], 422);
            }

            if (method_exists($discountCode, 'isUsageLimitReached') && $discountCode->isUsageLimitReached()) {
                return response()->json(['valid' => false, 'message' => 'لقد تم استخدام هذا الكود بالحد الأقصى.'], 422);
            }

            if (method_exists($discountCode, 'isApplicableForPaymentMethod') && !$discountCode->isApplicableForPaymentMethod($selectedPaymentMethod)) {
                return response()->json(['valid' => false, 'message' => 'هذا الخصم لا ينطبق على طريقة الدفع المختارة.'], 422);
            }

            if (method_exists($discountCode, 'isApplicableForTime') && !$discountCode->isApplicableForTime($bookingTimeInput)) {
                return response()->json(['valid' => false, 'message' => 'هذا الخصم غير متاح في الوقت المحدد للحجز.'], 422);
            }


            $discountAmount = 0;
            if (method_exists($discountCode, 'calculateDiscount')) {
                 $discountAmount = $discountCode->calculateDiscount($originalPrice);
            } else { // Fallback logic if the method doesn't exist
                 if ($discountCode->type === DiscountCode::TYPE_PERCENTAGE) {
                    $discountAmount = ($originalPrice * $discountCode->value) / 100;
                } elseif ($discountCode->type === DiscountCode::TYPE_FIXED) {
                    $discountAmount = $discountCode->value;
                }
            }
            
            $discountAmount = round(min($discountAmount, $originalPrice), 2);
            $newPrice = round(max(0, $originalPrice - $discountAmount), 2);

            return response()->json([
                'valid' => true,
                'message' => $this->generateDiscountDescription($discountCode), // استخدام الدالة المساعدة لتوحيد الوصف
                'discount_value_raw' => $discountAmount,
                'new_price_raw' => $newPrice,
                'currency' => 'ريال',
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
