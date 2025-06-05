@csrf
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name_ar" class="form-label">اسم الخدمة (العربية) <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('name_ar') is-invalid @enderror" id="name_ar" name="name_ar" value="{{ old('name_ar', $addOnService->name_ar ?? '') }}" required>
        @error('name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="name_en" class="form-label">اسم الخدمة (الإنجليزية)</label>
        <input type="text" class="form-control @error('name_en') is-invalid @enderror" id="name_en" name="name_en" value="{{ old('name_en', $addOnService->name_en ?? '') }}">
        @error('name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label for="price" class="form-label">السعر (ريال سعودي) <span class="text-danger">*</span></label>
    <input type="number" step="0.01" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price', $addOnService->price ?? '') }}" required min="0">
    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="description_ar" class="form-label">الوصف (العربية)</label>
    <textarea class="form-control @error('description_ar') is-invalid @enderror" id="description_ar" name="description_ar" rows="3">{{ old('description_ar', $addOnService->description_ar ?? '') }}</textarea>
    @error('description_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="description_en" class="form-label">الوصف (الإنجليزية)</label>
    <textarea class="form-control @error('description_en') is-invalid @enderror" id="description_en" name="description_en" rows="3">{{ old('description_en', $addOnService->description_en ?? '') }}</textarea>
    @error('description_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

{{-- --- MODIFICATION START: Add Main Services Selection --- --}}
@php
    // افترض أنك ستقوم بتمرير $mainServices من المتحكم
    // $mainServices = \App\Models\Service::where('is_active', true)->orderBy('name_ar')->get();
    // من الأفضل تمريرها من المتحكم لتجنب الاستعلامات في الـ view

    $selectedMainServices = collect(); // مجموعة فارغة بشكل افتراضي
    if (isset($addOnService) && $addOnService->relationLoaded('applicableServices')) {
        $selectedMainServices = $addOnService->applicableServices->pluck('id');
    } elseif (old('main_services')) {
        $selectedMainServices = collect(old('main_services'));
    } elseif (isset($addOnService)) {
        // إذا لم تكن العلاقة محملة، قم بتحميلها عند الحاجة (للتعديل)
        $selectedMainServices = $addOnService->applicableServices()->pluck('services.id'); // services.id لتجنب الغموض
    }
@endphp

@if(isset($mainServices) && $mainServices->count() > 0)
<div class="mb-3">
    <label class="form-label fw-bold">الخدمات الرئيسية التي تظهر معها هذه الخدمة الإضافية:</label>
    <div class="row">
        @foreach($mainServices as $mainService)
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="main_services[]" value="{{ $mainService->id }}" id="main_service_{{ $mainService->id }}"
                           {{ $selectedMainServices->contains($mainService->id) ? 'checked' : '' }}>
                    <label class="form-check-label" for="main_service_{{ $mainService->id }}">
                        {{ $mainService->name_ar }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
    @error('main_services') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    @error('main_services.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
@else
<div class="alert alert-info small">لا توجد خدمات رئيسية متاحة حالياً لربطها. يرجى إضافة خدمات رئيسية أولاً.</div>
@endif
{{-- --- MODIFICATION END --- --}}


<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $addOnService->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">فعالة (ستظهر للعميل)</label>
</div>

<div class="d-flex justify-content-end">
    <a href="{{ route('admin.add_on_services.index') }}" class="btn btn-outline-secondary me-2">إلغاء</a>
    <button type="submit" class="btn btn-primary">
        {{ isset($addOnService) ? 'تحديث الخدمة الإضافية' : 'إضافة الخدمة الإضافية' }}
    </button>
</div>
