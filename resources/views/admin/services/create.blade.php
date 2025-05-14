{{-- resources/views/admin/services/create.blade.php --}}

@extends('layouts.admin')
@section('title', 'إضافة خدمة جديدة')

{{-- @section('header-actions') --}}
    {{-- يمكنك إضافة زر "العودة" هنا إذا أردت --}}
    {{-- <a href="{{ route('admin.services.index') }}" class="btn btn-secondary btn-sm">العودة لقائمة الخدمات</a> --}}
{{-- @endsection --}}

@section('content')

{{-- لا حاجة لـ container-fluid هنا لأنها موجودة في التخطيط الرئيسي admin.blade.php --}}

{{-- **تعديل: إزالة row و col المحيطة بالبطاقة للسماح لها بأخذ العرض الكامل** --}}
{{-- <div class="row justify-content-center"> --}}
    {{-- <div class="col-lg-8 col-md-10"> --}}

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 fw-bold"><i class="fas fa-plus me-2"></i>إضافة خدمة جديدة</h6>
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('admin.services.store') }}" method="POST">
                    @csrf

                    {{-- فئة الخدمة --}}
                    <div class="mb-3">
                        <label for="service_category_id" class="form-label">فئة الخدمة:<span class="text-danger">*</span></label>
                        <select name="service_category_id" id="service_category_id" class="form-select @error('service_category_id') is-invalid @enderror" required>
                            <option value="" disabled selected>-- اختر الفئة --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('service_category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name_ar }}
                                </option>
                            @endforeach
                        </select>
                        @error('service_category_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- الاسم (عربي) --}}
                    <div class="mb-3">
                        <label for="name_ar" class="form-label">اسم الخدمة (بالعربية):<span class="text-danger">*</span></label>
                        <input type="text" id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" required>
                        @error('name_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- الوصف (عربي) --}}
                    <div class="mb-3">
                        <label for="description_ar" class="form-label">الوصف (بالعربية):</label>
                        <textarea id="description_ar" name="description_ar" class="form-control @error('description_ar') is-invalid @enderror" rows="5">{{ old('description_ar') }}</textarea>
                        @error('description_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- المدة والسعر في صف واحد --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="duration_hours" class="form-label">مدة الخدمة (بالساعات):<span class="text-danger">*</span></label>
                            <input type="number" id="duration_hours" name="duration_hours" class="form-control @error('duration_hours') is-invalid @enderror" value="{{ old('duration_hours') }}" required min="0.5" step="0.5">
                            @error('duration_hours')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="price_sar" class="form-label">السعر (بالريال السعودي):<span class="text-danger">*</span></label>
                            <input type="number" id="price_sar" name="price_sar" class="form-control @error('price_sar') is-invalid @enderror" value="{{ old('price_sar') }}" required min="0" step="0.01">
                            @error('price_sar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- ما تتضمنه الخدمة (عربي) --}}
                    <div class="mb-3">
                        <label for="included_items_ar" class="form-label">ما تتضمنه الخدمة (بالعربية):</label>
                        <textarea id="included_items_ar" name="included_items_ar" class="form-control @error('included_items_ar') is-invalid @enderror" rows="5">{{ old('included_items_ar') }}</textarea>
                        @error('included_items_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- خدمة فعالة؟ --}}
                    <div class="mb-3 form-check">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label for="is_active" class="form-check-label">خدمة فعالة؟</label>
                        @error('is_active')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- أزرار الحفظ والإلغاء --}}
                    <div class="mt-4 d-flex justify-content-end">
                         <a href="{{ route('admin.services.index') }}" class="btn btn-secondary me-2">إلغاء</a>
                         <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الخدمة</button>
                    </div>

                </form>
            </div> {{-- نهاية card-body --}}
        </div> {{-- نهاية card --}}

    {{-- </div> --}} {{-- نهاية col --}}
{{-- </div> --}} {{-- نهاية row --}}

@endsection

{{-- (أقسام push للسكريبتات والستايلات كما هي في الرد السابق) --}}
@push('scripts')
    <script src="https://cdn.tiny.cloud/1/125msbz4wq0uw092ih28wag6ixhxeeerdqf5v31mjfl2pq9e/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
       tinymce.init({
         selector: '#description_ar, #included_items_ar',
         plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste directionality quickbars wordcount',
         toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | rtl ltr | removeformat | code | help',
         height: 250,
         directionality: 'rtl',
         language: 'ar',
         menubar: false,
         statusbar: true,
         quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote',
         quickbars_insert_toolbar: 'quickimage quicktable',
         entity_encoding: "raw",
         setup: function (editor) {
             editor.on('change', function () {
                 tinymce.triggerSave();
             });
         }
       });
     </script>
@endpush

@push('styles')
<style>
    .tox-tinymce {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        max-width: 100%;
    }
    .invalid-feedback.d-block {
        margin-top: 0.25rem;
        font-size: 0.875em;
    }
    .form-label .text-danger {
        margin-right: 2px;
    }
</style>
@endpush