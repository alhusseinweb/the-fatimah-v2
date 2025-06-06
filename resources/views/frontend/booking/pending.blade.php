{{-- المسار: resources/views/frontend/booking/pending.blade.php --}}

@extends('layouts.app')

@php
    // دالة toArabicDigits يفترض أنها معرفة كـ helper عام أو في AppServiceProvider
    if (!function_exists('toArabicDigits')) {
        function toArabicDigits($number) {
            return str_replace(range(0, 9), ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], $number);
        }
    }
    if (!function_exists('formatAmountConditionallyPending')) { // اسم فريد للدالة
        function formatAmountConditionallyPending($value) {
            $value = (float) $value;
            $roundedToTwoDecimals = floor($value * 100) / 100;
            $hasSignificantFraction = (($roundedToTwoDecimals - floor($roundedToTwoDecimals)) > 0.001);
            $formattedNumber = number_format($roundedToTwoDecimals, $hasSignificantFraction ? 2 : 0, '.', '');
            return toArabicDigits($formattedNumber);
        }
    }
@endphp

@section('title', 'حالة طلب الحجز #' . toArabicDigits($booking->id))

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; direction: rtl; text-align: right; }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div, dl, dt, dd { font-family: 'Tajawal', sans-serif !important; }
    .booking-confirmation-wrapper { padding: 40px 0; min-height: calc(100vh - 150px); }
    .booking-card { background-color: #fff; border-radius: 15px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); overflow: hidden; border: none; margin-bottom: 20px; }
    .booking-card-header { background-color: #555; color: white; padding: 20px; border-bottom: none; }
    .booking-card-header h4 { margin: 0; font-weight: 700; font-size: 1.25rem; }
    .booking-card-body { padding: 25px; }
    .section-title { font-weight: 700; font-size: 1.1rem; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; }
    .section-title i { margin-left: 8px; color: #555; width: 1.2em; text-align: center;}
    html[dir="ltr"] .section-title i { margin-left: 0; margin-right: 8px; }
    .booking-section { background-color: #fff; border-radius: 12px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05); padding: 20px; margin-bottom: 20px; border: 1px solid #f0f0f0; }
    .booking-section dl dt { font-weight: 600; color: #555; margin-bottom: 5px; }
    .booking-section dl dd { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #f0f0f0; margin-right: 0; }
    .booking-section dl dd:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

    .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: 500; font-size: 0.85rem; display: inline-block; }
    .badge-unpaid { background-color: #ffc107 !important; color: #664d03 !important; }
    .badge-paid { background-color: #198754 !important; color: white !important; }
    .badge-partially-paid { background-color: #0dcaf0 !important; color: #055160 !important; }
    .badge-cancelled { background-color: #dc3545 !important; color: white !important; }
    .badge-failed { background-color: #dc3545 !important; color: white !important; }
    .badge-pending { background-color: #6c757d !important; color: white !important; } /* لحالة الحجز Pending */
    .badge-pending-confirmation { background-color: #6610f2 !important; color: white !important; } /* لحالة الفاتورة Pending Confirmation */
    .badge-expired { background-color: #adb5bd !important; color: #495057 !important; }
    .badge-secondary { background-color: #6c757d !important; color: white !important; }
    .badge-discount { background-color: #e0a800; color: #fff; font-size: 0.8rem; }

    .custom-alert { border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; align-items: flex-start; position: relative; }
    .alert-icon { width: 24px; height: 24px; margin-left: 12px; flex-shrink: 0; }
    html[dir="ltr"] .alert-icon { margin-left: 0; margin-right: 12px; }
    .alert-success { background-color: #d1e7dd; color: #0f5132; border-right: 4px solid #198754; }
    html[dir="ltr"] .alert-success { border-right: none; border-left: 4px solid #198754;}
    .alert-info { background-color: #cff4fc; color: #055160; border-right: 4px solid #0dcaf0; }
    html[dir="ltr"] .alert-info { border-right: none; border-left: 4px solid #0dcaf0;}
    .alert-warning { background-color: #fff3cd; color: #664d03; border-right: 4px solid #ffc107; }
    html[dir="ltr"] .alert-warning { border-right: none; border-left: 4px solid #ffc107;}
    .alert-danger { background-color: #f8d7da; color: #842029; border-right: 4px solid #dc3545; }
    html[dir="ltr"] .alert-danger { border-right: none; border-left: 4px solid #dc3545;}
    .alert-close { position: absolute; top: 15px; left: 15px; background: none; border: none; color: inherit; opacity: 0.7; cursor: pointer; font-size: 1.2rem; line-height: 1;}
    html[dir="ltr"] .alert-close { left: auto; right: 15px; }
    .alert-close:hover { opacity: 1; }

    .bank-accounts-list { list-style: none; padding: 0; margin: 15px 0; }
    .bank-account-item { background-color: #f9f9f9; border: 1px solid #eee; border-radius: 10px; padding: 15px; margin-bottom: 10px; }
    .bank-account-item:last-child { margin-bottom: 0; }
    .bank-name { font-weight: 700; margin-bottom: 8px; color: #333; }
    .bank-detail { display: flex; margin-bottom: 5px; align-items: center; font-size: 0.95rem; }
    .bank-detail-label { width: 100px; color: #555; font-weight: 500; }
    .bank-detail-value { font-weight: 400; word-break: break-all; }

    .actions-container { display: flex; gap: 10px; justify-content: center; margin-top: 25px; flex-wrap: wrap;}
    .btn-custom { padding: 10px 20px; border-radius: 30px; font-weight: 600; transition: all 0.3s ease; border: none; text-decoration: none; display: inline-block; line-height: 1.5; }
    .btn-primary-custom { background-color: #555; color: white; }
    .btn-primary-custom:hover { background-color: #333; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); color: white; }
    .btn-secondary-custom { background-color: transparent; border: 2px solid #555; color: #555; }
    .btn-secondary-custom:hover { background-color: #f0f0f0; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); color: #555; }
    .btn-pay { background-color: #28a745; color: white; }
    .btn-pay:hover { background-color: #218838; color: white;}

    .add-on-services-summary-list { list-style: none; padding: 0; margin-top: 5px; }
    .add-on-services-summary-list li { display: flex; justify-content: space-between; font-size: 0.95rem; color: #333; padding: 5px 0; }
    .add-on-services-summary-list li:not(:last-child) { border-bottom: 1px dashed #f0f0f0; margin-bottom: 5px; padding-bottom: 5px;}
    .add-on-services-summary-list .add-on-name { color: #555; }
    .add-on-services-summary-list .add-on-price { font-weight: 600; color: #495057; }

    @media (max-width: 576px) { 
        .booking-card-body { padding: 20px; }
        .booking-section { padding: 15px; }
        .booking-section dl dt { width: 100%; }
        .booking-section dl dd { width: 100%; margin-right: 0; padding-right: 0; }
        .bank-detail-label { width: 90px; }
    }
</style>
@endsection

@section('content')
<div class="booking-confirmation-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h4> حالة طلب الحجز #{{ toArabicDigits($booking->id) }}</h4>
                    </div>
                    <div class="booking-card-body">
                        @if (session('success'))
                            <div class="custom-alert alert-success alert-dismissible fade show" role="alert">
                                <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                                <div class="flex-grow-1"> {{ session('success') }} </div>
                                <button type="button" class="alert-close" data-bs-dismiss="alert" aria-label="Close">×</button>
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="custom-alert alert-danger alert-dismissible fade show" role="alert">
                                <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                                <div class="flex-grow-1">{{ session('error') }}</div>
                                <button type="button" class="alert-close" data-bs-dismiss="alert" aria-label="Close">×</button>
                            </div>
                        @endif
                        @if (session('info'))
                            <div class="custom-alert alert-info alert-dismissible fade show" role="alert">
                                <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                                <div class="flex-grow-1">{{ session('info') }}</div>
                                <button type="button" class="alert-close" data-bs-dismiss="alert" aria-label="Close">×</button>
                            </div>
                        @endif

                        <div class="booking-section">
                            <h5 class="section-title"> ملخص الحجز </h5>
                            <dl>
                                <dt>رقم الحجز:</dt> <dd>#{{ toArabicDigits($booking->id) }}</dd>
                                <dt>الخدمة الأساسية:</dt> <dd>{{ $booking->service?->name_ar ?? $booking->service?->name_en ?? 'غير محدد' }}</dd>
                                <dt>التاريخ والوقت:</dt> <dd>{{ $booking->booking_datetime ? toArabicDigits(\Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l, d F Y - h:i A')) : 'غير محدد' }}</dd>
                                
                                <dt>منطقة التصوير:</dt> <dd>{{ $booking->shooting_area_label }}</dd>
                                @if($booking->shooting_area === 'outside_ahsa' && $booking->outside_location_city)
                                    <dt>المدينة (خارج الأحساء):</dt> <dd>{{ $booking->outside_location_city }}</dd>
                                @endif

                                <dt>مكان الحفل (العنوان):</dt> <dd>{{ $booking->event_location ?: '-' }}</dd>
                                
                                @if($booking->addOnServices->isNotEmpty())
                                    <dt>الخدمات الإضافية:</dt>
                                    <dd>
                                        <ul class="add-on-services-summary-list">
                                            @foreach($booking->addOnServices as $addOn)
                                                <li>
                                                    <span class="add-on-name">{{ $addOn->getLocalizedNameAttribute() }}</span>
                                                    <span class="add-on-price">{{ formatAmountConditionallyPending($addOn->pivot->price_at_booking) }} {{ $booking->invoice?->currency ?: 'SAR' }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                @endif
                            </dl>
                        </div>

                        @if ($invoice = $booking->invoice)
                            <div class="booking-section">
                                <h5 class="section-title"> تفاصيل الفاتورة </h5>
                                <dl>
                                    <dt>رقم الفاتورة:</dt> <dd>{{ $invoice->invoice_number }}</dd>
                                    <dt>المبلغ الإجمالي للفاتورة:</dt> <dd class="fw-bold">{{ formatAmountConditionallyPending($invoice->amount) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    @if($booking->outside_location_fee_applied > 0)
                                        <dt>تشمل رسوم خارج المنطقة:</dt> <dd class="fw-bold">{{ formatAmountConditionallyPending($booking->outside_location_fee_applied) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    @endif
                                    @if($booking->total_add_on_services_price > 0)
                                    <dt>إجمالي الخدمات الإضافية:</dt>
                                    <dd class="fw-bold">{{ formatAmountConditionallyPending($booking->total_add_on_services_price) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    @endif
                                    <dt>المبلغ المدفوع:</dt> <dd class="text-success fw-bold">{{ formatAmountConditionallyPending($invoice->total_paid_amount) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    @if($invoice->remaining_amount > 0.009)
                                        <dt>المبلغ المتبقي:</dt> <dd class="text-danger fw-bold">{{ formatAmountConditionallyPending($invoice->remaining_amount) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    @endif
                                    <dt>خيار الدفع:</dt>
                                    <dd>
                                        @if($invoice->payment_option === 'down_payment') دفع عربون (٥٠%)
                                        @elseif($invoice->payment_option === 'full') دفع كامل
                                        @else {{ $invoice->payment_option ?? '-' }}
                                        @endif
                                    </dd>
                                    <dt>حالة الفاتورة الحالية:</dt>
                                    <dd>
                                        <span class="status-badge {{ $invoice->status_badge_class ?? 'badge-secondary' }}">
                                            {{ $invoice->status_label ?? Str::ucfirst($invoice->status) }}
                                        </span>
                                    </dd>
                                    @if($booking->discount_code_id && $booking->discountCode)
                                        <dt>كود الخصم المطبق:</dt> <dd> <span class="status-badge badge-discount">{{ $booking->discountCode->code }}</span> </dd>
                                    @endif
                                </dl>
                            </div>

                           <div class="booking-section">
                                <h5 class="section-title">
                                    @if($invoice->payment_method == 'bank_transfer')  الدفع بواسطة التحويل البنكي
                                    @elseif($invoice->payment_method == 'tamara')  الدفع بواسطة تمارا
                                    @elseif($invoice->payment_method == 'manual_confirmation_due_to_no_gateway')  بانتظار التأكيد اليدوي
                                    @else  تفاصيل الدفع
                                    @endif
                                </h5>

                                @php 
                                    $effectiveAmountDueNow = $amountDueNowOnPending ?? 0;
                                @endphp

                                @if ($invoice->status == \App\Models\Invoice::STATUS_PAID)
                                    <div class="custom-alert alert-success">
                                        <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                                        <div class="flex-grow-1">تم استلام المبلغ كاملاً للفاتورة بنجاح. شكراً لثقتكم بنا.</div>
                                    </div>
                                @elseif($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID && $effectiveAmountDueNow <= 0.009)
                                    <div class="custom-alert alert-info">
                                        <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                                        <div class="flex-grow-1">تم استلام دفعة العربون بنجاح. يرجى دفع المبلغ المتبقي ({{ formatAmountConditionallyPending($invoice->remaining_amount) }} {{ $invoice->currency }}) قبل موعد الحجز.</div>
                                    </div>
                                    @if($invoice->payment_method == 'tamara' && isset($isTamaraEnabled) && $isTamaraEnabled && $invoice->remaining_amount > 0.009)
                                        {{-- --- START: LOGIC MODIFICATION FOR TAMARA LIMIT --- --}}
                                        @if($invoice->remaining_amount > 3000)
                                            <div class="custom-alert alert-danger">
                                                <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                                                <div class="flex-grow-1">
                                                    <strong>ملاحظة هامة:</strong> المبلغ المتبقي ({{ formatAmountConditionallyPending($invoice->remaining_amount) }} {{ $invoice->currency }}) يتجاوز الحد الأقصى للدفع عبر تمارا (3000 ريال).
                                                    <br>
                                                    لإتمام عملية الدفع، يرجى التواصل معنا مباشرة عبر الواتساب على الرقم <strong dir="ltr">{{ toArabicDigits(\App\Models\Setting::where('key', 'contact_whatsapp')->value('value') ?? '') }}</strong> لترتيب الدفع عبر التحويل البنكي.
                                                </div>
                                            </div>
                                        @else
                                            <div class="mt-3 text-center">
                                                <form method="POST" action="{{ route('payment_retry_tamara', $invoice) }}" class="m-0 d-inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn-custom btn-pay"> دفع المتبقي الآن عبر تمارا</button>
                                                </form>
                                            </div>
                                        @endif
                                        {{-- --- END: LOGIC MODIFICATION FOR TAMARA LIMIT --- --}}
                                    @endif
                                @elseif(in_array($invoice->status, [\App\Models\Invoice::STATUS_UNPAID, \App\Models\Invoice::STATUS_FAILED, \App\Models\Invoice::STATUS_PENDING_CONFIRMATION]) || ($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID && $effectiveAmountDueNow > 0.009) )
                                    <div class="custom-alert {{ ($invoice->status == \App\Models\Invoice::STATUS_FAILED) ? 'alert-danger' : 'alert-warning' }}">
                                        <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                                        <div class="flex-grow-1">
                                            @if($invoice->status == \App\Models\Invoice::STATUS_UNPAID || $invoice->status == \App\Models\Invoice::STATUS_PENDING_CONFIRMATION)
                                                الفاتورة بانتظار الدفع. المبلغ المطلوب دفعه الآن: <strong>{{ formatAmountConditionallyPending($effectiveAmountDueNow) }} {{ $invoice->currency }}</strong>.
                                            @elseif($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID && $effectiveAmountDueNow > 0.009)
                                                مطلوب دفعة متبقية: <strong>{{ formatAmountConditionallyPending($effectiveAmountDueNow) }} {{ $invoice->currency }}</strong>.
                                            @else
                                                فشلت محاولة الدفع الأخيرة أو تم إلغاؤها/انتهاء صلاحيتها.
                                            @endif
                                        </div>
                                    </div>

                                    @if($invoice->payment_method == 'tamara' && isset($isTamaraEnabled) && $isTamaraEnabled && $effectiveAmountDueNow > 0.009)
                                        {{-- --- START: LOGIC MODIFICATION FOR TAMARA LIMIT --- --}}
                                        @if($effectiveAmountDueNow > 3000)
                                            <div class="custom-alert alert-danger">
                                                <svg class="alert-icon" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                                                <div class="flex-grow-1">
                                                    <strong>ملاحظة هامة:</strong> المبلغ المطلوب دفعه ({{ formatAmountConditionallyPending($effectiveAmountDueNow) }} {{ $invoice->currency }}) يتجاوز الحد الأقصى للدفع عبر تمارا (3000 ريال).
                                                    <br>
                                                    لإتمام عملية الدفع، يرجى التواصل معنا مباشرة عبر الواتساب على الرقم <strong dir="ltr">{{ toArabicDigits(\App\Models\Setting::where('key', 'contact_whatsapp')->value('value') ?? '') }}</strong> لترتيب الدفع عبر التحويل البنكي.
                                                </div>
                                            </div>
                                        @else
                                            <div class="mt-3 text-center">
                                                <form method="POST" action="{{ route('payment_retry_tamara', $invoice) }}" class="m-0 d-inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn-custom btn-pay"> 
                                                        {{ $invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID ? 'دفع المتبقي الآن' : 'ادفع الآن عبر تمارا' }}
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                        {{-- --- END: LOGIC MODIFICATION FOR TAMARA LIMIT --- --}}
                                    @elseif($invoice->payment_method == 'bank_transfer' && isset($isBankTransferEnabled) && $isBankTransferEnabled && $effectiveAmountDueNow > 0.009)
                                        <p class="mt-3">الرجاء تحويل المبلغ المطلوب (<strong>{{ formatAmountConditionallyPending($effectiveAmountDueNow) }} {{ $invoice->currency }}</strong>) إلى أحد الحسابات البنكية التالية وإرسال الإيصال عبر الواتساب <strong dir="ltr">{{ toArabicDigits(\App\Models\Setting::where('key', 'contact_whatsapp')->value('value') ?? '') }}</strong> لتأكيد حجزك.</p>
                                        @if($bankAccounts && $bankAccounts->count() > 0)
                                            <ul class="bank-accounts-list mt-3">
                                                @foreach($bankAccounts as $account)
                                                <li class="bank-account-item">
                                                    <div class="bank-name">{{ $account->{'bank_name_' . app()->getLocale()} ?? $account->bank_name_ar }}</div>
                                                    <div class="bank-detail"> <span class="bank-detail-label">اسم المستفيد:</span> <span class="bank-detail-value">{{ $account->{'account_name_' . app()->getLocale()} ?? $account->account_name_ar }}</span> </div>
                                                    <div class="bank-detail"> <span class="bank-detail-label">رقم الحساب:</span> <span class="bank-detail-value" dir="ltr">{{ toArabicDigits($account->account_number ?? '-') }}</span> </div>
                                                    <div class="bank-detail"> <span class="bank-detail-label">رقم IBAN:</span> <span class="bank-detail-value" dir="ltr" style="text-align: left;">{{ $account->iban ?? '-' }}</span> </div>
                                                </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="alert alert-warning mt-3 small">لم يتم إضافة حسابات بنكية بواسطة الإدارة بعد. سيتم التواصل معك لتزويدك بالبيانات.</div>
                                        @endif
                                    @elseif($invoice->payment_method == 'manual_confirmation_due_to_no_gateway')
                                        <p class="mt-3">سيتم التواصل معك من قبل فريقنا لتأكيد الحجز وترتيب عملية الدفع. شكراً لتفهمك.</p>
                                    @endif
                                @else
                                    <div class="custom-alert alert-secondary"> حالة الفاتورة: {{ $invoice->status_label ?? $invoice->status }} </div>
                                @endif
                           </div>
                        @else
                            <div class="alert alert-warning">لم يتم إنشاء فاتورة لهذا الحجز بعد.</div>
                        @endif

                        <div class="actions-container">
                            <a href="{{ route('customer.dashboard') }}" class="btn-custom btn-primary-custom">  العودة إلى لوحة التحكم </a>
                            <a href="{{ route('services.index') }}" class="btn-custom btn-secondary-custom">  تصفح خدمات أخرى </a>
                        </div>
                    </div> 
                </div> 
            </div> 
        </div> 
    </div> 
</div> 
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.alert-close').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.custom-alert').style.display = 'none';
        });
    });
</script>
@endsection
