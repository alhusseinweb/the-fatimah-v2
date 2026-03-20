<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddOnService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AddOnServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AddOnService::query();

        if ($request->has('search_term')) {
            $searchTerm = $request->search_term;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name_ar', 'like', "%{$searchTerm}%")
                  ->orWhere('name_en', 'like', "%{$searchTerm}%")
                  ->orWhere('description_ar', 'like', "%{$searchTerm}%")
                  ->orWhere('description_en', 'like', "%{$searchTerm}%");
            });
        }

        $addOnServices = $query->latest()->paginate(10)->withQueryString();

        return view('admin.add_on_services.index', compact('addOnServices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $mainServices = Service::active()->orderBy('name_ar')->get();
        return view('admin.add_on_services.create', compact('mainServices'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'main_services' => 'nullable|array',
            'main_services.*' => 'exists:services,id',
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        
        // Ensure name_en and descriptions are truly null if empty
        $validatedData['name_en'] = $validatedData['name_en'] ?: null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?: null;
        $validatedData['description_en'] = $validatedData['description_en'] ?: null;

        $addOnService = AddOnService::create($validatedData);

        if ($request->has('main_services')) {
            $addOnService->applicableServices()->sync($request->main_services);
        }

        return redirect()->route('admin.add_on_services.index')
                         ->with('success', 'تمت إضافة الخدمة الإضافية بنجاح.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AddOnService $addOnService)
    {
        $mainServices = Service::active()->orderBy('name_ar')->get();
        return view('admin.add_on_services.edit', compact('addOnService', 'mainServices'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AddOnService $addOnService)
    {
        $validatedData = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'main_services' => 'nullable|array',
            'main_services.*' => 'exists:services,id',
        ]);

        $validatedData['is_active'] = $request->has('is_active');
        
        // Ensure name_en and descriptions are truly null if empty
        $validatedData['name_en'] = $validatedData['name_en'] ?: null;
        $validatedData['description_ar'] = $validatedData['description_ar'] ?: null;
        $validatedData['description_en'] = $validatedData['description_en'] ?: null;

        $addOnService->update($validatedData);

        if ($request->has('main_services')) {
            $addOnService->applicableServices()->sync($request->main_services);
        } else {
            $addOnService->applicableServices()->detach();
        }

        return redirect()->route('admin.add_on_services.index')
                         ->with('success', 'تم تحديث الخدمة الإضافية بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AddOnService $addOnService)
    {
        try {
            $addOnService->delete();
            return redirect()->route('admin.add_on_services.index')
                             ->with('success', 'تم حذف الخدمة الإضافية بنجاح.');
        } catch (\Exception $e) {
            Log::error("Error deleting AddOnService: " . $e->getMessage());
            return redirect()->route('admin.add_on_services.index')
                             ->with('error', 'حدث خطأ أثناء محاولة حذف الخدمة الإضافية.');
        }
    }
}
