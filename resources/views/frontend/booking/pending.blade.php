{{-- المسار: resources/views/frontend/booking/pending.blade.php --}}

@extends('layouts.app')

{{-- !!! تم التعديل: استخدام الدالة المساعدة لرقم الحجز !!! --}}
@section('title', 'حالة طلب الحجز #' . toArabicDigits($booking->id))

@section('styles')
{{-- ... (نفس التنسيقات السابقة) ... --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* تعريف الخط الأساسي */
    body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; direction: rtl; text-align: right; }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div, dl, dt, dd { font-family: 'Tajawal', sans-serif !important; } /* إضافة dl, dt, dd */
    .booking-confirmation-wrapper { padding: 40px 0; min-height: calc(100vh - 150px); }
    .booking-card { background-color: #fff; border-radius: 15px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); overflow: hidden; border: none; margin-bottom: 20px; }
    .booking-card-header { background-color: #555; color: white; padding: 20px; border-bottom: none; }
    .booking-card-header h4 { margin: 0; font-weight: 700; font-size: 1.25rem; }
    .booking-card-body { padding: 25px; }
    .section-title { font-weight: 700; font-size: 1.1rem; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; }
    .section-title i { margin-left: 8px; color: #555; width: 1.2em; text-align: center;}
    html[dir="ltr"] .section-title i { margin-left: 0; margin-right: 8px; }
    .booking-section { background-color: #fff; border-radius: 12px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05); padding: 20px; margin-bottom: 20px; border: 1px solid #f0f0f0; }
    /* استخدام dl, dt, dd لتنسيق أفضل */
    .booking-section dl dt { font-weight: 600; color: #555; margin-bottom: 5px; }
    .booking-section dl dd { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #f0f0f0; margin-right: 0; /* Reset default margin */ }
    .booking-section dl dd:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

    .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: 500; font-size: 0.85rem; display: inline-block; }
     /* إضافة كلاسات للـ badges من موديل Invoice */
     .badge-unpaid { background-color: #ffc107; color: #664d03; }
     .badge-paid { background-color: #198754; color: white; }
     .badge-partially-paid { background-color: #0dcaf0; color: #055160; }
     .badge-cancelled { background-color: #dc3545; color: white; }
     .badge-failed { background-color: #dc3545; color: white; }
     .badge-pending { background-color: #6c757d; color: white; }
     .badge-expired { background-color: #adb5bd; color: #495057; }
     .badge-secondary { background-color: #6c757d; color: white; } /* Fallback */
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
     .btn-pay { /* زر الدفع الآن */
         background-color: #28a745; color: white;
     }
      .btn-pay:hover { background-color: #218838; color: white;}

    @media (max-width: 576px) { /* تعديلات للشاشات الصغيرة جداً */
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
                        {{-- رسائل الحالة (Flash Messages) --}}
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

                        {{-- *** قسم ملخص الحجز *** --}}
                        <div class="booking-section">
                            <h5 class="section-title"> ملخص الحجز </h5>
                            <dl>
                                <dt>رقم الحجز:</dt> <dd>#{{ toArabicDigits($booking->id) }}</dd>
                                <dt>الخدمة:</dt> <dd>{{ $booking->service?->name_ar ?? $booking->service?->name_en ?? 'غير محدد' }}</dd>
                                <dt>التاريخ والوقت:</dt> <dd>{{ $booking->booking_datetime ? toArabicDigits(\Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l, d F Y - h:i A')) : 'غير محدد' }}</dd>
                                <dt>مكان الحفل:</dt> <dd>{{ $booking->event_location ?: '-' }}</dd>
                            </dl>
                        </div>

                        {{-- *** قسم تفاصيل الفاتورة *** --}}
                        @if ($invoice = $booking->invoice)
                            <div class="booking-section">
                                <h5 class="section-title"> تفاصيل الفاتورة </h5>
                                <dl>
                                    <dt>رقم الفاتورة:</dt> <dd>{{ $invoice->invoice_number }}</dd>
                                    {{-- !!! تم التعديل: استخدام ليبل ومبالغ محسنة !!! --}}
                                    <dt>المبلغ الإجمالي:</dt> <dd class="fw-bold">{{ toArabicDigits(number_format($invoice->amount, 0)) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    <dt>المبلغ المدفوع:</dt> <dd class="text-success fw-bold">{{ toArabicDigits(number_format($invoice->total_paid_amount, 0)) }} {{ $invoice->currency ?? 'SAR' }}</dd>
                                    {{-- عرض المتبقي فقط إذا كان أكبر من صفر --}}
                                    @if($invoice->remaining_amount > 0)
                                        <dt>المبلغ المتبقي:</dt> <dd class="text-danger fw-bold">{{ toArabicDigits(number_format($invoice->remaining_amount, 0)) }} {{ $invoice->currency ?? 'SAR' }}</dd>
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

                            {{-- *** قسم تعليمات الدفع (منطق مُعدل) *** --}}
                           <div class="booking-section">
                                <h5 class="section-title">
                                    @if($invoice->payment_method == 'bank_transfer') الدفع بواسطة التحويل البنكي
                                    @elseif($invoice->payment_method == 'tamara') الدفع بواسطة تمارا
                                    @else تفاصيل الدفع
                                    @endif
                                </h5>

                                {{-- !!! تم تعديل منطق عرض الرسائل !!! --}}
                                @php $remainingAmountPending = $invoice->remaining_amount ?? 0; @endphp

                                @if ($invoice->status == \App\Models\Invoice::STATUS_PAID)
                                    <div class="custom-alert alert-success">
                                        تم استلام المبلغ المتبقي من الفاتورة بنجاح. نقدر لكم ثقتكم بنا. في حال وجود أي مشكلة أو استفسار، الرجاء التواصل على الواتساب <strong dir="ltr">{{ toArabicDigits('') }}</strong>
                                    </div>
                                @elseif($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID)
                                    <div class="custom-alert alert-info d-flex justify-content-between align-items-center">
                                        <span> تم استلام دفعة العربون بنجاح. يرجى دفع المبلغ المتبقي ({{ toArabicDigits(number_format($remainingAmountPending, 0)) }} {{ $invoice->currency }}) لاحقاً.</span>
                                         {{-- زر الدفع المتبقي لتمارا --}}
                                         @if($invoice->payment_method == 'tamara' && $remainingAmountPending > 0)
                                             <form method="POST" action="{{ route('payment_retry_tamara', $invoice) }}" class="m-0">
                                                 @csrf
                                                 <button type="submit" class="btn btn-sm btn-pay ms-2"> دفع المتبقي الآن </button>
                                             </form>
                                         @endif
                                    </div>
                                     {{-- عرض تفاصيل البنك إذا كانت الطريقة تحويل بنكي --}}
                                     @if($invoice->payment_method == 'bank_transfer')
                                         <p>الرجاء تحويل المبلغ المتبقي (<strong>{{ toArabicDigits(number_format($remainingAmountPending, 0)) }} {{ $invoice->currency }}</strong>) إلى أحد الحسابات البنكية التالية وإرسال الإيصال عبر الواتساب <strong dir="ltr">{{ toArabicDigits('0536311315') }}</strong>:</p>
                                         {{-- ... (كود عرض الحسابات البنكية كما كان) ... --}}
                                          @if($bankAccounts && $bankAccounts->count() > 0)
                                             <ul class="bank-accounts-list mt-3"> /* ... */ </ul>
                                          @else
                                             <div class="alert alert-warning mt-3">لم يتم إضافة حسابات بنكية.</div>
                                          @endif
                                     @endif

                                @elseif(in_array($invoice->status, [\App\Models\Invoice::STATUS_UNPAID, \App\Models\Invoice::STATUS_FAILED, \App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_EXPIRED]))
                                     <div class="custom-alert {{ $invoice->status == \App\Models\Invoice::STATUS_UNPAID ? 'alert-warning' : 'alert-danger' }} d-flex justify-content-between align-items-center">
                                         <span>
                                             @if($invoice->status == \App\Models\Invoice::STATUS_UNPAID)
                                                  الفاتورة بانتظار الدفع.
                                             @else
                                                  فشلت محاولة الدفع الأخيرة أو تم إلغاؤها/انتهاء صلاحيتها.
                                             @endif
                                         </span>
                                         {{-- زر إعادة المحاولة لتمارا --}}
                                         @if($invoice->payment_method == 'tamara')
                                              <form method="POST" action="{{ route('payment_retry_tamara', $invoice) }}" class="m-0">
                                                  @csrf
                                                  <button type="submit" class="btn btn-sm btn-pay ms-2"> محاولة الدفع مرة أخرى </button>
                                              </form>
                                          @endif
                                     </div>
                                     {{-- عرض تفاصيل البنك إذا كانت غير مدفوعة والتحويل بنكي --}}
                                     @if($invoice->payment_method == 'bank_transfer' && $invoice->status == \App\Models\Invoice::STATUS_UNPAID)
                                          @php
                                               $amountToPayNow = ($invoice->payment_option === 'down_payment') ? round($invoice->amount / 2, 0) : $invoice->amount;
                                               $amountTypeText = ($invoice->payment_option === 'down_payment') ? 'العربون' : 'الإجمالي';
                                          @endphp
                                          <p>الرجاء تحويل مبلغ {{ $amountTypeText }} (<strong>{{ toArabicDigits(number_format($amountToPayNow, 0)) }} {{ $invoice->currency }}</strong>) إلى أحد الحسابات البنكية التالية وإرسال الإيصال عبر الواتساب <strong dir="ltr">{{ toArabicDigits('0536311315') }}</strong>:</p>
                                          {{-- ... (كود عرض الحسابات البنكية كما كان) ... --}}
                                            @if($bankAccounts && $bankAccounts->count() > 0)
                                              <ul class="bank-accounts-list mt-3"> /* ... */ </ul>
                                           @else
                                              <div class="alert alert-warning mt-3">لم يتم إضافة حسابات بنكية.</div>
                                           @endif
                                     @endif
                                @else
                                     <div class="custom-alert alert-secondary"> حالة الفاتورة: {{ $invoice->status_label ?? $invoice->status }} </div>
                                @endif
                                {{-- !!! نهاية تعديل منطق الرسائل !!! --}}

                           </div>
                        @else
                            <div class="alert alert-warning">لم يتم إنشاء فاتورة لهذا الحجز بعد.</div>
                        @endif

                        {{-- أزرار الإجراءات --}}
                        <div class="actions-container">
                            {{-- !!! تم التعديل: زر العودة يذهب للوحة التحكم !!! --}}
                            <a href="{{ route('customer.dashboard') }}" class="btn-custom btn-primary-custom"> العودة إلى لوحة التحكم </a>
                            <a href="{{ route('services.index') }}" class="btn-custom btn-secondary-custom"> تصفح خدمات أخرى </a>
                        </div>
                    </div> {{-- End booking-card-body --}}
                </div> {{-- End booking-card --}}
            </div> {{-- End col --}}
        </div> {{-- End row --}}
    </div> {{-- End container --}}
</div> {{-- End wrapper --}}
@endsection

@section('scripts')
<script>
    // إغلاق رسائل التنبيه
    document.querySelectorAll('.alert-close').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.custom-alert').style.display = 'none';
        });
    });

    // --- !!! دالة JS لتحويل الأرقام (إذا لم تكن محملة في layout رئيسي) !!! ---
    function toArabicDigitsJS(str) {
        if (str === null || str === undefined) return '';
        const western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        const eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        let numStr = String(str);
        western.forEach((digit, index) => {
            numStr = numStr.replace(new RegExp(digit, "g"), eastern[index]);
        });
        return numStr;
    }
    // --- !!! نهاية دالة JS !!! ---

</script>
@endsection