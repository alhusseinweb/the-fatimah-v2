<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory; // استيراد نموذج فئة الخدمة
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // تأكد من استيراد Log لاستخدامه في catch block
use Illuminate\Validation\Rule; // لاستخدام قواعد التحقق المتقدمة مثل unique

class ServiceCategoryController extends Controller
{
    /**
     * عرض قائمة بجميع فئات الخدمات.
     */
    public function index()
    {
        $categories = ServiceCategory::latest()->get();
        return view('admin.categories.index', compact('categories'));
    }

    /**
     * عرض نموذج إنشاء فئة خدمة جديدة.
     */
    public function create()
    {
        return view('admin.categories.create');
    }

    /**
     * تخزين فئة الخدمة الجديدة في قاعدة البيانات.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_categories', 'name_ar') // التأكد من أن الاسم العربي فريد
            ],
            'name_en' => [
                'nullable', // <-- تم التعديل هنا: أصبح اختياريًا
                'string',
                'max:255',
                Rule::unique('service_categories', 'name_en')->where(function ($query) use ($request) {
                    return $request->name_en !== null; // التأكد من أن الاسم الإنجليزي فريد فقط إذا تم إدخاله وليس فارغًا
                })
            ],
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ],[
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_ar.unique'   => 'الاسم بالعربية مُستخدم بالفعل.',
            // 'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.', // <-- تم حذف هذه الرسالة لأن الحقل لم يعد مطلوبًا
            'name_en.unique'   => 'الاسم بالإنجليزية مُستخدم بالفعل.',
        ]);

        // إذا كان الحقل الاختياري فارغًا، قم بتعيينه إلى null لضمان حفظ null في قاعدة البيانات بدلاً من سلسلة فارغة
        $validatedData['name_en'] = $validatedData['name_en'] ?? null;
        $validatedData['description_en'] = $validatedData['description_en'] ?? null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?? null; // جيد أن يتم هذا للوصف العربي أيضًا إذا كان اختياريًا

        ServiceCategory::create($validatedData);

        return redirect()->route('admin.service-categories.index')
                         ->with('success', 'تمت إضافة الفئة بنجاح.');
    }

    /**
     * عرض تفاصيل فئة محددة (غير مستخدم حالياً).
     */
    public function show(ServiceCategory $serviceCategory)
    {
        // $serviceCategory يتم جلبها تلقائياً بفضل Route Model Binding
        // return view('admin.categories.show', compact('serviceCategory'));
    }

    /**
     * عرض نموذج تعديل فئة محددة.
     */
    public function edit(ServiceCategory $serviceCategory)
    {
        // $serviceCategory يتم جلبها تلقائياً
        // عرض الواجهة مع تمرير بيانات الفئة المراد تعديلها
        return view('admin.categories.edit', compact('serviceCategory'));
    }

    /**
     * تحديث بيانات فئة محددة في قاعدة البيانات.
     */
    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        // $serviceCategory يتم جلبها تلقائياً

        // 1. التحقق من صحة البيانات المدخلة من نموذج التعديل
        $validatedData = $request->validate([
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_categories', 'name_ar')->ignore($serviceCategory->id) // تجاهل السجل الحالي عند التحقق من التفرد
            ],
            'name_en' => [
                'nullable', // <-- تم التعديل هنا: أصبح اختياريًا
                'string',
                'max:255',
                Rule::unique('service_categories', 'name_en')->ignore($serviceCategory->id)->where(function ($query) use ($request) {
                    return $request->name_en !== null; // التأكد من أن الاسم الإنجليزي فريد فقط إذا تم إدخاله وليس فارغًا
                })
            ],
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ],[
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_ar.unique'   => 'الاسم بالعربية مُستخدم بالفعل.',
            // 'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.', // <-- تم حذف هذه الرسالة
            'name_en.unique'   => 'الاسم بالإنجليزية مُستخدم بالفعل.',
        ]);

        // إذا كان الحقل الاختياري فارغًا، قم بتعيينه إلى null
        $validatedData['name_en'] = $validatedData['name_en'] ?? null;
        $validatedData['description_en'] = $validatedData['description_en'] ?? null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?? null;

        // 2. تحديث بيانات الفئة في قاعدة البيانات
        $serviceCategory->update($validatedData);

        // 3. إعادة التوجيه إلى صفحة عرض الفئات مع رسالة نجاح
        return redirect()->route('admin.service-categories.index')
                         ->with('success', 'تم تحديث الفئة بنجاح.');
    }

    /**
     * حذف فئة محددة من قاعدة البيانات.
     */
    public function destroy(ServiceCategory $serviceCategory)
    {
        // $serviceCategory يتم جلبها تلقائياً
        try {
            // حذف الفئة
            $serviceCategory->delete();
            return redirect()->route('admin.service-categories.index')
                             ->with('success', 'تم حذف الفئة والخدمات المرتبطة بها بنجاح.');

        } catch (\Exception $e) {
            Log::error("Error deleting category: {$serviceCategory->id} - {$e->getMessage()}");
            return redirect()->route('admin.service-categories.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الفئة.');
        }
    }
}
