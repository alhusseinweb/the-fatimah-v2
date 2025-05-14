{{-- resources/views/admin/categories/create.blade.php --}}

@extends('layouts.admin')
@section('title', 'إضافة فئة خدمة جديدة')

@section('content')

<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 fw-bold"><i class="fas fa-plus me-2"></i>إضافة فئة خدمة جديدة</h6>
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

                <form action="{{ route('admin.service-categories.store') }}" method="POST">
                    @csrf

                    {{-- الاسم (بالعربية) --}}
                    <div class="mb-3">
                        <label for="name_ar" class="form-label">اسم الفئة (بالعربية):<span class="text-danger">*</span></label>
                        <input type="text" id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" required>
                        @error('name_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- الوصف (بالعربية) --}}
                    <div class="mb-3">
                        <label for="description_ar" class="form-label">الوصف (بالعربية):</label>
                        <textarea id="description_ar" name="description_ar" class="form-control @error('description_ar') is-invalid @enderror" rows="5">{{ old('description_ar') }}</textarea>
                        @error('description_ar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- أزرار الحفظ والإلغاء --}}
                    <div class="mt-4 d-flex justify-content-end">
                         <a href="{{ route('admin.service-categories.index') }}" class="btn btn-secondary me-2 px-4">إلغاء</a>
                         <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> حفظ الفئة</button>
                    </div>

                </form>

            </div> {{-- نهاية col --}}
        </div> {{-- نهاية row --}}
    </div> {{-- نهاية card-body --}}
</div> {{-- نهاية card --}}

@endsection