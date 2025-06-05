@extends('layouts.admin')

@section('title', 'إدارة الخدمات الإضافية')

@section('content')
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">إدارة الخدمات الإضافية</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="{{ route('admin.add_on_services.create') }}" class="btn btn-primary">
                <i class="fas fa-plus fa-sm me-1"></i> إضافة خدمة إضافية جديدة
            </a>
        </div>
    </div>

    {{-- شريط البحث --}}
    <form method="GET" action="{{ route('admin.add_on_services.index') }}" class="mb-3">
        <div class="input-group">
            <input type="text" name="search_term" class="form-control" placeholder="ابحث بالاسم أو الوصف..." value="{{ request('search_term') }}">
            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
            @if(request('search_term'))
                <a href="{{ route('admin.add_on_services.index') }}" class="btn btn-outline-danger" title="إلغاء البحث"><i class="fas fa-times"></i></a>
            @endif
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">الاسم (عربي)</th>
                            <th scope="col">الاسم (إنجليزي)</th>
                            <th scope="col">السعر</th>
                            <th scope="col">الحالة</th>
                            <th scope="col">تاريخ الإنشاء</th>
                            <th scope="col" style="width: 15%;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($addOnServices as $service)
                            <tr>
                                <td>{{ $loop->iteration + ($addOnServices->currentPage() - 1) * $addOnServices->perPage() }}</td>
                                <td>{{ $service->name_ar }}</td>
                                <td>{{ $service->name_en ?: '-' }}</td>
                                <td>{{ number_format($service->price, 2) }} ريال</td>
                                <td>
                                    @if ($service->is_active)
                                        <span class="badge bg-success-soft text-success">فعالة</span>
                                    @else
                                        <span class="badge bg-danger-soft text-danger">غير فعالة</span>
                                    @endif
                                </td>
                                <td>{{ $service->created_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.add_on_services.edit', $service->id) }}" class="btn btn-sm btn-outline-primary" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.add_on_services.destroy', $service->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذه الخدمة الإضافية؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-exclamation-circle fa-2x text-muted mb-2"></i><br>
                                    لا توجد خدمات إضافية لعرضها حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($addOnServices->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $addOnServices->links() }}
            </div>
        @endif
    </div>
@endsection
