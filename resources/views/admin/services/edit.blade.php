{{-- resources/views/admin/services/edit.blade.php --}}

@extends('layouts.admin') {{-- استخدام التخطيط الرئيسي --}}

{{-- تحديد عنوان الصفحة مع اسم الخدمة الحالية --}}
@section('title', 'تعديل الخدمة: ' . $service->name_ar)

{{-- @section('header-actions') --}}
    {{-- يمكنك إضافة زر "العودة" هنا إذا أردت --}}
    {{-- <a href="{{ route('admin.services.index') }}" class="btn btn-secondary btn-sm">العودة لقائمة الخدمات</a> --}}
{{-- @endsection --}}

@section('content')

{{-- لا حاجة لـ container-fluid هنا لأنها موجودة في التخطيط الرئيسي admin.blade.php --}}

{{-- البطاقة الرئيسية التي تملأ المحتوى --}}
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-warning text-dark"> {{-- لون مختلف لرأس بطاقة التعديل --}}
        <h6 class="m-0 fw-bold"><i class="fas fa-edit me-2"></i>تعديل الخدمة: {{ $service->name_ar }}</h6>
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

        {{-- تعديل مسار الإرسال ليشمل ID الخدمة واستخدام PUT --}}
        <form action="{{ route('admin.services.update', $service->id) }}" method="POST">
            @csrf
            @method('PUT') {{-- تحديد أن هذا الطلب هو لتحديث (Update) --}}

            {{-- فئة الخدمة --}}
            <div class="mb-3">
                <label for="service_category_id" class="form-label">فئة الخدمة:<span class="text-danger">*</span></label>
                <select name="service_category_id" id="service_category_id" class="form-select @error('service_category_id') is-invalid @enderror" required>
                    <option value="" disabled>-- اختر الفئة --</option>
                    @foreach($categories as $category)
                        {{-- تحديد الخيار المحدد بناءً على القيمة القديمة أو قيمة الخدمة الحالية --}}
                        <option value="{{ $category->id }}" {{ old('service_category_id', $service->service_category_id) == $category->id ? 'selected' : '' }}>
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
                {{-- عرض القيمة القديمة أو قيمة الخدمة الحالية --}}
                <input type="text" id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $service->name_ar) }}" required>
                @error('name_ar')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            {{-- الوصف (عربي) --}}
            <div class="mb-3">
                <label for="description_ar" class="form-label">الوصف (بالعربية):</label>
                {{-- عرض القيمة القديمة أو قيمة الخدمة الحالية --}}
                <textarea id="description_ar" name="description_ar" class="form-control @error('description_ar') is-invalid @enderror" rows="5">{{ old('description_ar', $service->description_ar) }}</textarea>
                @error('description_ar')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            {{-- المدة والسعر في صف واحد --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="duration_hours" class="form-label">مدة الخدمة (بالساعات):<span class="text-danger">*</span></label>
                    {{-- عرض القيمة القديمة أو قيمة الخدمة الحالية --}}
                    <input type="number" id="duration_hours" name="duration_hours" class="form-control @error('duration_hours') is-invalid @enderror" value="{{ old('duration_hours', $service->duration_hours) }}" required min="0.5" step="0.5">
                    @error('duration_hours')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="price_sar" class="form-label">السعر (بالريال السعودي):<span class="text-danger">*</span></label>
                    {{-- عرض القيمة القديمة أو قيمة الخدمة الحالية --}}
                    <input type="number" id="price_sar" name="price_sar" class="form-control @error('price_sar') is-invalid @enderror" value="{{ old('price_sar', $service->price_sar) }}" required min="0" step="0.01">
                    @error('price_sar')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- ما تتضمنه الخدمة (عربي) --}}
            <div class="mb-3">
                <label for="included_items_ar" class="form-label">ما تتضمنه الخدمة (بالعربية):</label>
                {{-- عرض القيمة القديمة أو قيمة الخدمة الحالية --}}
                <textarea id="included_items_ar" name="included_items_ar" class="form-control @error('included_items_ar') is-invalid @enderror" rows="5">{{ old('included_items_ar', $service->included_items_ar) }}</textarea>
                @error('included_items_ar')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            {{-- خدمة فعالة؟ --}}
            <div class="mb-3 form-check">
                 {{-- تحديد حالة المربع بناءً على القيمة القديمة أو قيمة الخدمة الحالية --}}
                <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                <label for="is_active" class="form-check-label">خدمة فعالة؟</label>
                @error('is_active')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- أزرار التحديث والإلغاء --}}
            <div class="mt-4 d-flex justify-content-end">
                 <a href="{{ route('admin.services.index') }}" class="btn btn-secondary me-2">إلغاء</a>
                 {{-- تغيير نص الزر إلى تحديث ولونه إلى أصفر --}}
                 <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> تحديث الخدمة</button>
            </div>

        </form>
    </div> {{-- نهاية card-body --}}
</div> {{-- نهاية card --}}

@endsection

{{-- (أقسام push للسكريبتات والستايلات كما هي في صفحة الإنشاء) --}}
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