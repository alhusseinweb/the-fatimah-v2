@extends('layouts.admin')
@section('title', 'تعديل بيانات العميل: ' . $customer->name)

@section('content')
<h1 class="h3 mb-4 text-gray-800">تعديل بيانات العميل: <span class="text-primary">{{ $customer->name }}</span></h1>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">نموذج تعديل البيانات</h6>
        <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> العودة لملف العميل
        </a>
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

        <form action="{{ route('admin.customers.update', $customer->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name', $customer->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="mobile_number" class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('mobile_number') is-invalid @enderror"
                               id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $customer->mobile_number) }}" required dir="ltr">
                        @error('mobile_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror"
                       id="email" name="email" value="{{ old('email', $customer->email) }}" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- يمكنك إضافة خيارات لتأكيد الجوال/البريد هنا إذا أردت --}}
            {{--
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="mobile_verified" id="mobile_verified" {{ $customer->mobile_verified_at ? 'checked' : '' }}>
                        <label class="form-check-label" for="mobile_verified">
                            الجوال مؤكد ({{ $customer->mobile_verified_at ? $customer->mobile_verified_at->format('Y-m-d') : 'غير مؤكد' }})
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email_verified" id="email_verified" {{ $customer->email_verified_at ? 'checked' : '' }}>
                        <label class="form-check-label" for="email_verified">
                            البريد مؤكد ({{ $customer->email_verified_at ? $customer->email_verified_at->format('Y-m-d') : 'غير مؤكد' }})
                        </label>
                    </div>
                </div>
            </div>
            --}}

            <hr>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> حفظ التعديلات
            </button>
            <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-outline-secondary">
                إلغاء
            </a>
        </form>
    </div>
</div>
@endsection