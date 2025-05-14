@extends('layouts.admin')
@section('title', 'ملف العميل: ' . $customer->name)

@php
    // دوال ترجمة الحالات (كما في dashboard.blade.php)
    $bookingStatusTranslations = App\Models\Booking::statuses(); // افترضنا وجود دالة statuses في موديل Booking
    $invoiceStatusTranslations = App\Models\Invoice::statuses(); // افترضنا وجود دالة statuses في موديل Invoice

    if (!function_exists('getBookingStatusTranslation')) {
        function getBookingStatusTranslation($status, $translations) {
            return $translations[$status] ?? Str::title(str_replace('_', ' ', $status));
        }
    }
    if (!function_exists('getInvoiceStatusTranslation')) {
        function getInvoiceStatusTranslation($status, $translations) {
            return $translations[$status] ?? Str::title(str_replace('_', ' ', $status));
        }
    }
@endphp

@push('styles')
<style>
    .profile-header { background-color: #f8f9fa; padding: 1.5rem; border-bottom: 1px solid #dee2e6; }
    .profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,.1); }
    .data-card .card-header { background-color: #e9ecef; font-weight: bold; }
    .status-pill { padding: 0.3em 0.7em; border-radius: 0.25rem; font-size: 0.8em; color: white; }
    /* يمكنك إضافة ألوان مخصصة للـ status-pill كما في dashboard */
    .status-pill.bg-pending, .status-pill.bg-unpaid, .status-pill.bg-pending_confirmation { background-color: #ffc107; color: #000 !important; }
    .status-pill.bg-confirmed, .status-pill.bg-partially_paid { background-color: #0dcaf0; color: #000 !important; }
    .status-pill.bg-paid, .status-pill.bg-completed { background-color: #198754; }
    .status-pill.bg-cancelled, .status-pill.bg-failed, .status-pill.bg-expired { background-color: #dc3545; }
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="profile-header mb-4 text-center">
    {{-- يمكنك إضافة صورة رمزية للعميل إذا كان لديك هذا الحقل --}}
    {{-- <img src="{{ $customer->avatar_url ?? asset('path/to/default-avatar.png') }}" alt="{{ $customer->name }}" class="profile-avatar mb-2"> --}}
    <i class="fas fa-user-circle fa-5x text-secondary mb-2"></i>
    <h2 class="h4 mb-1">{{ $customer->name }}</h2>
    <p class="text-muted mb-1"><i class="fas fa-envelope me-1"></i> {{ $customer->email }}</p>
    <p class="text-muted mb-0" dir="ltr"><i class="fas fa-phone me-1"></i> {{ $customer->mobile_number }}</p>
    <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-outline-primary btn-sm mt-2">
        <i class="fas fa-edit"></i> تعديل بيانات العميل
    </a>
</div>

{{-- إجمالي المبالغ المستحقة --}}
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-left-danger shadow-sm h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            إجمالي المبالغ المستحقة (غير مدفوعة / مدفوعة جزئياً)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($totalDueAmount, 2) }} ر.س</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- الحجوزات والفواتير غير المكتملة --}}
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm data-card h-100">
            <div class="card-header">
                <i class="fas fa-calendar-alt me-1"></i> الحجوزات الجارية/القادمة (غير المكتملة)
            </div>
            <div class="card-body">
                @if($ongoingBookings->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($ongoingBookings as $booking)
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">{{ $booking->service->name_ar ?? 'خدمة غير محددة' }}</h6>
                                    <small class="text-muted">{{ $booking->booking_datetime->translatedFormat('d M Y, h:i A') }}</small>
                                </div>
                                <p class="mb-1">
                                    الحالة: <span class="status-pill bg-{{ str_replace('_', '-', $booking->status) }}">{{ getBookingStatusTranslation($booking->status, $bookingStatusTranslations) }}</span>
                                    @if($booking->invoice)
                                        | الفاتورة: #{{ $booking->invoice->invoice_number }}
                                        (<span class="status-pill bg-{{ str_replace('_', '-', $booking->invoice->status) }}">{{ getInvoiceStatusTranslation($booking->invoice->status, $invoiceStatusTranslations) }}</span>)
                                    @endif
                                </p>
                                <div class="text-end">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">عرض الحجز</a>
                                    @if($booking->invoice)
                                    <a href="{{ route('admin.invoices.show', $booking->invoice->id) }}" class="btn btn-sm btn-outline-secondary ms-1">عرض الفاتورة</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-muted mt-3">لا توجد حجوزات جارية أو قادمة حالياً.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm data-card h-100">
            <div class="card-header">
                <i class="fas fa-file-invoice-dollar me-1"></i> الفواتير غير المدفوعة / المدفوعة جزئياً
            </div>
            <div class="card-body">
                @if($unpaidInvoices->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($unpaidInvoices as $invoice)
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">فاتورة #{{ $invoice->invoice_number }}</h6>
                                    <small class="text-muted">{{ $invoice->created_at->translatedFormat('d M Y') }}</small>
                                </div>
                                <p class="mb-1">
                                    الخدمة: {{ $invoice->booking->service->name_ar ?? '-' }} <br>
                                    المبلغ: <span class="fw-bold">{{ number_format($invoice->amount, 2) }} ر.س</span> |
                                    المدفوع: <span class="text-success">{{ number_format($invoice->total_paid_amount, 2) }} ر.س</span> |
                                    المتبقي: <span class="text-danger">{{ number_format($invoice->remaining_amount, 2) }} ر.س</span>
                                </p>
                                <p class="mb-1">
                                    الحالة: <span class="status-pill bg-{{ str_replace('_', '-', $invoice->status) }}">{{ getInvoiceStatusTranslation($invoice->status, $invoiceStatusTranslations) }}</span>
                                </p>
                                 <div class="text-end">
                                    <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="btn btn-sm btn-outline-primary">عرض الفاتورة</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-muted mt-3">لا توجد فواتير مستحقة حالياً.</p>
                @endif
            </div>
        </div>
    </div>
</div>


{{-- قسم الحجوزات المكتملة --}}
<div class="card shadow-sm data-card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <span><i class="fas fa-history me-1"></i> سجل الحجوزات المكتملة</span>
            @if(!$completedBookings)
            <a href="{{ route('admin.customers.show', ['customer' => $customer->id, 'view_completed' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-eye"></i> عرض الحجوزات المكتملة
            </a>
            @else
            <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-eye-slash"></i> إخفاء الحجوزات المكتملة
            </a>
            @endif
        </div>
    </div>
    @if($completedBookings)
        <div class="card-body">
            @if($completedBookings->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>رقم الحجز</th>
                                <th>الخدمة</th>
                                <th>تاريخ الموعد</th>
                                <th>حالة الحجز</th>
                                <th>الفاتورة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($completedBookings as $booking)
                                <tr>
                                    <td>{{ $booking->id }}</td>
                                    <td>{{ $booking->service->name_ar ?? 'N/A' }}</td>
                                    <td>{{ $booking->booking_datetime->translatedFormat('d M Y, h:i A') }}</td>
                                    <td><span class="status-pill bg-{{ str_replace('_', '-', $booking->status) }}">{{ getBookingStatusTranslation($booking->status, $bookingStatusTranslations) }}</span></td>
                                    <td>
                                        @if($booking->invoice)
                                            #{{ $booking->invoice->invoice_number }} (<span class="status-pill bg-{{ str_replace('_', '-', $booking->invoice->status) }}">{{ getInvoiceStatusTranslation($booking->invoice->status, $invoiceStatusTranslations) }}</span>)
                                        @else
                                            لا يوجد
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($completedBookings->hasPages())
                    <div class="mt-2">
                        {{ $completedBookings->appends(['view_completed' => 1] + request()->except('completed_page'))->links() }}
                    </div>
                @endif
            @else
                <p class="text-center text-muted mt-3">لا توجد حجوزات مكتملة لهذا العميل.</p>
            @endif
        </div>
    @endif
</div>

@endsection