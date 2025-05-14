{{-- resources/views/admin/bookings/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'إدارة الحجوزات')

@section('content')

    {{-- منطقة الفلترة (تبقى كما هي) --}}
    <div class="card shadow-sm mb-4 border-0"> {{-- تعديل تنسيق البطاقة قليلاً --}}
        <div class="card-body">
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="status" class="form-label">تصفية حسب الحالة:</label>
                    <select name="status" id="status" class="form-select form-select-sm"> {{-- تصغير حجم الحقل --}}
                        <option value="">-- كل الحالات --</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- يمكنك إضافة فلاتر أخرى هنا (مثلاً حسب التاريخ) --}}
                <div class="col-md-auto align-self-end"> {{-- محاذاة للأسفل --}}
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i> إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    {{-- **تعديل: عرض الحجوزات باستخدام البطاقات بدلاً من الجدول** --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary">قائمة الحجوزات الواردة @if(request('status')) - ({{ $statuses[request('status')] ?? request('status') }}) @endif</h6>
        </div>
        <div class="card-body">
            {{-- شبكة لعرض البطاقات بشكل متجاوب --}}
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                @forelse ($bookings as $booking)
                    <div class="col">
                        <div class="card booking-card h-100 shadow-sm border-0"> {{-- استخدام نفس كلاس البطاقة من الداشبورد --}}
                            <div class="card-header d-flex justify-content-between align-items-center bg-light-subtle">
                                <h6 class="mb-0 fw-bold small">
                                    <i class="fas fa-user me-1"></i> {{ $booking->user?->name ?? 'N/A' }}
                                </h6>
                                <span class="badge bg-secondary rounded-pill small">#{{ $booking->id }}</span>
                            </div>
                            <div class="card-body pb-2"> {{-- تقليل الحشو السفلي قليلاً --}}
                                <p class="mb-2 text-dark">
                                    <i class="fas fa-concierge-bell fa-fw me-1 text-muted"></i>
                                    {{ $booking->service?->name_ar ?? 'N/A' }}
                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>
                                    {{ $booking->booking_datetime ? $booking->booking_datetime->translatedFormat('d M Y') : '-' }}
                                </p>
                                <p class="mb-0 small">
                                    <i class="fas fa-clock fa-fw me-1 text-muted"></i>
                                    {{ $booking->booking_datetime ? $booking->booking_datetime->translatedFormat('h:i A') : '-' }}
                                </p>
                            </div>
                            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center border-top-dashed pt-2">
                                {{-- استخدام نفس طريقة عرض الحالة من الكود الأصلي إذا كانت موجودة ومفضلة --}}
                                <span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }}"> {{-- استخدام المتغير الجاهز للكلاس --}}
                                    {{ $booking->status_label ?? $booking->status }} {{-- استخدام المتغير الجاهز للاسم --}}
                                </span>
                                {{-- أو استخدام الطريقة المضمنة إذا لم تكن المتغيرات السابقة متوفرة --}}
                                {{-- <span class="status-pill {{ 'bg-' . str_replace('_', '-', $booking->status ?? 'secondary') }} {{ 'badge-' . str_replace('_', '-', $booking->status ?? 'secondary') }}">
                                    {{ getBookingStatusTranslation($booking->status ?? '', $bookingStatusTranslations ?? []) }}
                                </span> --}}

                                <a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    {{-- رسالة في حالة عدم وجود حجوزات مطابقة للفلتر --}}
                    <div class="col-12">
                        <div class="alert alert-warning text-center">لا توجد حجوزات تطابق الفلترة الحالية.</div>
                    </div>
                @endforelse
            </div> {{-- نهاية row --}}
        </div> {{-- نهاية card-body --}}

         {{-- Pagination Links (تبقى كما هي أسفل البطاقة الرئيسية) --}}
         @if ($bookings->hasPages()) {{-- التأكد من وجود صفحات قبل عرض الروابط --}}
             <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                 {{ $bookings->appends(request()->query())->links() }} {{-- إضافة appends للحفاظ على الفلاتر عند التنقل --}}
             </div>
         @endif

    </div> {{-- نهاية card --}}

@endsection

@push('styles')
{{-- إضافة تنسيقات لبطاقات الحجوزات إذا لم تكن موجودة بشكل عام في admin.css --}}
<style>
/* يمكنك إعادة استخدام تنسيقات .booking-card من admin.css أو تعريفها هنا */
.booking-card .card-header { font-size: 0.85em; padding: 0.6rem 1rem; }
.booking-card .card-header .badge { font-size: 0.8em;}
.booking-card .card-body { padding: 1rem; font-size: 0.9em;}
.booking-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.booking-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef; }
.booking-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; } /* تأكيد ظهور الخط المنقط */
</style>
@endpush