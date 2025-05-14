<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * عرض قائمة بجميع الخدمات.
     */
    public function index()
    {
        $services = Service::with('serviceCategory')->latest()->get();
        return view('admin.services.index', compact('services'));
    }

    /**
     * عرض نموذج إنشاء خدمة جديدة.
     */
    public function create()
    {
        $categories = ServiceCategory::orderBy('name_ar')->get();
        return view('admin.services.create', compact('categories'));
    }

    /**
     * تخزين الخدمة الجديدة في قاعدة البيانات.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'service_category_id' => 'required|exists:service_categories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'duration_hours' => 'required|integer|min:1',
            'price_sar' => 'required|numeric|min:0',
            'included_items_ar' => 'nullable|string',
            'included_items_en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ],[
            'service_category_id.required' => 'الرجاء اختيار فئة الخدمة.',
            'service_category_id.exists' => 'الفئة المختارة غير صالحة.',
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.',
            'duration_hours.required' => 'حقل مدة الخدمة مطلوب.',
            'duration_hours.integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً.',
            'duration_hours.min' => 'مدة الخدمة يجب أن تكون ساعة واحدة على الأقل.',
            'price_sar.required' => 'حقل السعر مطلوب.',
            'price_sar.numeric' => 'السعر يجب أن يكون رقماً.',
            'price_sar.min' => 'السعر لا يمكن أن يكون سالباً.',
        ]);

        $validatedData['is_active'] = $request->has('is_active');

        Service::create($validatedData);

        return redirect()->route('admin.services.index')
                         ->with('success', 'تمت إضافة الخدمة بنجاح.');
    }

    /**
     * عرض تفاصيل خدمة محددة (غير مستخدم حالياً).
     */
    public function show(Service $service)
    {
        // $service يتم جلبها تلقائياً بفضل Route Model Binding
        // يمكن استخدامه لعرض صفحة تفاصيل للخدمة إذا لزم الأمر
    }

    /**
     * عرض نموذج تعديل خدمة محددة.
     */
    public function edit(Service $service)
    {
        // $service يتم جلبها تلقائياً
        // نحتاج أيضاً لجلب الفئات لعرضها في قائمة منسدلة
        $categories = ServiceCategory::orderBy('name_ar')->get();
        return view('admin.services.edit', compact('service', 'categories'));
        // اسم الواجهة: resources/views/admin/services/edit.blade.php
    }

    /**
     * تحديث بيانات خدمة محددة في قاعدة البيانات.
     */
public function update(Request $request, Service $service)
{
    // $service يتم جلبها تلقائياً

    // 1. التحقق من صحة البيانات المدخلة
     $validatedData = $request->validate([
         'service_category_id' => 'required|exists:service_categories,id',
         'name_ar' => 'required|string|max:255',
         // تم إزالة 'required' من هنا
         'name_en' => 'nullable|string|max:255', // الآن يمكن أن يكون الحقل فارغاً
         'description_ar' => 'nullable|string',
         'description_en' => 'nullable|string',
         'duration_hours' => 'required|integer|min:1',
         'price_sar' => 'required|numeric|min:0',
         'included_items_ar' => 'nullable|string',
         'included_items_en' => 'nullable|string',
         'is_active' => 'nullable|boolean',
     ],[
         // يمكن إضافة رسائل تحقق مخصصة هنا أيضاً
         'service_category_id.required' => 'الرجاء اختيار فئة الخدمة.',
         'name_ar.required' => 'حقل الاسم مطلوب.', // تم تبسيط الرسالة أيضاً
         // ... باقي الرسائل ...
     ]);

     // 2. التعامل مع حقل 'is_active' (Checkbox)
     $validatedData['is_active'] = $request->has('is_active');

     // 3. تحديث بيانات الخدمة
     $service->update($validatedData);

     // 4. إعادة التوجيه مع رسالة نجاح
     return redirect()->route('admin.services.index')
                 ->with('success', 'تم تحديث الخدمة بنجاح.');
}

    /**
     * حذف خدمة محددة من قاعدة البيانات.
     */
    public function destroy(Service $service)
    {
        // $service يتم جلبها تلقائياً
        try {
            $service->delete();
             return redirect()->route('admin.services.index')
                         ->with('success', 'تم حذف الخدمة بنجاح.');

        } catch (\Exception $e) {
             \Log::error("Error deleting service: {$service->id} - {$e->getMessage()}");
            return redirect()->route('admin.services.index')
                         ->with('error', 'حدث خطأ أثناء محاولة حذف الخدمة.');
        }
    }
}