{{-- resources/views/admin/categories/edit.blade.php --}}

@extends('layouts.admin')
@section('title', 'تعديل فئة الخدمة: ' . $serviceCategory->name_ar)

@section('content')

<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-warning text-dark">
        <h6 class="m-0 fw-bold"><i class="fas fa-edit me-2"></i>تعديل فئة الخدمة: {{ $serviceCategory->name_ar }}</h6>
    </div>
    <div class="card-body">
         {{-- إضافة صف وعمود للتحكم بعرض النموذج الداخلي كما في صفحة إضافة خدمة --}}
         <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('admin.service-categories.update', $serviceCategory->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- الاسم (بالعربية) --}}
                    <div class="mb-3">
                        <label for="name_ar" class="form-label">اسم الفئة (بالعربية):<span class="text-danger">*</span></label>
                        <input type="text" id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar', $serviceCategory->name_ar) }}" required>
                        @error('name_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- الوصف (بالعربية) --}}
                    <div class="mb-3">
                        <label for="description_ar" class="form-label">الوصف (بالعربية):</label>
                        <textarea id="description_ar" name="description_ar" class="form-control @error('description_ar') is-invalid @enderror" rows="5">{{ old('description_ar', $serviceCategory->description_ar) }}</textarea>
                        @error('description_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- أزرار التحديث والإلغاء --}}
                    <div class="mt-4 d-flex justify-content-end">
                         <a href="{{ route('admin.service-categories.index') }}" class="btn btn-secondary me-2 px-4">إلغاء</a>
                         <button type="submit" class="btn btn-warning px-4"><i class="fas fa-save me-1"></i> تحديث الفئة</button>
                    </div>

                </form>

            </div> {{-- نهاية col --}}
        </div> {{-- نهاية row --}}
    </div> {{-- نهاية card-body --}}
</div> {{-- نهاية card --}}

@endsection