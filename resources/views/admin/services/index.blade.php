{{-- resources/views/admin/services/index.blade.php --}}

@extends('layouts.admin')
@section('title', 'إدارة الخدمات')

@section('content')

    {{-- رأس الصفحة وزر الإضافة --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة الخدمات</h1>
        <a href="{{ route('admin.services.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة خدمة جديدة
        </a>
    </div>

    {{-- التحقق من وجود خدمات لعرضها --}}
    @if($services->count() > 0)
        {{-- **جديد: استخدام شبكة Bootstrap لعرض البطاقات بشكل متجاوب** --}}
        {{-- row-cols-1: عمود واحد على أصغر الشاشات --}}
        {{-- row-cols-md-2: عمودان على الشاشات المتوسطة --}}
        {{-- row-cols-xl-3: ثلاثة أعمدة على الشاشات الكبيرة جداً (اختياري) --}}
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            @foreach($services as $service)
                <div class="col">
                    {{-- **جديد: بطاقة الخدمة** --}}
                    <div class="card service-card h-100 shadow-sm border-0"> {{-- h-100 لجعل البطاقات متساوية الارتفاع --}}
                        {{-- رأس البطاقة: اسم الخدمة وحالة التفعيل --}}
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <h6 class="mb-0 fw-bold text-primary">{{ $service->name_ar }}</h6>
                            <span class="badge {{ $service->is_active ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' }}">
                                {{ $service->is_active ? 'فعال' : 'غير فعال' }}
                            </span>
                        </div>
                        {{-- جسم البطاقة: باقي التفاصيل --}}
                        <div class="card-body d-flex flex-column"> {{-- استخدام flex-column لدفع الأزرار للأسفل --}}
                            <p class="mb-2 text-muted small">
                                <i class="fas fa-tag fa-fw me-1"></i>
                                {{ $service->serviceCategory?->name_ar ?? '-' }}
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-clock fa-fw me-1 text-muted"></i>
                                <strong>المدة:</strong> {{ $service->duration_hours }} ساعات
                            </p>
                            <p class="mb-3"> {{-- زيادة الهامش السفلي للسعر --}}
                                <i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>
                                <strong>السعر:</strong> {{ number_format($service->price_sar, 2) }} ريال
                            </p>

                            {{-- زر الإجراءات في الأسفل --}}
                            <div class="mt-auto text-end"> {{-- mt-auto يدفع هذا العنصر للأسفل --}}
                                {{-- زر التعديل --}}
                                <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                {{-- زر الحذف مع تأكيد --}}
                                <form action="{{ route('admin.services.destroy', $service->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الخدمة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                        <i class="fas fa-trash-alt"></i> حذف
                                    </button>
                                </form>
                            </div>
                        </div> {{-- نهاية card-body --}}
                    </div> {{-- نهاية card --}}
                </div> {{-- نهاية col --}}
            @endforeach
        </div> {{-- نهاية row --}}

        {{-- روابط الترقيم (Pagination) --}}
         @if ($services instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-4 pt-2 d-flex justify-content-center"> {{-- إضافة هامش علوي إضافي --}}
                 {{ $services->links() }}
            </div>
         @endif

    @else
        <div class="alert alert-warning text-center">لم يتم العثور على أي خدمات.</div>
    @endif

@endsection


{{-- إضافة تنسيقات خاصة بـ Soft Badges إذا لم تكن موجودة في CSS الرئيسي --}}
@push('styles')
<style>
    .badge.bg-success-soft { background-color: rgba(25, 135, 84, 0.15); }
    .badge.bg-danger-soft { background-color: rgba(220, 53, 69, 0.15); }
    .text-success { color: #198754 !important; }
    .text-danger { color: #dc3545 !important; }

    /* تحسين بسيط لبطاقة الخدمة */
    .service-card .card-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 0.75rem 1rem;
    }
    .service-card .card-body {
        padding: 1rem;
        font-size: 0.9em;
    }
    .service-card .card-body p i {
        width: 1.4em; /* محاذاة الأيقونات */
    }
    .service-card .card-body .btn {
        font-size: 0.85em; /* تصغير الأزرار قليلاً */
    }

</style>
@endpush