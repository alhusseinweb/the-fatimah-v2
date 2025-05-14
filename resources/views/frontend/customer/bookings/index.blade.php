{{-- المسار: resources/views/frontend/customer/bookings/index.blade.php --}}
@extends('layouts.app')

@section('title', 'حجوزاتي')

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
{{-- تضمين Font Awesome إذا لم يكن مضمنًا بالفعل في layouts.app --}}
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* تعريف الخط الأساسي */
    body {
        font-family: 'Tajawal', sans-serif !important;
        background-color: #f8f9fa;
        direction: rtl;
        text-align: right;
    }

    /* جعل جميع العناصر تستخدم خط Tajawal */
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div, th, td, a {
        font-family: 'Tajawal', sans-serif !important;
    }

    /* العنوان الرئيسي للصفحة */
    .page-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 20px;
        position: relative;
        display: inline-block;
        padding-bottom: 10px;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 50px;
        height: 3px;
        background-color: #555;
    }

    .page-title i {
        margin-left: 10px;
        color: #555;
    }

    /* تصميم البطاقة الرئيسية */
    .bookings-card {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .bookings-card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #dee2e6;
    }

    .bookings-card-title {
        font-size: 18px;
        font-weight: 700;
        color: #333;
        margin: 0;
        display: flex;
        align-items: center;
    }

    .bookings-card-title i {
        margin-left: 10px;
        color: #555;
    }

    .bookings-card-body {
        padding: 0;
    }

    /* تنسيق الجدول */
    .table-container {
        overflow-x: auto;
        border-radius: 0 0 12px 12px;
    }

    .bookings-table {
        width: 100%;
        margin-bottom: 0;
    }

    .bookings-table th {
        font-weight: 600;
        background-color: #f8f9fa;
        border-top: none;
        padding: 12px 15px;
        white-space: nowrap;
        font-size: 0.9em;
    }

    .bookings-table td {
        vertical-align: middle;
        padding: 12px 15px;
        font-size: 0.95em;
    }

    .bookings-table tr:last-child td {
        border-bottom: none;
    }

    /* تنسيق الشارات (Badges) */
    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.7em;
        font-weight: 600;
    }

    /* أزرار الإجراءات */
    .btn-action {
        padding: 5px 12px;
        border-radius: 5px;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        margin-left: 5px;
        margin-right: 0;
    }

    .btn-action i {
        margin-left: 5px;
        margin-right: 0;
    }

    .btn-primary-action {
        background-color: #555;
        color: white;
        border: none;
    }

    .btn-primary-action:hover {
        background-color: #444;
        color: white;
    }

    .btn-secondary-action {
        background-color: transparent;
        color: #555;
        border: 1px solid #555;
    }

    .btn-secondary-action:hover {
        background-color: #f0f0f0;
        color: #333;
    }

    .btn-info-action {
        background-color: transparent;
        color: #17a2b8;
        border: 1px solid #17a2b8;
    }

    .btn-info-action:hover {
        background-color: #17a2b8;
        color: white;
    }

    /* رسالة فارغة */
    .empty-message {
        padding: 25px;
        text-align: center;
        color: #777;
    }

    .empty-icon {
        font-size: 35px;
        color: #ddd;
        margin-bottom: 10px;
    }

    /* طريقة عرض البطاقة للجوال */
    .mobile-bookings-view {
        display: none;
        padding: 10px;
    }

    .mobile-booking-item {
        background-color: #f9f9f9;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border-right: 3px solid #555;
    }

    .mobile-booking-item:last-child {
        margin-bottom: 0;
    }

    .mobile-item-row {
        display: flex;
        margin-bottom: 10px;
    }

    .mobile-item-row:last-child {
        margin-bottom: 0;
    }

    .mobile-item-label {
        font-weight: 600;
        color: #555;
        width: 110px;
        flex-shrink: 0;
    }

    .mobile-item-value {
        flex: 1;
    }

    .mobile-item-actions {
        margin-top: 15px;
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mobile-item-actions .btn-action {
        margin: 0;
        min-width: 110px;
        padding: 8px 15px;
    }

    /* تنسيقات التوافق مع الجوال */
    @media (max-width: 767px) {
        .page-title {
            font-size: 22px;
            margin-bottom: 15px;
        }

        .bookings-card {
            margin-bottom: 15px;
        }

        .bookings-card-header {
            padding: 12px 15px;
        }

        .bookings-card-title {
            font-size: 16px;
        }

        .bookings-table th,
        .bookings-table td {
            font-size: 0.9em;
            padding: 10px 8px;
        }

        .btn-action {
            padding: 5px 12px;
            font-size: 12px;
        }

        .btn-action span {
            display: none;
        }

        .btn-action i {
            margin: 0;
        }
        
        .mobile-booking-item {
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .mobile-item-label {
            width: 90px;
            font-size: 0.9em;
        }
        
        .mobile-item-value {
            font-size: 0.9em;
        }
    }

    /* طريقة عرض الجوال للشاشات الصغيرة جداً */
    @media (max-width: 575px) {
        .table-container {
            display: none;
        }
        
        .mobile-bookings-view {
            display: block;
        }

        .pagination {
            justify-content: center;
        }
        
        .mobile-item-date {
            display: block;
        }
        
        .mobile-item-date-day {
            display: block;
            font-weight: bold;
        }
        
        .mobile-item-date-time {
            display: block;
            font-size: 0.9em;
            color: #666;
        }
        
        .mobile-item-actions .btn-action {
            flex: 1;
            text-align: center;
        }
        
        .mobile-item-actions .btn-action span {
            display: inline-block;
        }
        
        .mobile-item-row {
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .mobile-item-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
    }
</style>
@endsection

@section('content')
<div class="container my-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="page-title">
                حجوزاتي
            </h1>

            <div class="bookings-card">
                <div class="bookings-card-header">
                    <h3 class="bookings-card-title">
                        قائمة الحجوزات
                    </h3>
                </div>

                <div class="bookings-card-body">
                    @if($bookings->isEmpty())
                        <div class="empty-message">
                            <p>لا توجد حجوزات لعرضها حالياً.</p>
                        </div>
                    @else
                        {{-- عرض الجدول للشاشات الكبيرة --}}
                        <div class="table-container">
                            <table class="table bookings-table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col"># الحجز</th>
                                        <th scope="col">الخدمة</th>
                                        <th scope="col">التاريخ والوقت</th>
                                        <th scope="col">المبلغ</th>
                                        <th scope="col">حالة الحجز</th>
                                        <th scope="col">حالة الدفع</th>
                                        <th scope="col" class="text-center">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookings as $booking)
                                    <tr>
                                        <td>{{ $booking->id }}</td>
                                        <td>{{ $booking->service?->name_ar ?? $booking->service?->name ?? 'غير متوفر' }}</td>
                                        <td>{{ $booking->booking_datetime ? \Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l، d F Y - H:i') : 'غير متوفر' }}</td>
                                        <td>{{ $booking->invoice ? number_format($booking->invoice->amount, 2) . ' ر.س' : 'غير متوفر' }}</td>
                                        <td>
                                            @php
                                                $bookingStatusClass = 'bg-secondary'; // الافتراضي
                                                // ترجمة نص الحالة إلى العربية
                                                $statusText = 'غير معروف';
                                                if ($booking->status == 'confirmed') {
                                                    $bookingStatusClass = 'bg-success';
                                                    $statusText = 'مؤكد';
                                                } elseif ($booking->status == 'pending') {
                                                    $bookingStatusClass = 'bg-warning text-dark';
                                                    $statusText = 'قيد الانتظار';
                                                } elseif ($booking->status == 'pending_confirmation') {
                                                    $bookingStatusClass = 'bg-warning text-dark';
                                                    $statusText = 'بانتظار التأكيد';
                                                } elseif ($booking->status == 'cancelled') {
                                                    $bookingStatusClass = 'bg-danger';
                                                    $statusText = 'ملغي';
                                                } elseif ($booking->status == 'completed') {
                                                    $bookingStatusClass = 'bg-primary';
                                                    $statusText = 'مكتمل';
                                                }
                                            @endphp
                                            <span class="badge {{ $bookingStatusClass }}">{{ $statusText }}</span>
                                        </td>
                                        <td>
                                            @if ($booking->invoice)
                                                @php
                                                    $invoiceStatusClass = 'bg-secondary'; // الافتراضي
                                                    // ترجمة نص الحالة إلى العربية
                                                    $statusText = 'غير معروف';
                                                    if ($booking->invoice->status == 'paid') {
                                                        $invoiceStatusClass = 'bg-success';
                                                        $statusText = 'مدفوعة';
                                                    } elseif ($booking->invoice->status == 'unpaid') {
                                                        $invoiceStatusClass = 'bg-warning text-dark';
                                                        $statusText = 'غير مدفوعة';
                                                    } elseif ($booking->invoice->status == 'failed') {
                                                        $invoiceStatusClass = 'bg-danger';
                                                        $statusText = 'فشل الدفع';
                                                    } elseif ($booking->invoice->status == 'pending') {
                                                        $invoiceStatusClass = 'bg-info text-white';
                                                        $statusText = 'قيد الانتظار';
                                                    } elseif ($booking->invoice->status == 'cancelled') {
                                                        $invoiceStatusClass = 'bg-secondary';
                                                        $statusText = 'ملغاة';
                                                    } elseif ($booking->invoice->status == 'expired') {
                                                        $invoiceStatusClass = 'bg-dark';
                                                        $statusText = 'منتهية الصلاحية';
                                                    }
                                                @endphp
                                                <span class="badge {{ $invoiceStatusClass }}">{{ $statusText }}</span>
                                            @else
                                                <span class="text-muted small">غير متوفر</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($booking->invoice)
                                                <a href="{{ route('customer.invoices.show', $booking->invoice->id) }}"
                                                   class="btn-action btn-info-action" 
                                                   title="عرض الفاتورة">
                                                  <span>الفاتورة</span>
                                                </a>
                                            @endif

                                            @if($booking->invoice &&
                                                 $booking->invoice->payment_method == 'tamara' &&
                                                 ($booking->invoice->status == 'unpaid' || $booking->invoice->status == 'failed') &&
                                                 $booking->status != 'cancelled'
                                               )
                                                 <form action="{{ route('payment_retry_tamara', $booking->invoice->id) }}" method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>'; return confirm('سيتم تحويلك إلى تمارا لإعادة محاولة الدفع. هل أنت متأكد؟');">
                                                     @csrf
                                                     <button type="submit" class="btn-action btn-primary-action" title="إعادة محاولة الدفع عبر تمارا">
                                                         <span>إعادة الدفع</span>
                                                     </button>
                                                 </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- عرض البطاقات للشاشات الصغيرة --}}
                        <div class="mobile-bookings-view">
                            @foreach($bookings as $booking)
                                <div class="mobile-booking-item">
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label"># الحجز:</div>
                                        <div class="mobile-item-value">{{ $booking->id }}</div>
                                    </div>
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label">الخدمة:</div>
                                        <div class="mobile-item-value">{{ $booking->service?->name_ar ?? $booking->service?->name ?? 'غير متوفر' }}</div>
                                    </div>
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label">التاريخ والوقت:</div>
                                        <div class="mobile-item-value">
                                            @if($booking->booking_datetime)
                                                <div class="mobile-item-date">
                                                    <span class="mobile-item-date-day">{{ \Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l، d F Y') }}</span>
                                                    <span class="mobile-item-date-time">{{ \Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('h:i a') }}</span>
                                                </div>
                                            @else
                                                غير متوفر
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label">المبلغ:</div>
                                        <div class="mobile-item-value">{{ $booking->invoice ? number_format($booking->invoice->amount, 2) . ' ر.س' : 'غير متوفر' }}</div>
                                    </div>
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label">حالة الحجز:</div>
                                        <div class="mobile-item-value">
                                            @php
                                                $bookingStatusClass = 'bg-secondary'; // الافتراضي
                                                // ترجمة نص الحالة إلى العربية
                                                $statusText = 'غير معروف';
                                                if ($booking->status == 'confirmed') {
                                                    $bookingStatusClass = 'bg-success';
                                                    $statusText = 'مؤكد';
                                                } elseif ($booking->status == 'pending') {
                                                    $bookingStatusClass = 'bg-warning text-dark';
                                                    $statusText = 'قيد الانتظار';
                                                } elseif ($booking->status == 'pending_confirmation') {
                                                    $bookingStatusClass = 'bg-warning text-dark';
                                                    $statusText = 'بانتظار التأكيد';
                                                } elseif ($booking->status == 'cancelled') {
                                                    $bookingStatusClass = 'bg-danger';
                                                    $statusText = 'ملغي';
                                                } elseif ($booking->status == 'completed') {
                                                    $bookingStatusClass = 'bg-primary';
                                                    $statusText = 'مكتمل';
                                                }
                                            @endphp
                                            <span class="badge {{ $bookingStatusClass }}">{{ $statusText }}</span>
                                        </div>
                                    </div>
                                    <div class="mobile-item-row">
                                        <div class="mobile-item-label">حالة الدفع:</div>
                                        <div class="mobile-item-value">
                                            @if ($booking->invoice)
                                                @php
                                                    $invoiceStatusClass = 'bg-secondary'; // الافتراضي
                                                    // ترجمة نص الحالة إلى العربية
                                                    $statusText = 'غير معروف';
                                                    if ($booking->invoice->status == 'paid') {
                                                        $invoiceStatusClass = 'bg-success';
                                                        $statusText = 'مدفوعة';
                                                    } elseif ($booking->invoice->status == 'unpaid') {
                                                        $invoiceStatusClass = 'bg-warning text-dark';
                                                        $statusText = 'غير مدفوعة';
                                                    } elseif ($booking->invoice->status == 'failed') {
                                                        $invoiceStatusClass = 'bg-danger';
                                                        $statusText = 'فشل الدفع';
                                                    } elseif ($booking->invoice->status == 'pending') {
                                                        $invoiceStatusClass = 'bg-info text-white';
                                                        $statusText = 'قيد الانتظار';
                                                    } elseif ($booking->invoice->status == 'cancelled') {
                                                        $invoiceStatusClass = 'bg-secondary';
                                                        $statusText = 'ملغاة';
                                                    } elseif ($booking->invoice->status == 'expired') {
                                                        $invoiceStatusClass = 'bg-dark';
                                                        $statusText = 'منتهية الصلاحية';
                                                    }
                                                @endphp
                                                <span class="badge {{ $invoiceStatusClass }}">{{ $statusText }}</span>
                                            @else
                                                <span class="text-muted small">غير متوفر</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mobile-item-actions">
                                        @if ($booking->invoice)
                                            <a href="{{ route('customer.invoices.show', $booking->invoice->id) }}"
                                               class="btn-action btn-info-action" 
                                               title="عرض الفاتورة">
                                               <span>الفاتورة</span>
                                            </a>
                                        @endif

                                        @if($booking->invoice &&
                                             $booking->invoice->payment_method == 'tamara' &&
                                             ($booking->invoice->status == 'unpaid' || $booking->invoice->status == 'failed') &&
                                             $booking->status != 'cancelled'
                                           )
                                             <form action="{{ route('payment_retry_tamara', $booking->invoice->id) }}" method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm\' role=\'status\' aria-hidden=\'true\'></span>'; return confirm('سيتم تحويلك إلى تمارا لإعادة محاولة الدفع. هل أنت متأكد؟');">
                                                 @csrf
                                                 <button type="submit" class="btn-action btn-primary-action" title="إعادة محاولة الدفع عبر تمارا">
                                                     <span>إعادة الدفع</span>
                                                 </button>
                                             </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- روابط الترقيم (Pagination) --}}
                        <div class="mt-3 p-3">
                            {{ $bookings->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // سكريبت للتحقق من حجم الشاشة والتبديل بين طريقة العرض
    document.addEventListener('DOMContentLoaded', function() {
        function checkScreenSize() {
            // تحقق إذا كان عرض الشاشة أقل من 576 بكسل (sm في Bootstrap)
            if (window.innerWidth < 576) {
                // إخفاء الجدول وإظهار طريقة عرض البطاقات
                document.querySelectorAll('.table-container').forEach(function(table) {
                    table.style.display = 'none';
                });
                document.querySelectorAll('.mobile-bookings-view').forEach(function(card) {
                    card.style.display = 'block';
                });
            } else {
                // إظهار الجدول وإخفاء طريقة عرض البطاقات
                document.querySelectorAll('.table-container').forEach(function(table) {
                    table.style.display = 'block';
                });
                document.querySelectorAll('.mobile-bookings-view').forEach(function(card) {
                    card.style.display = 'none';
                });
            }
        }
        
        // تنفيذ الفحص عند تحميل الصفحة
        checkScreenSize();
        
        // تنفيذ الفحص عند تغيير حجم النافذة
        window.addEventListener('resize', checkScreenSize);
    });
</script>
@endpush