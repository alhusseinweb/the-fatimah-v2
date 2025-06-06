{{-- المسار: resources/views/frontend/customer/invoices/show.blade.php --}}
@extends('layouts.app')

@php
    // إعادة تعريف الدوال المساعدة هنا
    if (!function_exists('toArabicDigitsGlobalSafeInvoiceShow')) {
        function toArabicDigitsGlobalSafeInvoiceShow($number) {
            if (is_null($number)) return '';
            return str_replace(range(0, 9), ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], (string)$number);
        }
    }
    if (!function_exists('formatAmountConditionallyGlobalSafeInvoiceShow')) {
        function formatAmountConditionallyGlobalSafeInvoiceShow($value, $currency = null) {
            if (is_null($value)) return '-';
            $value = (float) $value;
            $roundedToTwoDecimals = round($value, 2);
            $hasSignificantFraction = (abs($roundedToTwoDecimals - floor($roundedToTwoDecimals)) > 0.0001);
            $formattedNumber = number_format($roundedToTwoDecimals, $hasSignificantFraction ? 2 : 0, '.', '');
            
            $result = toArabicDigitsGlobalSafeInvoiceShow($formattedNumber);
            if ($currency) {
                $result = $result . ' ' . e($currency); 
            }
            return $result;
        }
    }

    $invoiceTitleNumberDisplay = $invoice->invoice_number ?: $invoice->id;
    $invoiceTitleNumberDisplay = toArabicDigitsGlobalSafeInvoiceShow((string)$invoiceTitleNumberDisplay);

    $remainingAmount = $invoice->remaining_amount ?? 0; 
    if (!isset($invoice->remaining_amount) && $invoice) { 
        $totalPaidForInvoice = $invoice->payments()->where('status', 'completed')->sum('amount');
        $remainingAmount = $invoice->amount - $totalPaidForInvoice;
    }
    $remainingAmount = (float) round($remainingAmount, 2);
@endphp

@section('title', "تفاصيل الفاتورة رقم " . $invoiceTitleNumberDisplay)

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; direction: rtl; text-align: right; }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div, th, td, a { font-family: 'Tajawal', sans-serif !important; }
    .invoice-card { background-color: #fff; border-radius: 12px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px; overflow: hidden; }
    .invoice-card-header { background-color: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .invoice-card-title { font-size: 18px; font-weight: 700; color: #333; margin: 0; display: flex; align-items: center; }
    .invoice-card-title i { margin-left: 10px; color: #555; }
    html[dir="ltr"] .invoice-card-title i { margin-left: 0; margin-right: 10px; }
    .invoice-card-body { padding: 25px; }
    .invoice-section { margin-bottom: 30px; }
    .invoice-section:last-of-type { margin-bottom: 0; }
    .invoice-section-title { font-size: 1.1rem; font-weight: 700; color: #444; margin-bottom: 18px; position: relative; display: inline-block; padding-bottom: 8px; }
    .invoice-section-title::after { content: ''; position: absolute; bottom: 0; right: 0; width: 50px; height: 3px; background-color: #555; }
    .info-row { display: flex; align-items: flex-start; margin-bottom: 12px; padding: 6px 0; border-bottom: 1px dashed #eee; }
    .info-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .info-label { font-weight: 600; color: #555; width: 160px; flex-shrink: 0; margin-left: 15px; }
    html[dir="ltr"] .info-label { margin-left: 0; margin-right: 15px; }
    .info-value { color: #333; flex-grow: 1; }
    .info-value.fw-bold { font-weight: 700 !important; }
    .info-value.text-success { color: #198754 !important; }
    .info-value.text-danger { color: #dc3545 !important; }
    .info-value.text-primary { color: #0d6efd !important; }
    .invoice-number-value { direction: ltr; text-align: right; display: inline-block; }
    html[dir="ltr"] .invoice-number-value { text-align: left;}
    .badge { font-size: 0.9em; padding: 0.45em 0.8em; font-weight: 500; vertical-align: middle; }
    .badge-unpaid { background-color: #ffc107 !important; color: #664d03 !important; }
    .badge-paid { background-color: #198754 !important; color: white !important; }
    .badge-partially-paid { background-color: #0dcaf0 !important; color: #055160 !important; }
    .badge-cancelled { background-color: #dc3545 !important; color: white !important; }
    .badge-failed { background-color: #dc3545 !important; color: white !important; }
    .badge-pending, .badge-pending-confirmation { background-color: #6c757d !important; color: white !important; }
    .badge-expired { background-color: #adb5bd !important; color: #495057 !important; }
    .badge-secondary { background-color: #6c757d !important; color: white !important; }
    .invoice-divider { margin: 30px 0; border-top: 1px solid #ddd; }
    .invoice-actions { display: flex; gap: 10px; margin-top: 25px; flex-wrap: wrap; justify-content: center; }
    .btn-action { padding: 8px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
    .btn-action i { margin-left: 6px; margin-right: 0; font-size: 0.9em; }
    html[dir="ltr"] .btn-action i { margin-left: 0; margin-right: 6px; }
    .btn-back { background-color: #6c757d; color: white; border: 1px solid #6c757d; }
    .btn-back:hover { background-color: #5c636a; color: white; }
    .btn-print { background-color: #555; color: white; }
    .btn-print:hover { background-color: #444; color: white; }
    .btn-pay { background-color: #28a745; color: white; }
    .btn-pay:hover { background-color: #218838; color: white;}
    .btn-tamara-pay { background-color: #4A4A4A; border-color: #4A4A4A; color: white; }
    .btn-tamara-pay:hover { background-color: #3A3A3A; border-color: #3A3A3A; color: white; }
    .btn-tamara-pay:disabled { background-color: #a0a0a0; border-color: #a0a0a0; cursor: not-allowed; }
    .btn-tamara-pay img { height: 20px; margin-left: 8px; }
    html[dir="ltr"] .btn-tamara-pay img { margin-left: 0; margin-right: 8px; }
    .alert { border-radius: 8px; } 
    .add-on-services-details-list { list-style: none; padding-right: 1.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem; }
    .add-on-services-details-list li { display: flex; justify-content: space-between; font-size: 0.9rem; color: #444; padding: 3px 0; }
    .add-on-services-details-list .add-on-name { color: #555; }
    .add-on-services-details-list .add-on-price { font-weight: 500; color: #333; }
    @media (max-width: 767px) { .invoice-card-header { flex-direction: column; align-items: flex-start; } .invoice-card-title { margin-bottom: 10px; } .info-label { min-width: 100px; font-size: 0.9em; } .info-value { font-size: 0.9em; } .btn-action { padding: 6px 12px; font-size: 13px; } .invoice-actions { justify-content: center; } }
    @media (max-width: 576px) { .invoice-card-body { padding: 20px; } .info-row { flex-direction: column; align-items: flex-start; } .info-label { width: auto; margin-bottom: 4px; margin-left: 0;} html[dir="ltr"] .info-label { margin-right: 0; } .info-value { display: block; width: 100%; } .invoice-actions { flex-direction: column; align-items: stretch; } .invoice-actions .btn-action { width: 100%; margin-bottom: 10px; } .invoice-actions .btn-action:last-child { margin-bottom: 0; } }
</style>
@endsection

@section('content')
<div class="container my-4">
    <div class="invoice-card">
        <div class="invoice-card-header">
            <h1 class="invoice-card-title">
                تفاصيل الفاتورة رقم {{ $invoiceTitleNumberDisplay }}
            </h1>
            <a href="{{ route('customer.dashboard') }}" class="btn-action btn-back">
                العودة إلى لوحة التحكم
            </a>
        </div>
        <div class="invoice-card-body">

            @php
                $alertClass = '';
                $alertText = '';
                $allowTamaraPayment = false;
                if (!isset($settings)) {
                    $settings = App\Models\Setting::pluck('value', 'key')->all();
                }
                $isTamaraGenerallyEnabled = filter_var($settings['tamara_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                switch ($invoice->status) {
                    case \App\Models\Invoice::STATUS_PAID:
                        $alertClass = 'alert-success';
                        $alertText = 'الفاتورة مدفوعة بالكامل.';
                        break;
                    case \App\Models\Invoice::STATUS_PARTIALLY_PAID:
                        $alertClass = 'alert-info';
                        if ($remainingAmount > 0.009) {
                            $alertText = 'تم دفع جزء. المبلغ المتبقي: ' . formatAmountConditionallyGlobalSafeInvoiceShow($remainingAmount, $invoice->currency);
                            if ($isTamaraGenerallyEnabled && $invoice->payment_method === 'tamara') {
                                $allowTamaraPayment = true;
                            }
                        } else {
                            $alertClass = 'alert-success';
                            $alertText = 'الفاتورة مدفوعة بالكامل.';
                        }
                        break;
                    case \App\Models\Invoice::STATUS_UNPAID:
                    case \App\Models\Invoice::STATUS_PENDING:
                    case \App\Models\Invoice::STATUS_PENDING_CONFIRMATION:
                    case \App\Models\Invoice::STATUS_FAILED:
                        $alertClass = ($invoice->status === \App\Models\Invoice::STATUS_FAILED) ? 'alert-danger' : 'alert-warning';
                        if($invoice->status === \App\Models\Invoice::STATUS_PENDING_CONFIRMATION){
                            $alertText = 'الفاتورة بانتظار تأكيد الدفع اليدوي من الإدارة.';
                        } else {
                            $alertText = ($invoice->status === \App\Models\Invoice::STATUS_FAILED) ? 'فشلت عملية الدفع الأخيرة.' : 'الفاتورة بانتظار الدفع.';
                        }
                        if ($remainingAmount > 0.009 && $isTamaraGenerallyEnabled && $invoice->payment_method === 'tamara') {
                            $allowTamaraPayment = true;
                        }
                        break;
                    case \App\Models\Invoice::STATUS_CANCELLED:
                    case \App\Models\Invoice::STATUS_EXPIRED:
                        $alertClass = 'alert-secondary';
                        $alertText = ($invoice->status === \App\Models\Invoice::STATUS_CANCELLED) ? 'الفاتورة ملغاة.' : 'الفاتورة منتهية الصلاحية.';
                        break;
                    default:
                        $alertClass = 'alert-info';
                        $alertText = 'الحالة: ' . ($invoice->status_label ?? Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $invoice->status)));
                        break;
                }
            @endphp

            @if($alertText)
            <div class="alert {{ $alertClass }} d-flex justify-content-between align-items-center mb-4 flex-wrap" role="alert">
                <span class="flex-grow-1 mb-2 mb-md-0">{{ $alertText }}</span>
                
                {{-- START: MODIFIED TAMARA PAYMENT BUTTON LOGIC --}}
                @if($allowTamaraPayment && $remainingAmount > 0.009)
                    <div class="d-flex flex-column align-items-end">
                        <form method="POST" action="{{ route('payment_retry_tamara', $invoice->id) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-tamara-pay" {{ $remainingAmount > 3000 ? 'disabled' : '' }}>
                                <img src="{{ asset('images/tamara.png') }}" alt="Tamara">
                                {{ $invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID ? 'ادفع المتبقي عبر تمارا' : 'ادفع الآن عبر تمارا' }}
                            </button>
                        </form>

                        @if ($remainingAmount > 3000)
                            <div class="text-danger small mt-2" style="font-weight: 500;">
                                <strong>ملاحظة:</strong> المبلغ يتجاوز حد الدفع. يرجى التواصل عبر الواتساب.
                            </div>
                        @endif
                    </div>
                @endif
                {{-- END: MODIFIED TAMARA PAYMENT BUTTON LOGIC --}}
            </div>
            @endif


            <div class="invoice-section">
                <h2 class="invoice-section-title">معلومات الفاتورة</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row"> <span class="info-label">رقم الفاتورة:</span> <span class="info-value invoice-number-value">{{ $invoice->invoice_number ?? $invoice->id }}</span> </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value">
                                <span class="badge {{ $invoice->status_badge_class ?? 'badge-secondary' }}">
                                    {{ $invoice->status_label ?? Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $invoice->status)) }}
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row"> <span class="info-label">المبلغ الإجمالي:</span> <span class="info-value fw-bold">{{ formatAmountConditionallyGlobalSafeInvoiceShow($invoice->amount, $invoice->currency) }}</span> </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row"> <span class="info-label">المبلغ المدفوع:</span> <span class="info-value text-success fw-bold">{{ formatAmountConditionallyGlobalSafeInvoiceShow($invoice->total_paid_amount, $invoice->currency) }}</span> </div>
                    </div>
                    @if ($remainingAmount > 0.009 && $invoice->status != \App\Models\Invoice::STATUS_PAID)
                        <div class="col-md-6">
                            <div class="info-row"> <span class="info-label">المبلغ المتبقي:</span> <span class="info-value text-danger fw-bold">{{ formatAmountConditionallyGlobalSafeInvoiceShow($remainingAmount, $invoice->currency) }}</span> </div>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">خيار الدفع:</span>
                            <span class="info-value">
                                @if($invoice->payment_option === 'down_payment') دفع عربون
                                @elseif($invoice->payment_option === 'full') دفع كامل
                                @else {{ $invoice->payment_option ?? '-' }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">طريقة الدفع المسجلة:</span>
                            <span class="info-value">
                                @if ($invoice->payment_method == 'tamara') تمارا
                                @elseif ($invoice->payment_method == 'bank_transfer') تحويل بنكي
                                @elseif ($invoice->payment_method == 'manual_by_admin' || $invoice->payment_method == 'manual_admin_deposit' || $invoice->payment_method == 'manual_admin_full') تسجيل دفعة يدوية
                                @elseif ($invoice->payment_method == 'manual_confirmation_due_to_no_gateway') بانتظار التأكيد اليدوي
                                @else {{ $invoice->payment_method ?? 'غير محدد' }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row"> <span class="info-label">تاريخ الإنشاء:</span> <span class="info-value">{{ $invoice->created_at ? toArabicDigitsGlobalSafeInvoiceShow($invoice->created_at->translatedFormat('d F Y - H:i')) : '-' }}</span> </div>
                    </div>
                    @if ($invoice->paid_at)
                        <div class="col-md-6">
                            <div class="info-row"> <span class="info-label">تاريخ أول دفعة:</span> <span class="info-value">{{ $invoice->paid_at ? toArabicDigitsGlobalSafeInvoiceShow(\Carbon\Carbon::parse($invoice->paid_at)->translatedFormat('d F Y - H:i')) : '-' }}</span> </div>
                        </div>
                    @endif
                    @if ($invoice->payment_gateway_ref)
                        <div class="col-md-12">
                            <div class="info-row">
                                <span class="info-label">مرجع الدفع:</span>
                                <span class="info-value">
                                    {{ $invoice->payment_method == 'tamara' ? 'رقم طلب تمارا: ' : 'رقم المرجع: ' }}
                                    {{ $invoice->payment_gateway_ref }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="invoice-divider"></div>

            @if ($booking = $invoice->booking)
                @php $booking->loadMissing(['service', 'addOnServices']); @endphp
                <div class="invoice-section">
                    <h2 class="invoice-section-title">معلومات الحجز المرتبط</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">رقم الحجز:</span>
                                <span class="info-value">
                                    <a href="{{ route('customer.bookings.index') }}">#{{ toArabicDigitsGlobalSafeInvoiceShow($booking->id) }}</a>
                                </span>
                            </div>
                        </div>
                        @if ($booking->service)
                            <div class="col-md-6">
                                <div class="info-row"> <span class="info-label">الخدمة الأساسية:</span> <span class="info-value">{{ $booking->service->name_ar ?? $booking->service->name_en }}</span> </div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <div class="info-row"> <span class="info-label">تاريخ ووقت الحجز:</span> <span class="info-value">{{ $booking->booking_datetime ? toArabicDigitsGlobalSafeInvoiceShow(\Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l، d F Y - h:i a')) : 'غير محدد' }}</span> </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-row"> <span class="info-label">منطقة التصوير:</span> <span class="info-value">{{ $booking->shooting_area_label }}</span> </div>
                        </div>
                        @if($booking->shooting_area === 'outside_ahsa' && $booking->outside_location_city)
                            <div class="col-md-6">
                                <div class="info-row"> <span class="info-label">المدينة (خارج الأحساء):</span> <span class="info-value">{{ $booking->outside_location_city }}</span> </div>
                            </div>
                        @endif
                        @if($booking->outside_location_fee_applied > 0)
                            <div class="col-md-6">
                                <div class="info-row"> <span class="info-label">رسوم خارج المنطقة:</span> <span class="info-value">{{ formatAmountConditionallyGlobalSafeInvoiceShow($booking->outside_location_fee_applied, ($invoice->currency ?? 'SAR')) }}</span> </div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <div class="info-row"> <span class="info-label">مكان الفعالية (العنوان):</span> <span class="info-value">{{ $booking->event_location ?? '-' }}</span> </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">حالة الحجز:</span>
                                <span class="info-value">
                                    <span class="badge {{ $booking->status_badge_class ?? 'bg-secondary' }}">
                                        {{ $booking->status_label ?? Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $booking->status)) }}
                                    </span>
                                </span>
                            </div>
                        </div>
                        @if($booking->addOnServices && $booking->addOnServices->isNotEmpty())
                        <div class="col-12 mt-2">
                            <div class="info-row">
                                <span class="info-label">الخدمات الإضافية:</span>
                                <span class="info-value">
                                    <ul class="add-on-services-details-list">
                                        @foreach($booking->addOnServices as $addOn)
                                        <li>
                                            <span class="add-on-name">{{ $addOn->getLocalizedNameAttribute() }}</span>
                                            <span class="add-on-price">
                                                {{ formatAmountConditionallyGlobalSafeInvoiceShow($addOn->pivot->price_at_booking, $invoice->currency) }}
                                            </span>
                                        </li>
                                        @endforeach
                                    </ul>
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="invoice-actions">
                <button onclick="window.print()" class="btn-action btn-print">
                    طباعة الفاتورة
                </button>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- No specific JavaScript needed here --}}
@endsection
