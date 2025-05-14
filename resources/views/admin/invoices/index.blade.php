{{-- resources/views/admin/invoices/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'إدارة الفواتير')

@section('content')

    {{-- منطقة الفلترة (تبقى كما هي) --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.invoices.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="status" class="form-label">تصفية حسب الحالة:</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">-- كل الحالات --</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- يمكنك إضافة فلاتر أخرى هنا --}}
                <div class="col-md-auto align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i> إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    {{-- **تعديل: عرض الفواتير باستخدام البطاقات بدلاً من الجدول** --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary">قائمة الفواتير @if(request('status')) - ({{ $statuses[request('status')] ?? request('status') }}) @endif</h6>
        </div>
        <div class="card-body">
            {{-- شبكة لعرض البطاقات بشكل متجاوب --}}
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                @forelse ($invoices as $invoice)
                    <div class="col">
                        <div class="card invoice-card h-100 shadow-sm border-0"> {{-- استخدام كلاس جديد invoice-card --}}
                            {{-- رأس البطاقة: رقم الفاتورة واسم العميل --}}
                            <div class="card-header d-flex justify-content-between align-items-center bg-light-subtle">
                                <h6 class="mb-0 fw-bold small">
                                    <i class="fas fa-file-invoice me-1"></i> فاتورة #{{ $invoice->invoice_number }}
                                </h6>
                                <span class="text-muted small" title="العميل">
                                    <i class="fas fa-user me-1"></i>{{ $invoice->booking?->user?->name ?? 'N/A' }}
                                </span>
                            </div>
                             {{-- جسم البطاقة: المبلغ، الحالة، التواريخ --}}
                            <div class="card-body pb-2">
                                <p class="mb-2 fw-bold fs-6"> {{-- حجم خط أكبر للمبلغ --}}
                                    <i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>
                                    {{ number_format($invoice->amount, 2) }} {{ $invoice->currency ?: 'ريال' }}
                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-info-circle fa-fw me-1 text-muted"></i>
                                    الحالة:
                                    {{-- استخدام نفس طريقة عرض الحالة من الكود الأصلي --}}
                                    <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }}">
                                        {{ $invoice->status_label ?? $invoice->status }}
                                    </span>
                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>
                                    تاريخ الإنشاء: {{ $invoice->created_at ? $invoice->created_at->format('Y-m-d') : '-' }}
                                </p>
                                @if($invoice->due_date)
                                    <p class="mb-2 small {{ $invoice->due_date->isPast() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled' ? 'text-danger fw-bold' : '' }}">
                                        <i class="fas fa-calendar-times fa-fw me-1 text-muted"></i>
                                        تاريخ الاستحقاق: {{ $invoice->due_date->format('Y-m-d') }}
                                         {{-- إشارة إذا كان متأخراً --}}
                                         @if($invoice->due_date->isPast() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled') (متأخر) @endif
                                    </p>
                                @endif
                                <p class="mb-0 small">
                                    <i class="fas fa-credit-card fa-fw me-1 text-muted"></i>
                                    طريقة الدفع: {{ $invoice->payment_method ?: '-' }}
                                </p>
                            </div>
                             {{-- تذييل البطاقة: الإجراءات --}}
                            <div class="card-footer bg-transparent text-end border-top-dashed pt-2">
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                </a>
                                {{-- يمكنك إضافة أزرار إجراءات أخرى هنا (مثل تحديث الحالة) --}}
                            </div>
                        </div>
                    </div>
                @empty
                    {{-- رسالة في حالة عدم وجود فواتير مطابقة للفلتر --}}
                    <div class="col-12">
                        <div class="alert alert-warning text-center">لا توجد فواتير تطابق الفلترة الحالية.</div>
                    </div>
                @endforelse
            </div> {{-- نهاية row --}}
        </div> {{-- نهاية card-body --}}

         {{-- Pagination Links --}}
         @if ($invoices->hasPages())
             <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                 {{ $invoices->appends(request()->query())->links() }}
             </div>
         @endif

    </div> {{-- نهاية card --}}

@endsection

@push('styles')
{{-- إضافة تنسيقات لبطاقات الفواتير إذا لم تكن موجودة بشكل عام في admin.css --}}
<style>
/* يمكنك إعادة استخدام تنسيقات .booking-card أو .service-card أو تعريف .invoice-card */
.invoice-card .card-header { font-size: 0.85em; padding: 0.6rem 1rem; }
.invoice-card .card-body { padding: 1rem; }
.invoice-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.invoice-card .card-body p.small { font-size: 0.85em; }
.invoice-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef; }
.invoice-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; }
</style>
@endpush