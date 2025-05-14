<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory; // استيراد نموذج فئة الخدمة
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // تأكد من استيراد Log لاستخدامه في catch block

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
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ],[
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.',
        ]);

        ServiceCategory::create($validatedData);

        // تم التعديل هنا: استخدام اسم المسار الصحيح 'admin.service-categories.index'
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
        // اسم الواجهة: resources/views/admin/categories/edit.blade.php
    }

    /**
     * تحديث بيانات فئة محددة في قاعدة البيانات.
     */
    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        // $serviceCategory يتم جلبها تلقائياً

        // 1. التحقق من صحة البيانات المدخلة من نموذج التعديل
        $validatedData = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
        ],[
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.',
        ]);

        // 2. تحديث بيانات الفئة في قاعدة البيانات
        $serviceCategory->update($validatedData);

        // 3. إعادة التوجيه إلى صفحة عرض الفئات مع رسالة نجاح
        // تم التعديل هنا: استخدام اسم المسار الصحيح 'admin.service-categories.index'
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
            // حذف الفئة (سيتم حذف الخدمات المرتبطة بها تلقائياً بسبب onDelete('cascade'))
            $serviceCategory->delete();
             // تم التعديل هنا: استخدام اسم المسار الصحيح 'admin.service-categories.index'
             return redirect()->route('admin.service-categories.index')
                            ->with('success', 'تم حذف الفئة والخدمات المرتبطة بها بنجاح.');

        } catch (\Exception $e) {
            // يمكنك إضافة معالجة للخطأ هنا إذا لزم الأمر
             \Log::error("Error deleting category: {$serviceCategory->id} - {$e->getMessage()}");
            // تم التعديل هنا: استخدام اسم المسار الصحيح 'admin.service-categories.index'
            return redirect()->route('admin.service-categories.index')
                            ->with('error', 'حدث خطأ أثناء محاولة حذف الفئة.');
        }
    }
}