@extends('layouts.admin')
@section('title', 'ملف العميل: ' . $customer->name)

@php
    // --- MODIFICATION START: Use the correct method to get statuses ---
    // افترض أن هذه الدوال موجودة في الموديلات المعنية
    // إذا لم تكن موجودة في موديل Invoice، قد تحتاج إلى إنشاء دالة مشابهة أو تمرير مصفوفة الحالات من المتحكم
    $bookingStatusTranslations = \App\Models\Booking::getStatusesWithOptions();
    
    if (method_exists(\App\Models\Invoice::class, 'getStatusesWithOptions')) {
        $invoiceStatusTranslations = \App\Models\Invoice::getStatusesWithOptions();
    } elseif (method_exists(\App\Models\Invoice::class, 'statuses')) { // Fallback for old name
        $invoiceStatusTranslations = \App\Models\Invoice::statuses(); // This might still cause an error if 'statuses' is completely removed
    } else {
        // Provide a default or ensure it's passed from the controller if method doesn't exist
        $invoiceStatusTranslations = [
            \App\Models\Invoice::STATUS_UNPAID => 'غير مدفوعة',
            \App\Models\Invoice::STATUS_PAID => 'مدفوعة',
            \App\Models\Invoice::STATUS_PARTIALLY_PAID => 'مدفوعة جزئياً',
            \App\Models\Invoice::STATUS_CANCELLED => 'ملغاة',
            \App\Models\Invoice::STATUS_FAILED => 'فشلت',
            \App\Models\Invoice::STATUS_PENDING => 'قيد الانتظار',
            \App\Models\Invoice::STATUS_EXPIRED => 'منتهية الصلاحية',
            \App\Models\Invoice::STATUS_PENDING_CONFIRMATION => 'بانتظار التأكيد (تحويل)',
        ];
        // Log::warning('Invoice getStatusesWithOptions() method not found, using default array in admin/customers/show.blade.php');
    }
    // --- MODIFICATION END ---

    // الدوال المساعدة يمكن تركها كما هي، أو إعادة تسميتها لتجنب التضارب إذا كانت معرفة بشكل عام
    if (!function_exists('getCustomerShowBookingStatusTranslation')) { // Renamed
        function getCustomerShowBookingStatusTranslation($status, $translations) {
            if (empty($status)) return '-'; // التعامل مع القيم الفارغة
            return $translations[$status] ?? Str::title(str_replace('_', ' ', $status));
        }
    }
    if (!function_exists('getCustomerShowInvoiceStatusTranslation')) { // Renamed
        function getCustomerShowInvoiceStatusTranslation($status, $translations) {
            if (empty($status)) return '-'; // التعامل مع القيم الفارغة
            return $translations[$status] ?? Str::title(str_replace('_', ' ', $status));
        }
    }
@endphp

@push('styles')
<style>
    .profile-header { background-color: #f8f9fa; padding: 1.5rem; border-bottom: 1px solid #dee2e6; }
    .profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,.1); }
    .data-card .card-header { background-color: #e9ecef; font-weight: bold; }
    .status-pill { padding: 0.3em 0.7em; border-radius: 0.25rem; font-size: 0.8em; color: white !important; display: inline-block; }
    /* استخدام كلاسات badges من bootstrap أو Accessors في الموديل أفضل من تعريفها هنا مباشرة */
    /* لنفترض أن الموديلات Invoice و Booking لديها status_badge_class accessor */
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
        <div class="card border-start border-danger border-4 shadow-sm h-100 py-2"> {{-- تعديل لـ border-start --}}
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            إجمالي المبالغ المستحقة (غير مدفوعة / مدفوعة جزئياً)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($totalDueAmount ?? 0, 2) }} ر.س</div>
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
                @if($ongoingBookings && $ongoingBookings->isNotEmpty()) {{-- التأكد من أن المتغير موجود وليس فارغاً --}}
                    <div class="list-group list-group-flush">
                        @foreach($ongoingBookings as $booking)
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">{{ $booking->service->name_ar ?? 'خدمة غير محددة' }}</h6>
                                    <small class="text-muted">{{ $booking->booking_datetime->translatedFormat('d M Y, h:i A') }}</small>
                                </div>
                                <p class="mb-1">
                                    الحالة: <span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }}">{{ getCustomerShowBookingStatusTranslation($booking->status, $bookingStatusTranslations) }}</span>
                                    @if($booking->invoice)
                                        | الفاتورة: <a href="{{ route('admin.invoices.show', $booking->invoice->id) }}">#{{ $booking->invoice->invoice_number }}</a>
                                        (<span class="status-pill {{ $booking->invoice->status_badge_class ?? 'bg-secondary' }}">{{ getCustomerShowInvoiceStatusTranslation($booking->invoice->status, $invoiceStatusTranslations) }}</span>)
                                    @endif
                                </p>
                                <div class="text-end mt-1">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-sm btn-outline-primary">عرض الحجز</a>
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
                @if($unpaidInvoices && $unpaidInvoices->isNotEmpty()) {{-- التأكد من أن المتغير موجود وليس فارغاً --}}
                    <div class="list-group list-group-flush">
                        @foreach($unpaidInvoices as $invoice)
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">فاتورة <a href="{{ route('admin.invoices.show', $invoice->id) }}">#{{ $invoice->invoice_number }}</a></h6>
                                    <small class="text-muted">{{ $invoice->created_at->translatedFormat('d M Y') }}</small>
                                </div>
                                <p class="mb-1 small">
                                    الخدمة: {{ $invoice->booking->service->name_ar ?? '-' }} <br>
                                    المبلغ: <span class="fw-bold">{{ number_format($invoice->amount, 2) }} ر.س</span> |
                                    المدفوع: <span class="text-success">{{ number_format($invoice->total_paid_amount, 2) }} ر.س</span> |
                                    المتبقي: <span class="text-danger">{{ number_format($invoice->remaining_amount, 2) }} ر.س</span>
                                </p>
                                <p class="mb-1">
                                    الحالة: <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }}">{{ getCustomerShowInvoiceStatusTranslation($invoice->status, $invoiceStatusTranslations) }}</span>
                                </p>
                                 <div class="text-end mt-1">
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
            @if(empty(request()->query('view_completed'))) {{-- تم تعديل الشرط هنا --}}
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
    @if(request()->query('view_completed') == 1 && isset($completedBookings)) {{-- تم تعديل الشرط هنا --}}
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
                                    <td><span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }}">{{ getCustomerShowBookingStatusTranslation($booking->status, $bookingStatusTranslations) }}</span></td>
                                    <td>
                                        @if($booking->invoice)
                                            <a href="{{ route('admin.invoices.show', $booking->invoice->id) }}">#{{ $booking->invoice->invoice_number }}</a>
                                             (<span class="status-pill {{ $booking->invoice->status_badge_class ?? 'bg-secondary' }}">{{ getCustomerShowInvoiceStatusTranslation($booking->invoice->status, $invoiceStatusTranslations) }}</span>)
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
                        {{-- تأكد من أن paginator يمرر view_completed=1 للحفاظ على الحالة عند التنقل بين الصفحات --}}
                        {{ $completedBookings->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <p class="text-center text-muted mt-3">لا توجد حجوزات مكتملة لهذا العميل.</p>
            @endif
        </div>
    @endif
</div>

@endsection
