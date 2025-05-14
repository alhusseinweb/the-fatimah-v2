<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // لاستخدام قواعد التحقق

class DiscountCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $discountCodes = DiscountCode::latest()->paginate(15);
        return view('admin.discount_codes.index', compact('discountCodes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $discountCode = new DiscountCode(['is_active' => true]); // كود جديد، مفعل افتراضياً
        $types = DiscountCode::types(); // جلب أنواع الخصومات
        return view('admin.discount_codes.create', compact('discountCode', 'types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // الكود يجب أن يكون فريداً وغير مكرر
            'code' => ['required', 'string', 'max:50', Rule::unique('discount_codes')],
            // النوع يجب أن يكون أحد الأنواع المعرفة في الموديل
            'type' => ['required', Rule::in(array_keys(DiscountCode::types()))],
            // القيمة يجب أن تكون رقماً، والحد الأدنى يعتمد على النوع
            'value' => ['required', 'numeric', 'min:0'],
            // تاريخ البدء مطلوب وهو تاريخ
            'start_date' => ['required', 'date'],
            // تاريخ الانتهاء اختياري، ولكن يجب أن يكون بعد تاريخ البدء إذا وُجد
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // الحد الأقصى للاستخدام اختياري، يجب أن يكون رقماً صحيحاً موجباً
            'max_uses' => ['nullable', 'integer', 'min:1'],
            // الحالة
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // التعامل مع checkbox الحالة
        $validatedData['is_active'] = $request->has('is_active');
        // تحويل قيمة النسبة المئوية إذا كانت أكبر من 100 (اختياري حسب منطق العمل)
        if ($validatedData['type'] === DiscountCode::TYPE_PERCENTAGE && $validatedData['value'] > 100) {
             return back()->withErrors(['value' => 'قيمة النسبة المئوية لا يمكن أن تتجاوز 100.'])->withInput();
        }


        DiscountCode::create($validatedData);

        return redirect()->route('admin.discount-codes.index')
                         ->with('success', 'تم إضافة كود الخصم بنجاح.');
    }

    /**
     * Display the specified resource. (Optional)
     */
    public function show(DiscountCode $discountCode)
    {
        return redirect()->route('admin.discount-codes.edit', $discountCode);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DiscountCode $discountCode)
    {
        $types = DiscountCode::types(); // جلب أنواع الخصومات
        return view('admin.discount_codes.edit', compact('discountCode', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DiscountCode $discountCode)
    {
        $validatedData = $request->validate([
            // الكود يجب أن يكون فريداً، مع تجاهل الكود الحالي عند التحقق
            'code' => ['required', 'string', 'max:50', Rule::unique('discount_codes')->ignore($discountCode->id)],
            'type' => ['required', Rule::in(array_keys(DiscountCode::types()))],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validatedData['is_active'] = $request->has('is_active');
         if ($validatedData['type'] === DiscountCode::TYPE_PERCENTAGE && $validatedData['value'] > 100) {
             return back()->withErrors(['value' => 'قيمة النسبة المئوية لا يمكن أن تتجاوز 100.'])->withInput();
        }


        $discountCode->update($validatedData);

        return redirect()->route('admin.discount-codes.index')
                         ->with('success', 'تم تحديث كود الخصم بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DiscountCode $discountCode)
    {
        try {
            // لا تسمح بالحذف إذا كان الكود مستخدماً (يمكن إضافة هذا التحقق إذا لزم الأمر)
            // if ($discountCode->current_uses > 0) {
            //     return redirect()->route('admin.discount-codes.index')
            //                      ->with('error', 'لا يمكن حذف كود الخصم لأنه مستخدم بالفعل.');
            // }
            $discountCode->delete();
            return redirect()->route('admin.discount-codes.index')
                             ->with('success', 'تم حذف كود الخصم بنجاح.');
        } catch (\Exception $e) {
            return redirect()->route('admin.discount-codes.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف كود الخصم.');
        }
    }

    /**
      * Toggle the active status of the discount code.
      */
    public function toggleActive(DiscountCode $discountCode)
    {
        $discountCode->update(['is_active' => !$discountCode->is_active]);
        $message = $discountCode->is_active ? 'تم تفعيل كود الخصم.' : 'تم تعطيل كود الخصم.';
        return redirect()->back()->with('success', $message);
    }
}