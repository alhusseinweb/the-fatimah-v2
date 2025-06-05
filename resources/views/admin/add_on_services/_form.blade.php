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
