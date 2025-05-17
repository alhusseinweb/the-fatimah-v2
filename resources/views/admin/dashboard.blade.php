@extends('layouts.admin')
@section('title', 'لوحة التحكم الرئيسية')

@php
    // *** تم التعديل هنا لاستخدام الدالة الصحيحة من موديل Booking ***
    $bookingStatusTranslations = \App\Models\Booking::getStatusesWithOptions();
    // افترض أن Invoice model لديه دالة مشابهة أو أنك ستمرر ترجمات حالات الفاتورة من المتحكم
    // إذا كان Invoice model لا يحتوي على دالة statuses()، قد تحتاج إلى تعديل هذا أيضًا
    // أو التأكد من أن المتحكم DashboardController يمرر $invoiceStatusTranslations
    $invoiceStatusTranslations = \App\Models\Invoice::statuses(); // تأكد أن هذه الدالة موجودة في Invoice.php

    // الدوال المساعدة يمكن تركها كما هي إذا كانت تستخدم $translations بشكل صحيح
    if (!function_exists('getBookingStatusTranslation')) {
        function getBookingStatusTranslation($status, $translations) {
            // إذا كان $status فارغًا أو null، أرجع شرطة أو نصًا افتراضيًا
            if (empty($status)) return '-';
            return $translations[$status] ?? Illuminate\Support\Str::title(str_replace('_', ' ', $status));
        }
    }
    if (!function_exists('getInvoiceStatusTranslation')) {
        function getInvoiceStatusTranslation($status, $translations) {
            if (empty($status)) return '-';
            return $translations[$status] ?? Illuminate\Support\Str::title(str_replace('_', ' ', $status));
        }
    }
@endphp

@section('content')

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="mb-1">أهلاً بك مجدداً, {{ Auth::user()->name }}!</h4>
                    <p class="text-muted mb-0">نظرة سريعة على حالة نظام حجز المواعيد.</p>
                    @if(isset($smsLimitWarning) && $smsLimitWarning)
                        <div class="alert {{ str_contains($smsLimitWarning, 'تجاوزت') || str_contains($smsLimitWarning, 'إيقاف') ? 'alert-danger' : 'alert-warning' }} alert-dismissible fade show mt-3" role="alert">
                            <i class="fas {{ str_contains($smsLimitWarning, 'تجاوزت') || str_contains($smsLimitWarning, 'إيقاف') ? 'fa-exclamation-triangle' : 'fa-info-circle' }} me-2"></i>
                            <strong>تنبيه بخصوص رسائل SMS:</strong> {{ $smsLimitWarning }}
                            <a href="{{ route('admin.settings.edit') }}#sms_settings_card" class="alert-link ms-2">مراجعة الإعدادات</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(isset($nextConfirmedBooking) && $nextConfirmedBooking)
                        <div class="next-appointment-notice mt-3">
                            <strong><i class="fas fa-bell me-2"></i> تنبيه: الموعد القادم</strong>
                            <p class="details mt-2 mb-1">
                                <i class="fas fa-fw fa-concierge-bell"></i> <strong>الخدمة:</strong> {{ $nextConfirmedBooking->service?->name_ar ?? 'N/A' }} <br>
                                <i class="fas fa-fw fa-user"></i> <strong>العميل:</strong> {{ $nextConfirmedBooking->user?->name ?? 'N/A' }} <br>
                                <i class="fas fa-fw fa-calendar-alt"></i> <strong>التاريخ:</strong> {{ \Carbon\Carbon::parse($nextConfirmedBooking->booking_datetime)->translatedFormat('l, d F Y') }} <br>
                                <i class="fas fa-fw fa-clock"></i> <strong>الوقت:</strong> {{ \Carbon\Carbon::parse($nextConfirmedBooking->booking_datetime)->translatedFormat('h:i A') }} ({{ \Carbon\Carbon::parse($nextConfirmedBooking->booking_datetime)->diffForHumans() }})
                            </p>
                            <a href="{{ route('admin.bookings.show', $nextConfirmedBooking->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> عرض تفاصيل الحجز
                            </a>
                        </div>
                    @else
                        <div class="alert alert-light text-center mt-3" role="alert">
                            <i class="fas fa-info-circle me-1"></i> لا توجد مواعيد مؤكدة قادمة قريباً.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 mb-4">
        <div class="col">
            <div class="card shadow-sm h-100 stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div>
                        <div class="text-muted text-uppercase small mb-1">إجمالي الحجوزات</div>
                        <div class="h4 mb-0 fw-bold">{{ $totalBookings ?? '0' }}</div>
                    </div>
                    <div class="icon text-primary"> <i class="fas fa-calendar-check"></i> </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm h-100 stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div>
                        <div class="text-muted text-uppercase small mb-1">فواتير تحتاج متابعة</div>
                        <div class="h4 mb-0 fw-bold">{{ $pendingPaymentInvoicesCount ?? '0' }}</div>
                    </div>
                    <div class="icon text-warning"> <i class="fas fa-file-invoice-dollar"></i> </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm h-100 stat-card border-start border-success border-4">
                <div class="card-body">
                    <div>
                        <div class="text-muted text-uppercase small mb-1">إجمالي العملاء</div>
                        <div class="h4 mb-0 fw-bold">{{ $totalCustomers ?? '0' }}</div>
                    </div>
                    <div class="icon text-success"> <i class="fas fa-users"></i> </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card shadow-sm h-100 stat-card border-start {{ isset($smsLimitWarning) && (str_contains($smsLimitWarning, 'تجاوزت') || str_contains($smsLimitWarning, 'إيقاف')) ? 'border-danger' : 'border-info' }} border-4">
                <div class="card-body">
                    <div>
                        <div class="text-muted text-uppercase small mb-1">رسائل SMS هذا الشهر</div>
                        <div class="h5 mb-0 fw-bold">
                            {{ $currentMonthSmsCount ?? '0' }}
                            @if(isset($smsMonthlyLimit) && $smsMonthlyLimit > 0)
                                / {{ $smsMonthlyLimit }}
                            @else
                                <small>(لا يوجد حد)</small>
                            @endif
                        </div>
                         @if(isset($smsMonthlyLimit) && $smsMonthlyLimit > 0 && isset($currentMonthSmsCount) && $currentMonthSmsCount < $smsMonthlyLimit)
                            <small class="text-muted">المتبقي: {{ $smsMonthlyLimit - $currentMonthSmsCount }}</small>
                         @elseif(isset($smsMonthlyLimit) && $smsMonthlyLimit > 0 && isset($currentMonthSmsCount) && $currentMonthSmsCount >= $smsMonthlyLimit)
                            <small class="text-danger">تم الوصول للحد!</small>
                         @endif
                    </div>
                    <div class="icon {{ isset($smsLimitWarning) && (str_contains($smsLimitWarning, 'تجاوزت') || str_contains($smsLimitWarning, 'إيقاف')) ? 'text-danger' : 'text-info' }}">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                </div>
                 @if(isset($smsMonthlyLimit) && $smsMonthlyLimit > 0 && isset($currentMonthSmsCount))
                <div class="progress" style="height: 5px;">
                    @php
                        $smsPercentage = ($smsMonthlyLimit > 0) ? round(($currentMonthSmsCount / $smsMonthlyLimit) * 100) : 0;
                        $progressClass = 'bg-info';
                        if ($smsPercentage >= 80 && $smsPercentage < 100) $progressClass = 'bg-warning';
                        if ($smsPercentage >= 100) $progressClass = 'bg-danger';
                    @endphp
                    <div class="progress-bar {{ $progressClass }}" role="progressbar" style="width: {{ $smsPercentage }}%;" aria-valuenow="{{ $smsPercentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
             <div class="card shadow-sm h-100 border-0">
                 <div class="card-header bg-white">
                     <h6 class="m-0 fw-bold text-primary"> <i class="fas fa-check-circle me-2"></i> المواعيد المؤكدة القادمة (أحدث ٥)</h6>
                 </div>
                 <div class="card-body upcoming-appointments-list p-2">
                     @if(isset($confirmedUpcomingBookings) && $confirmedUpcomingBookings->count() > 0)
                        @foreach($confirmedUpcomingBookings as $booking)
                            <div class="card booking-card mb-2">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-0 card-title-booking"><i class="fas fa-user text-muted me-1"></i> {{ $booking->user?->name ?? 'N/A' }}</h6>
                                            <small class="text-muted"><i class="fas fa-concierge-bell me-1"></i> {{ $booking->service?->name_ar ?? 'N/A' }}</small>
                                        </div>
                                        <span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }}">
                                            {{ $booking->status_label ?? getBookingStatusTranslation($booking->status ?? '', $bookingStatusTranslations) }}
                                        </span>
                                    </div>
                                    <p class="mb-1 mt-1 small"><i class="fas fa-calendar-alt text-muted me-1"></i> {{ \Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('d M Y, h:i A') }}</p>
                                    <div class="text-end mt-2">
                                        <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-outline-primary btn-sm py-1 px-2" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                     @else
                         <p class="text-center text-muted mt-3 py-4"> <i class="fas fa-calendar-times me-1"></i> لا توجد مواعيد مؤكدة قادمة.</p>
                     @endif
                 </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white">
                    <h6 class="m-0 fw-bold text-warning"><i class="fas fa-hourglass-half me-2"></i> مواعيد بانتظار التأكيد (أحدث ٥)</h6>
                </div>
                <div class="card-body upcoming-appointments-list p-2">
                    @if(isset($pendingUpcomingBookings) && $pendingUpcomingBookings->count() > 0)
                        @foreach($pendingUpcomingBookings as $booking)
                            <div class="card booking-card mb-2">
                                 <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-0 card-title-booking"><i class="fas fa-user text-muted me-1"></i> {{ $booking->user?->name ?? 'N/A' }}</h6>
                                            <small class="text-muted"><i class="fas fa-concierge-bell me-1"></i> {{ $booking->service?->name_ar ?? 'N/A' }}</small>
                                        </div>
                                        <span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }}">
                                            {{ $booking->status_label ?? getBookingStatusTranslation($booking->status ?? '', $bookingStatusTranslations) }}
                                        </span>
                                    </div>
                                    <p class="mb-1 mt-1 small"><i class="fas fa-calendar-alt text-muted me-1"></i> {{ \Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('d M Y, h:i A') }}</p>
                                     @if($booking->invoice)
                                        <p class="mb-1 small">
                                            الفاتورة: #{{ $booking->invoice->invoice_number }}
                                            (<span class="status-pill {{ $booking->invoice->status_badge_class ?? 'bg-secondary' }}">{{ $booking->invoice->status_label ?? getInvoiceStatusTranslation($booking->invoice->status ?? '', $invoiceStatusTranslations) }}</span>)
                                        </p>
                                     @endif
                                    <div class="text-end mt-2">
                                         @if($booking->invoice && $booking->invoice->status === \App\Models\Invoice::STATUS_PENDING_CONFIRMATION)
                                            <a href="{{ route('admin.invoices.show', $booking->invoice->id) }}" class="btn btn-outline-success btn-sm py-1 px-2 ms-1" title="مراجعة الفاتورة">
                                                 <i class="fas fa-check"></i> <span class="d-none d-md-inline">مراجعة</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.bookings.show', $booking->id) }}" class="btn btn-outline-primary btn-sm py-1 px-2" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-center text-muted mt-3 py-4"><i class="fas fa-calendar-check me-1"></i> لا توجد مواعيد بانتظار التأكيد حالياً.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <h6 class="m-0 fw-bold text-danger"><i class="fas fa-times-circle me-2"></i> فواتير فشلت أو غير مدفوعة (أحدث 5)</h6>
                </div>
                <div class="card-body pt-2">
                    @if(isset($failedOrUnpaidInvoices) && $failedOrUnpaidInvoices->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($failedOrUnpaidInvoices as $invoice)
                                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap px-2 py-2">
                                    <div class="mb-1 mb-md-0">
                                        <span class="fw-bold">فاتورة #{{ $invoice->invoice_number }}</span>
                                        <small class="text-muted ms-2">({{ $invoice->booking?->user?->name ?? 'عميل غير معروف' }})</small>
                                        <small class="text-muted d-block d-md-inline ms-md-2"> - {{ \Carbon\Carbon::parse($invoice->created_at)->translatedFormat('d M Y') }}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="me-2 fw-bold">{{ number_format($invoice->amount, 2) }} {{ $invoice->currency_symbol_short ?? $invoice->currency }}</span>
                                        <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }}">
                                            {{ $invoice->status_label ?? getInvoiceStatusTranslation($invoice->status ?? '', $invoiceStatusTranslations) }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-muted mt-3 py-4"><i class="fas fa-check-double me-1"></i> لا توجد فواتير فاشلة أو غير مدفوعة حالياً.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <h6 class="m-0 fw-bold text-info"><i class="fas fa-adjust me-2"></i> فواتير مدفوعة جزئياً (أحدث 5)</h6>
                </div>
                <div class="card-body pt-2">
                    @if(isset($partiallyPaidInvoices) && $partiallyPaidInvoices->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($partiallyPaidInvoices as $invoice)
                                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center flex-wrap px-2 py-2">
                                    <div class="mb-1 mb-md-0">
                                        <span class="fw-bold">فاتورة #{{ $invoice->invoice_number }}</span>
                                        <small class="text-muted ms-2">({{ $invoice->booking?->user?->name ?? 'عميل غير معروف' }})</small>
                                        <small class="text-muted d-block d-md-inline ms-md-2"> - {{ \Carbon\Carbon::parse($invoice->created_at)->translatedFormat('d M Y') }}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="me-2 fw-bold">{{ number_format($invoice->amount, 2) }} {{ $invoice->currency_symbol_short ?? $invoice->currency }}</span>
                                        <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }}">
                                            {{ $invoice->status_label ?? getInvoiceStatusTranslation($invoice->status ?? '', $invoiceStatusTranslations) }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-muted mt-3 py-4"><i class="fas fa-check-double me-1"></i> لا توجد فواتير مدفوعة جزئياً حالياً.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
    .stat-card .icon { font-size: 2.5rem; opacity: 0.3; }
    .next-appointment-notice { background-color: #e6f7ff; border-left: 5px solid #007bff; padding: 15px; border-radius: 5px; }
    .next-appointment-notice strong { font-size: 1.1rem; }
    .next-appointment-notice .details { font-size: 0.95rem; }
    .upcoming-appointments-list .booking-card { border: 1px solid #eee; transition: box-shadow 0.2s ease-in-out; }
    .upcoming-appointments-list .booking-card:hover { box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
    .card-title-booking { font-size: 1rem; color: #333; }
    .status-pill { padding: 0.25em 0.6em; font-size: 0.75em; font-weight: 600; border-radius: 0.25rem; color: white !important; }
</style>
@endpush
