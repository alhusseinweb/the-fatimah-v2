{{-- resources/views/admin/categories/index.blade.php --}}

@extends('layouts.admin')
@section('title', 'إدارة فئات الخدمات')

@section('content')

    {{-- رأس قسم إدارة الفئات وزر الإضافة --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة فئات الخدمات</h1>
        <a href="{{ route('admin.service-categories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة فئة جديدة
        </a>
    </div>

    {{-- التحقق من وجود فئات لعرضها --}}
    @if($categories->count() > 0)
        {{-- **جديد: استخدام شبكة Bootstrap لعرض البطاقات** --}}
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            @foreach($categories as $category)
                <div class="col">
                    {{-- **جديد: بطاقة الفئة** --}}
                    <div class="card category-card h-100 shadow-sm border-0"> {{-- h-100 لجعل البطاقات متساوية الارتفاع --}}
                        {{-- رأس البطاقة: اسم الفئة --}}
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-bold text-primary">{{ $category->name_ar }}</h6>
                            {{-- يمكنك إضافة الاسم الإنجليزي هنا إذا أردت كنص صغير --}}
                            {{-- <small class="text-muted d-block">{{ $category->name_en }}</small> --}}
                        </div>
                        {{-- جسم البطاقة: الوصف (إذا وجد) والأزرار --}}
                        <div class="card-body d-flex flex-column">
                            {{-- عرض الوصف (مع تحديد عدد الأحرف) --}}
                            @if($category->description_ar)
                                <p class="mb-3 text-muted small flex-grow-1">
                                    <i class="fas fa-info-circle fa-fw me-1"></i>
                                    {{ Str::limit($category->description_ar, 120) }} {{-- عرض أول 120 حرف --}}
                                </p>
                            @else
                                {{-- ترك مسافة إذا لم يكن هناك وصف لدفع الأزرار للأسفل --}}
                                <div class="flex-grow-1"></div>
                            @endif

                            {{-- أزرار الإجراءات في الأسفل --}}
                            <div class="mt-auto text-end pt-2 border-top-dashed">
                                <a href="{{ route('admin.service-categories.edit', $category->id) }}" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                    <i class="fas fa-edit"></i> <span class="d-none d-md-inline">تعديل</span>
                                </a>
                                <form action="{{ route('admin.service-categories.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('تحذير! حذف هذه الفئة سيؤدي أيضاً إلى حذف جميع الخدمات المرتبطة بها. هل أنت متأكد من المتابعة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                        <i class="fas fa-trash-alt"></i> <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </form>
                            </div>
                        </div> {{-- نهاية card-body --}}
                    </div> {{-- نهاية card --}}
                </div> {{-- نهاية col --}}
            @endforeach
        </div> {{-- نهاية row --}}

        {{-- روابط الترقيم (Pagination) --}}
         @if ($categories instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-4 pt-2 d-flex justify-content-center">
                 {{ $categories->links() }}
            </div>
         @endif

    @else
        <div class="alert alert-warning text-center">لم يتم العثور على فئات خدمات.</div>
    @endif

@endsection

{{-- إضافة تنسيقات خاصة ببطاقات الفئات إذا لم تكن موجودة --}}
@push('styles')
<style>
    /* تحسين بسيط لبطاقة الفئة */
    .category-card .card-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 0.75rem 1rem;
    }
    .category-card .card-body {
        padding: 1rem;
    }
    .category-card .card-body p.small {
        font-size: 0.85em; /* تصغير خط الوصف */
        line-height: 1.6;
    }
     .category-card .card-body p i.fa-fw {
        width: 1.4em;
        text-align: center;
        color: #a0aec0;
    }
    .category-card .card-body .btn {
        font-size: 0.85em; /* تصغير الأزرار قليلاً */
    }
    /* خط منقط لفاصل الأزرار */
    .border-top-dashed {
        border-top: 1px dashed #e9ecef;
    }
</style>
@endpush