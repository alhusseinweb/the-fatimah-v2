<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /**
     * عرض قائمة بجميع الخدمات.
     */
    public function index()
    {
        // --- MODIFICATION START: Change relationship name ---
        $services = Service::with('category')->latest()->get(); // تم تغيير 'serviceCategory' إلى 'category'
        // --- MODIFICATION END ---
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
                Rule::unique('services', 'name_ar')
            ],
            'name_en' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'name_en')->where(function ($query) use ($request) {
                    return $request->name_en !== null && $request->name_en !== '';
                })
            ],
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
            'name_ar.unique'   => 'اسم الخدمة بالعربية مُستخدم بالفعل.',
            'name_en.unique'   => 'اسم الخدمة بالإنجليزية مُستخدم بالفعل.',
            'duration_hours.required' => 'حقل مدة الخدمة مطلوب.',
            'duration_hours.integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً.',
            'duration_hours.min' => 'مدة الخدمة يجب أن تكون ساعة واحدة على الأقل.',
            'price_sar.required' => 'حقل السعر مطلوب.',
            'price_sar.numeric' => 'السعر يجب أن يكون رقماً.',
            'price_sar.min' => 'السعر لا يمكن أن يكون سالباً.',
        ]);

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
        // For now, redirect to edit or index if show is not implemented.
        return redirect()->route('admin.services.edit', $service);
    }

    /**
     * عرض نموذج تعديل خدمة محددة.
     */
    public function edit(Service $service)
    {
        $categories = ServiceCategory::orderBy('name_ar')->get();
        return view('admin.services.edit', compact('service', 'categories'));
    }

    /**
     * تحديث بيانات خدمة محددة في قاعدة البيانات.
     */
    public function update(Request $request, Service $service)
    {
        $validatedData = $request->validate([
            'service_category_id' => 'required|exists:service_categories,id',
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name_ar')->ignore($service->id)
            ],
            'name_en' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'name_en')->ignore($service->id)->where(function ($query) use ($request) {
                    return $request->name_en !== null && $request->name_en !== '';
                })
            ],
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'duration_hours' => 'required|integer|min:1',
            'price_sar' => 'required|numeric|min:0',
            'included_items_ar' => 'nullable|string',
            'included_items_en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ],[
            'service_category_id.required' => 'الرجاء اختيار فئة الخدمة.',
            'service_category_id.exists'   => 'الفئة المختارة غير صالحة.',
            'name_ar.required' => 'حقل اسم الخدمة بالعربية مطلوب.',
            'name_ar.unique'   => 'اسم الخدمة بالعربية مُستخدم بالفعل.',
            'name_en.unique'   => 'اسم الخدمة بالإنجليزية مُستخدم بالفعل.',
            'duration_hours.required' => 'حقل مدة الخدمة مطلوب.',
            'duration_hours.integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً.',
            'duration_hours.min' => 'مدة الخدمة يجب أن تكون ساعة واحدة على الأقل.',
            'price_sar.required' => 'حقل السعر مطلوب.',
            'price_sar.numeric' => 'السعر يجب أن يكون رقماً.',
            'price_sar.min' => 'السعر لا يمكن أن يكون سالباً.',
        ]);

        $validatedData['name_en'] = $validatedData['name_en'] ?? null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?? null;
        $validatedData['description_en'] = $validatedData['description_en'] ?? null;
        $validatedData['included_items_ar'] = $validatedData['included_items_ar'] ?? null;
        $validatedData['included_items_en'] = $validatedData['included_items_en'] ?? null;
        $validatedData['is_active'] = $request->has('is_active');

        $service->update($validatedData);

        return redirect()->route('admin.services.index')
                         ->with('success', 'تم تحديث الخدمة بنجاح.');
    }

    /**
     * حذف خدمة محددة من قاعدة البيانات.
     */
    public function destroy(Service $service)
    {
        try {
            // يمكنك إضافة تحقق هنا إذا كانت الخدمة مرتبطة بحجوزات نشطة قبل الحذف
            if ($service->bookings()->whereNotIn('status', [Booking::STATUS_COMPLETED_DELIVERED, Booking::STATUS_CANCELLED_BY_ADMIN, Booking::STATUS_CANCELLED_BY_USER])->exists()) {
                return redirect()->route('admin.services.index')
                                 ->with('error', 'لا يمكن حذف الخدمة لأنها مرتبطة بحجوزات قائمة. يرجى إلغاء أو إكمال الحجوزات أولاً.');
            }
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
