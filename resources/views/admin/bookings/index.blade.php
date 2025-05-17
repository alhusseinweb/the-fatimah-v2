{{-- resources/views/admin/bookings/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'إدارة الحجوزات')

@section('content')

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="status" class="form-label">تصفية حسب الحالة:</label>
                    {{-- $statuses يتم تمريرها من BookingController@index --}}
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">-- كل الحالات --</option>
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i> إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary">قائمة الحجوزات الواردة @if(request('status') && isset($statuses[request('status')])) - ({{ $statuses[request('status')] }}) @elseif(request('status')) - ({{ request('status') }}) @endif</h6>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                @forelse ($bookings as $booking)
                    <div class="col">
                        <div class="card booking-card h-100 shadow-sm border-0">
                            <div class="card-header d-flex justify-content-between align-items-center bg-light-subtle">
                                <h6 class="mb-0 fw-bold small">
                                    <i class="fas fa-user me-1"></i> {{ $booking->user?->name ?? 'N/A' }}
                                </h6>
                                <span class="badge bg-secondary rounded-pill small">#{{ $booking->id }}</span>
                            </div>
                            <div class="card-body pb-2">
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
                                <span class="status-pill {{ $booking->status_badge_class }}">
                                    {{ $booking->status_label }}
                                </span>
                                <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-warning text-center">لا توجد حجوزات تطابق الفلترة الحالية.</div>
                    </div>
                @endforelse
            </div>
        </div>

         @if ($bookings->hasPages())
             <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                 {{ $bookings->appends(request()->query())->links() }}
             </div>
         @endif
    </div>
@endsection

@push('styles')
<style>
.booking-card .card-header { font-size: 0.85em; padding: 0.6rem 1rem; }
.booking-card .card-header .badge { font-size: 0.8em;}
.booking-card .card-body { padding: 1rem; font-size: 0.9em;}
.booking-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.booking-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef !important; }
.booking-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
/* .status-pill كلاس معرف في show.blade.php، إذا لم يكن عامًا، عرفه هنا أو في admin.css */
</style>
@endpush
