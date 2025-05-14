<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory; // استدعاء موديل فئات الخدمات
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of the active services grouped by category.
     * عرض قائمة الخدمات النشطة مجمعة حسب الفئات
     *
     * @return \Illuminate\View\View
     */
public function index()
{
    $categories = ServiceCategory::with(['services' => function ($query) {
                                     // هذا الشرط للخدمات داخل الفئة يبقى كما هو
                                     $query->where('is_active', true)
                                           ->orderBy('name_ar');
                                 }])
                                 // ->where('is_active', true) // <-- احذف هذا السطر أو ضع // قبله
                                 ->orderBy('name_ar') // ترتيب الفئات
                                 ->get();

    // التأكد من عدم جلب الفئات التي لا تحتوي على أي خدمات نشطة (بعد تحميلها)
    $filteredCategories = $categories->filter(function ($category) {
        return $category->services->isNotEmpty();
    });

    // تمرير الفئات المفلترة فقط إلى الواجهة
    return view('frontend.services.index', ['categories' => $filteredCategories]); // تم تغيير اسم المتغير هنا
}
}