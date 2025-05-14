<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // تم استيراده بالفعل، جيد!
use Illuminate\Validation\Rule;    // لاستخدام قواعد التحقق المتقدمة مثل unique

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
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name_ar') // التأكد من أن الاسم العربي فريد
            ],
            'name_en' => [ // <-- تم التعديل هنا
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'name_en')->where(function ($query) use ($request) {
                    return $request->name_en !== null && $request->name_en !== ''; // فريد فقط إذا تم إدخاله وليس فارغًا
                })
            ],
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'duration_hours' => 'required|integer|min:1',
            'price_sar' => 'required|numeric|min:0',
            'included_items_ar' => 'nullable|string',
            'included_items_en' => 'nullable|string',
            'is_active' => 'nullable|boolean', // سيتعامل معه $request->has('is_active') لاحقًا
        ],[
            'service_category_id.required' => 'الرجاء اختيار فئة الخدمة.',
            'service_category_id.exists' => 'الفئة المختارة غير صالحة.',
            'name_ar.required' => 'حقل الاسم بالعربية مطلوب.',
            'name_ar.unique'   => 'اسم الخدمة بالعربية مُستخدم بالفعل.',
            // 'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.', // <-- تم حذف هذه الرسالة
            'name_en.unique'   => 'اسم الخدمة بالإنجليزية مُستخدم بالفعل.',
            'duration_hours.required' => 'حقل مدة الخدمة مطلوب.',
            'duration_hours.integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً.',
            'duration_hours.min' => 'مدة الخدمة يجب أن تكون ساعة واحدة على الأقل.',
            'price_sar.required' => 'حقل السعر مطلوب.',
            'price_sar.numeric' => 'السعر يجب أن يكون رقماً.',
            'price_sar.min' => 'السعر لا يمكن أن يكون سالباً.',
        ]);

        // التعامل مع القيم الاختيارية الفارغة وحقل is_active
        $validatedData['name_en'] = $validatedData['name_en'] ?? null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?? null;
        $validatedData['description_en'] = $validatedData['description_en'] ?? null;
        $validatedData['included_items_ar'] = $validatedData['included_items_ar'] ?? null;
        $validatedData['included_items_en'] = $validatedData['included_items_en'] ?? null;
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
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name_ar')->ignore($service->id) // تجاهل السجل الحالي عند التحقق من التفرد
            ],
            'name_en' => [ // <-- تم التعديل هنا
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'name_en')->ignore($service->id)->where(function ($query) use ($request) {
                    return $request->name_en !== null && $request->name_en !== ''; // فريد فقط إذا تم إدخاله وليس فارغًا
                })
            ],
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'duration_hours' => 'required|integer|min:1',
            'price_sar' => 'required|numeric|min:0',
            'included_items_ar' => 'nullable|string',
            'included_items_en' => 'nullable|string',
            'is_active' => 'nullable|boolean', // سيتعامل معه $request->has('is_active') لاحقًا
        ],[
            'service_category_id.required' => 'الرجاء اختيار فئة الخدمة.',
            'service_category_id.exists'   => 'الفئة المختارة غير صالحة.',
            'name_ar.required' => 'حقل اسم الخدمة بالعربية مطلوب.',
            'name_ar.unique'   => 'اسم الخدمة بالعربية مُستخدم بالفعل.',
            // 'name_en.required' => 'حقل الاسم بالإنجليزية مطلوب.', // <-- تم حذف هذه الرسالة
            'name_en.unique'   => 'اسم الخدمة بالإنجليزية مُستخدم بالفعل.',
            'duration_hours.required' => 'حقل مدة الخدمة مطلوب.',
            'duration_hours.integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً.',
            'duration_hours.min' => 'مدة الخدمة يجب أن تكون ساعة واحدة على الأقل.',
            'price_sar.required' => 'حقل السعر مطلوب.',
            'price_sar.numeric' => 'السعر يجب أن يكون رقماً.',
            'price_sar.min' => 'السعر لا يمكن أن يكون سالباً.',
        ]);

        // التعامل مع القيم الاختيارية الفارغة وحقل is_active
        $validatedData['name_en'] = $validatedData['name_en'] ?? null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?? null;
        $validatedData['description_en'] = $validatedData['description_en'] ?? null;
        $validatedData['included_items_ar'] = $validatedData['included_items_ar'] ?? null;
        $validatedData['included_items_en'] = $validatedData['included_items_en'] ?? null;
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
            Log::error("Error deleting service: {$service->id} - {$e->getMessage()}");
            return redirect()->route('admin.services.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الخدمة.');
        }
    }
}
