<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule; // لاستخدام Rule

class DiscountCodeController extends Controller
{
    // خيارات طرق الدفع التي يمكن للخصم أن ينطبق عليها
    protected function getPaymentMethodOptions(): array
    {
        return [
            'bank_transfer' => 'التحويل البنكي',
            'tamara' => 'تمارا',
            // أضف المزيد هنا إذا لزم الأمر من إعدادات الموقع مثلاً
        ];
    }

    public function index()
    {
        $discountCodes = DiscountCode::orderBy('created_at', 'desc')->paginate(10);
        // جلب مصفوفة الأنواع لترجمتها في العرض
        // هذا السطر موجود بالفعل في ملف index.blade.php الذي أرفقته، لذلك لا داعي لتمريره من هنا إذا كان الأمر كذلك.
        // $types = DiscountCode::types();
        return view('admin.discount_codes.index', compact('discountCodes'));
    }

    public function create()
    {
        $discountCode = new DiscountCode(['is_active' => true, 'start_date' => Carbon::today()]); // قيم افتراضية
        $types = DiscountCode::types();
        $paymentMethodOptions = $this->getPaymentMethodOptions();
        return view('admin.discount_codes.create', compact('discountCode', 'types', 'paymentMethodOptions'));
    }

    public function store(Request $request)
    {
        $paymentMethodKeys = array_keys($this->getPaymentMethodOptions());

        $validatedData = $request->validate([
            'code' => 'required|string|unique:discount_codes,code|max:255',
            'type' => ['required', Rule::in(array_keys(DiscountCode::types()))],
            'value' => 'required|numeric|min:0.01', // يجب أن تكون القيمة أكبر من صفر عادةً
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'allowed_payment_methods' => 'nullable|array',
            'allowed_payment_methods.*' => ['string', Rule::in($paymentMethodKeys)],
            'applicable_from_time' => 'nullable|date_format:H:i',
            // إذا تم تحديد وقت الانتهاء، يجب أن يكون بعد وقت البدء، ولكن فقط إذا تم تحديد وقت البدء أيضاً
            'applicable_to_time' => ['nullable', 'date_format:H:i', Rule::requiredIf(function () use ($request) {
                return !is_null($request->input('applicable_from_time'));
            }), 'after:applicable_from_time'],
        ],[
            'applicable_to_time.after' => 'وقت انتهاء تطبيق الخصم يجب أن يكون بعد وقت البدء.',
            'applicable_to_time.required_if' => 'يجب تحديد وقت انتهاء تطبيق الخصم إذا تم تحديد وقت البدء.'
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        $validatedData['allowed_payment_methods'] = $request->input('allowed_payment_methods', null);
        // إذا كان applicable_from_time فارغًا ولكن applicable_to_time ليس كذلك، قم بإفراغه أيضًا (أو العكس حسب المنطق المطلوب)
        if(empty($validatedData['applicable_from_time']) && !empty($validatedData['applicable_to_time'])){
            $validatedData['applicable_to_time'] = null;
        }
        if(!empty($validatedData['applicable_from_time']) && empty($validatedData['applicable_to_time'])){
             // إذا أردت أن يكون وقت الانتهاء مطلوباً إذا تم تحديد وقت البدء، القاعدة أعلاه ستفعل ذلك
             // أو يمكنك وضع قيمة افتراضية مثل نهاية اليوم إذا كان هذا هو المطلوب
        }


        DiscountCode::create($validatedData);

        return redirect()->route('admin.discount-codes.index')->with('success', 'تم إضافة كود الخصم بنجاح.');
    }

    public function edit(DiscountCode $discountCode)
    {
        $types = DiscountCode::types();
        $paymentMethodOptions = $this->getPaymentMethodOptions();
        return view('admin.discount_codes.edit', compact('discountCode', 'types', 'paymentMethodOptions'));
    }

    public function update(Request $request, DiscountCode $discountCode)
    {
        $paymentMethodKeys = array_keys($this->getPaymentMethodOptions());

        $validatedData = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('discount_codes')->ignore($discountCode->id)],
            'type' => ['required', Rule::in(array_keys(DiscountCode::types()))],
            'value' => 'required|numeric|min:0.01',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'allowed_payment_methods' => 'nullable|array',
            'allowed_payment_methods.*' => ['string', Rule::in($paymentMethodKeys)],
            'applicable_from_time' => 'nullable|date_format:H:i',
            'applicable_to_time' => ['nullable', 'date_format:H:i', Rule::requiredIf(function () use ($request) {
                return !is_null($request->input('applicable_from_time'));
            }), 'after:applicable_from_time'],
        ],[
            'applicable_to_time.after' => 'وقت انتهاء تطبيق الخصم يجب أن يكون بعد وقت البدء.',
            'applicable_to_time.required_if' => 'يجب تحديد وقت انتهاء تطبيق الخصم إذا تم تحديد وقت البدء.'
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        $validatedData['allowed_payment_methods'] = $request->input('allowed_payment_methods', null);

        if(empty($validatedData['applicable_from_time']) && !empty($validatedData['applicable_to_time'])){
            $validatedData['applicable_to_time'] = null;
        }


        if (isset($validatedData['max_uses']) && !is_null($validatedData['max_uses'])) {
            if ($discountCode->current_uses > $validatedData['max_uses']) {
                return back()->withErrors(['max_uses' => 'الحد الأقصى للاستخدام لا يمكن أن يكون أقل من عدد الاستخدامات الحالية ('.$discountCode->current_uses.').'])->withInput();
            }
        }


        $discountCode->update($validatedData);

        return redirect()->route('admin.discount-codes.index')->with('success', 'تم تحديث كود الخصم بنجاح.');
    }

    public function destroy(DiscountCode $discountCode)
    {
        // يمكنك إضافة منطق هنا للتحقق مما إذا كان الكود مستخدمًا في حجوزات نشطة قبل الحذف
        // على سبيل المثال، التحقق من جدول bookings
        if ($discountCode->bookings()->exists()) {
            return redirect()->route('admin.discount-codes.index')->with('error', 'لا يمكن حذف كود الخصم لأنه مستخدم في حجوزات حالية.');
        }
        $discountCode->delete();
        return redirect()->route('admin.discount-codes.index')->with('success', 'تم حذف كود الخصم بنجاح.');
    }

    public function toggleActive(DiscountCode $discountCode)
    {
        $discountCode->update(['is_active' => !$discountCode->is_active]);
        $message = $discountCode->is_active ? 'تم تفعيل كود الخصم.' : 'تم تعطيل كود الخصم.';
        return redirect()->route('admin.discount-codes.index')->with('success', $message);
    }
}
