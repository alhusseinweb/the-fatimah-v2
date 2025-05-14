@extends('layouts.admin')
@section('title', 'إدارة العملاء')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">إدارة العملاء</h1>
    {{-- يمكنك إضافة زر لإنشاء عميل جديد إذا أردت هذه الميزة --}}
    {{-- <a href="#" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> إضافة عميل جديد</a> --}}
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">قائمة العملاء المسجلين</h6>
        <form action="{{ route('admin.customers.index') }}" method="GET" class="mt-2">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم, البريد, أو الجوال..." value="{{ request('search') }}">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>رقم الجوال</th>
                        <th>تاريخ التسجيل</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td dir="ltr">{{ $customer->mobile_number }}</td>
                            <td>{{ $customer->created_at->translatedFormat('d M Y, h:i A') }}</td>
                            <td>
                                <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-sm btn-primary" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                {{-- زر الحذف (بحذر) --}}
                                {{-- <form action="{{ route('admin.customers.destroy', $customer->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا العميل؟ لا يمكن التراجع عن هذا الإجراء.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف"><i class="fas fa-trash"></i></button>
                                </form> --}}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">لا يوجد عملاء مسجلون حالياً.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="mt-3">
                {{ $customers->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection