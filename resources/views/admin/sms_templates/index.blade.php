{{-- resources/views/admin/sms_templates/index.blade.php --}}
@extends('layouts.admin')
@section('title', 'إدارة قوالب الرسائل النصية')

@section('content')
    <h1 class="h3 mb-4 text-gray-800">إدارة قوالب الرسائل النصية (SMS)</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">قائمة القوالب المتاحة</h6>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>الوصف</th>
                            <th>نوع المستلم</th>
                            <th>آخر تحديث</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>{{ $template->description ?? $template->notification_type }}</td>
                                <td>
                                    @if ($template->recipient_type == 'customer')
                                        <span class="badge bg-success">للعميل</span>
                                    @elseif ($template->recipient_type == 'admin')
                                        <span class="badge bg-info text-dark">للمدير</span>
                                    @else
                                        {{ $template->recipient_type }}
                                    @endif
                                </td>
                                <td>{{ $template->updated_at->diffForHumans() }}</td>
                                <td>
                                    <a href="{{ route('admin.sms-templates.edit', $template->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">لا توجد قوالب SMS معرفة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
             <p class="mt-3 text-muted small">
                 ملاحظة: يتم استخدام هذه القوالب لإنشاء محتوى رسائل SMS المرسلة تلقائياً من النظام.
                 تعديل القالب سيؤثر على جميع الرسائل المستقبلية من نفس النوع.
             </p>
        </div>
    </div>
@endsection