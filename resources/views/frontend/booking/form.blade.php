@php
    // تعريف المتغيرات هنا لتجنب الأخطاء إذا لم تكن معرفة
    $bookingPolicyAr = $bookingPolicyAr ?? '';
    $bookingPolicyEn = $bookingPolicyEn ?? '';
    $bankAccounts = $bankAccounts ?? collect(); // استخدام collection فارغة كافتراضي

    // حساب قيمة العربون (50%) للعرض
    $fullAmount = $service->price_sar ?? 0;
    // !!! تعديل: استخدام التقريب الصحيح للعربون !!!
    $downPaymentAmount = round($fullAmount / 2, 0); // التقريب لأقرب رقم صحيح للعربون
    $fullAmountFormatted = number_format($fullAmount, 0); // السعر بدون كسور عشرية
    $downPaymentAmountFormatted = number_format($downPaymentAmount, 0); // العربون بدون كسور عشرية

@endphp

@extends('layouts.app')

{{-- تحديد عنوان الصفحة --}}
@section('title', 'تأكيد الحجز')

@section('styles')
{{-- إضافة خط Tajawal --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
{{-- إضافة meta tag للـ CSRF Token (مهم إذا كان المسار في web.php) --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    /* --- نفس تنسيقات CSS السابقة بدون تغيير --- */
    /* تعريف الخط الأساسي */
    body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; direction: rtl; text-align: right; }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div { font-family: 'Tajawal', sans-serif !important; }
    .booking-form-wrapper { padding: 40px 0; min-height: calc(100vh - 150px); }
    .booking-container { max-width: 1000px; margin: 0 auto; }
    .booking-header { margin-bottom: 30px; position: relative; text-align: center; }
    .booking-header::after { content: ''; display: block; width: 100px; height: 3px; background-color: #555; margin: 15px auto 0; }
    .booking-card { background-color: #fff; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 25px; transition: all 0.3s ease; border: none; overflow: hidden; }
    .booking-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); }
    .card-header { background-color: #f8f9fa; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; position: relative; }
    .card-header h5 { margin: 0; font-weight: 700; color: #333; font-size: 1.1rem; }
    .card-header.primary-header { background-color: #555; color: white; border-bottom: none; }
    .card-header.primary-header h5 { color: white; }
    .card-body { padding: 20px; }
    .booking-summary dl { margin-bottom: 0; }
    .booking-summary dt { font-weight: 500; color: #555; }
    .booking-summary dd { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #f0f0f0; }
    .booking-summary dd:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .booking-price, #amount_to_pay_display { font-size: 1.2rem; font-weight: 700; color: #28a745; } /* تنسيق مبلغ الدفع */
    .form-label { font-weight: 500; color: #333; margin-bottom: 8px; }
    .form-control { border-radius: 8px; padding: 10px 15px; border: 1px solid #e0e0e0; transition: all 0.3s ease; }
    .form-control:focus { border-color: #555; box-shadow: 0 0 0 0.2rem rgba(85, 85, 85, 0.15); }
    textarea.form-control { min-height: 100px; }
    .input-group .btn { border-top-left-radius: 8px; border-bottom-left-radius: 8px; border-top-right-radius: 0; border-bottom-right-radius: 0; border-color: #e0e0e0; background-color: #f8f9fa; color: #555; border-right: none; }
    .input-group .btn:hover { background-color: #e9ecef; }
    .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
    .policy-box { height: 180px; overflow-y: scroll; border: 1px solid #e0e0e0; padding: 15px; background-color: #f9f9f9; margin-bottom: 1rem; border-radius: 8px; line-height: 1.6; }
    .policy-box::-webkit-scrollbar { width: 8px; }
    .policy-box::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .policy-box::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
    .policy-box::-webkit-scrollbar-thumb:hover { background: #ccc; }
    .policy-check-container { display: flex; margin-top: 20px; }
    .policy-check-input { flex-shrink: 0; width: 20px; height: 20px; margin-left: 10px; cursor: pointer; }
    .policy-check-label { font-weight: 500; cursor: pointer; }
    .policy-check-input.is-invalid { border-color: #dc3545; }
    .policy-check-input.is-invalid + .policy-check-label { color: #dc3545; }
    .payment-options, .payment-methods { display: flex; flex-direction: column; gap: 15px; } /* تنسيق خيارات الدفع */
    .payment-option-item, .payment-method-item { display: flex; align-items: center; padding: 12px; border: 1px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; }
    .payment-option-item:hover, .payment-method-item:hover { background-color: #f9f9f9; border-color: #ccc; }
    .payment-option-item.selected, .payment-method-item.selected { border-color: #555; background-color: rgba(85, 85, 85, 0.05); }
    .payment-option-item .amount, .payment-method-item img { margin: 0 8px; } /* تعديل بسيط */
    .payment-option-item .amount { font-weight: 600; color: #28a745; } /* لون مبلغ العربون */
    #bank-details { background-color: #f9f9f9; border-radius: 10px; padding: 15px; margin-top: 15px; }
    #bank-details h6 { font-weight: 700; margin-bottom: 15px; color: #333; }
    #bank-details ul { padding-right: 0; }
    #bank-details li { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; list-style: none; }
    #bank-details li:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    #bank-details .bank-item-header { font-weight: 700; color: #333; margin-bottom: 8px; }
    #bank-details .bank-item-detail { margin-bottom: 5px; display: flex; align-items: center; font-size: 0.9rem;} /* تصغير خط تفاصيل البنك */
    #bank-details .bank-item-detail-label { font-weight: 500; min-width: 90px; } /* تقليل عرض الليبل */
    .custom-btn { border-radius: 50px; padding: 12px 25px; font-weight: 600; transition: all 0.3s ease; text-align: center; border: none; }
    .custom-btn-primary { background-color: #555; color: white; }
    .custom-btn-primary:hover { background-color: #444; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .custom-btn-outline { background-color: transparent; border: 2px solid #555; color: #555; }
    .custom-btn-outline:hover { background-color: #555; color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .custom-btn-success { background-color: #28a745; color: white; }
    .custom-btn-success:hover { background-color: #218838; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-2px); }
    @media (max-width: 768px) { .booking-form-wrapper { padding: 20px 0; } .card-body { padding: 15px; } .booking-summary dt, .booking-summary dd { width: 100%; text-align: right; } .input-group .btn { font-size: 0.9rem; padding: 8px 12px;} }
    .is-invalid { border-color: #dc3545 !important; }
    .invalid-feedback.d-block { display: block !important; }
    .invalid-feedback { display: block; color: #dc3545; margin-top: 5px; font-size: 0.85rem; }
    .alert { border-radius: 10px; padding: 15px; margin-bottom: 20px; border: none; }
    .alert-danger { background-color: #fff2f2; color: #dc3545; border-right: 4px solid #dc3545; }
    #discount_result .text-success { color: #198754 !important; font-weight: 500; }
    #discount_result .text-danger { color: #dc3545 !important; font-weight: 500; }
    #discount_result .spinner-border-sm { width: 1rem; height: 1rem; border-width: .2em; }
</style>
@endsection

@section('content')
<div class="booking-form-wrapper">
    <div class="container booking-container">
        <div class="booking-header">
            <h1 class="mb-2">تأكيد تفاصيل الحجز</h1>
            <p class="text-muted">يرجى تعبئة جميع البيانات المطلوبة لإتمام الحجز</p>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- ملخص الحجز --}}
        <div class="booking-card mb-4">
            <div class="card-header primary-header">
                <h5 class="mb-0">ملخص الحجز</h5>
            </div>
            <div class="card-body booking-summary">
                <dl class="row mb-0">
                    <dt class="col-sm-3">الخدمة:</dt>
                    <dd class="col-sm-9">{{ $service->{'name_' . app()->getLocale()} ?? $service->name_ar }}</dd>

                    <dt class="col-sm-3">التاريخ والوقت:</dt>
                    <dd class="col-sm-9">{{ $bookingDateTime ? toArabicDigits($bookingDateTime->translatedFormat('l, d F Y - h:i A')) : 'غير محدد' }}</dd>

                    <dt class="col-sm-3">السعر الإجمالي:</dt>
                    <dd class="col-sm-9 booking-price" id="total_amount_display">{{ toArabicDigits($fullAmountFormatted) }} ريال سعودي</dd>

                    <dt class="col-sm-3">المبلغ المطلوب للدفع الآن:</dt>
                    <dd class="col-sm-9 booking-price" id="amount_to_pay_display">{{ toArabicDigits($fullAmountFormatted) }} ريال سعودي</dd>
                </dl>
            </div>
        </div>

        {{-- نموذج الحجز --}}
        <form action="{{ route('booking.submit') }}" method="POST" id="booking-form">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">
            <input type="hidden" name="date" value="{{ $selectedDate }}">
            <input type="hidden" name="time" value="{{ $selectedTime }}">
            <input type="hidden" name="payment_option" id="payment_option_input" value="full">

            {{-- معلومات إضافية --}}
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0">معلومات إضافية</h5> </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="event_location" class="form-label">مكان الحفل/المناسبة</label>
                            <input type="text" class="form-control @error('event_location') is-invalid @enderror" id="event_location" name="event_location" value="{{ old('event_location') }}">
                            @error('event_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                             <label for="groom_name_en" class="form-label">اسم العريس (بالإنجليزية)</label>
                             <input type="text" class="form-control @error('groom_name_en') is-invalid @enderror" id="groom_name_en" name="groom_name_en" value="{{ old('groom_name_en') }}">
                             @error('groom_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                         <div class="col-md-6 mb-3">
                             <label for="bride_name_en" class="form-label">اسم العروس (بالإنجليزية)</label>
                             <input type="text" class="form-control @error('bride_name_en') is-invalid @enderror" id="bride_name_en" name="bride_name_en" value="{{ old('bride_name_en') }}">
                             @error('bride_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                         </div>
                        <div class="col-md-12 mb-3">
                            <label for="customer_notes" class="form-label">ملاحظات إضافية (اختياري)</label>
                            <textarea class="form-control @error('customer_notes') is-invalid @enderror" id="customer_notes" name="customer_notes" rows="3">{{ old('customer_notes') }}</textarea>
                            @error('customer_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- كود الخصم --}}
            <div class="booking-card mb-4">
                <div class="card-header"><h5 class="mb-0">كود الخصم (اختياري)</h5></div>
                <div class="card-body">
                    <label for="discount_code_input" class="form-label visually-hidden">كود الخصم</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control @error('discount_code') is-invalid @enderror" placeholder="أدخل كود الخصم هنا" name="discount_code" id="discount_code_input" value="{{ old('discount_code') }}" aria-describedby="discount_result">
                        <button class="btn btn-outline-secondary" type="button" id="check_discount_btn" style="border-color: #e0e0e0;">
                             التحقق
                        </button>
                    </div>
                     @error('discount_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div id="discount_result" class="mt-1" style="min-height: 22px; font-size: 0.9rem;"></div>
                </div>
            </div>

            {{-- خيار الدفع --}}
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0"> اختر خيار الدفع <span class="text-danger">*</span> </h5> </div>
                <div class="card-body">
                    @error('payment_option') <div class="alert alert-danger">{{ $message }}</div> @enderror
                    <div class="payment-options">
                        <div class="payment-option-item selected" data-value="full">
                            <input class="form-check-input" type="radio" name="payment_option_radio" id="pay_full" value="full" checked>
                            <label for="pay_full" style="cursor:pointer; margin-right: 8px;">دفع المبلغ كاملاً</label>
                            <span class="ms-auto amount" id="full_payment_option_amount">{{ toArabicDigits($fullAmountFormatted) }} ريال</span>
                        </div>
                        <div class="payment-option-item" data-value="down_payment">
                            <input class="form-check-input" type="radio" name="payment_option_radio" id="pay_down_payment" value="down_payment">
                            <label for="pay_down_payment" style="cursor:pointer; margin-right: 8px;">دفع عربون (٥٠%) لتأكيد الحجز</label>
                            <span class="ms-auto amount" id="down_payment_option_amount">{{ toArabicDigits($downPaymentAmountFormatted) }} ريال</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- سياسة الحجز --}}
            <div class="booking-card mb-4">
                <div class="card-header"><h5 class="mb-0">سياسة الحجز</h5></div>
                <div class="card-body">
                    <div class="policy-box">
                         @php
                             $policy = app()->getLocale() == 'ar' ? ($bookingPolicyAr ?? '') : ($bookingPolicyEn ?? '');
                             if(empty($policy) && app()->getLocale() == 'en') $policy = $bookingPolicyAr ?? '';
                             if(empty($policy)) $policy = 'لم يتم تحديد سياسة الحجز بعد.';
                         @endphp
                         {!! nl2br(e($policy)) !!}
                    </div>
                    <div class="policy-check-container">
                        <input class="policy-check-input form-check-input @error('agreed_to_policy') is-invalid @enderror" type="checkbox" value="1" id="agreed_to_policy" name="agreed_to_policy" required>
                        <label class="policy-check-label form-check-label" for="agreed_to_policy">
                            لقد قرأت سياسة الحجز وأوافق عليها <span class="text-danger">*</span>
                        </label>
                        @error('agreed_to_policy') <div class="invalid-feedback d-block">يجب الموافقة على سياسة الحجز للمتابعة.</div> @enderror
                    </div>
                </div>
            </div>

            {{-- طريقة الدفع --}}
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0"> اختر طريقة الدفع <span class="text-danger">*</span> </h5> </div>
                <div class="card-body">
                    @error('payment_method') <div class="alert alert-danger">{{ $message }}</div> @enderror
                    <div class="payment-methods">
                        <div class="payment-method-item {{ old('payment_method', 'tamara') == 'tamara' ? 'selected' : '' }}" data-value="tamara">
                             <input class="form-check-input" type="radio" name="payment_method" id="pay_tamara" value="tamara" {{ old('payment_method', 'tamara') == 'tamara' ? 'checked' : '' }} required>
                             <img src="{{ asset('images/tamara.png') }}" height="30" alt="Tamara" style="margin-right: 8px; max-width: 100px;">
                             <label for="pay_tamara" style="cursor:pointer;">الدفع لاحقاً أو على أقساط مع تمارا</label>
                        </div>
                         <div class="payment-method-item {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}" data-value="bank_transfer">
                             <input class="form-check-input" type="radio" name="payment_method" id="pay_bank" value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'checked' : '' }} required>
                             <label for="pay_bank" style="cursor:pointer; margin-right: 8px;">تحويل بنكي</label>
                        </div>
                    </div>
                    <div id="bank-details" style="display: {{ old('payment_method') == 'bank_transfer' ? 'block' : 'none' }};">
                        <hr class="my-3">
                        <h6>بيانات التحويل البنكي:</h6>
                        @if($bankAccounts->isEmpty())
                            <p class="alert alert-warning small">لم يتم إضافة حسابات بنكية بواسطة الإدارة بعد.</p>
                        @else
                            <ul class="list-unstyled mb-2">
                                @foreach($bankAccounts as $account)
                                <li class="mb-3 pb-3 border-bottom">
                                    <div class="bank-item-header">{{ $account->{'bank_name_' . app()->getLocale()} ?? $account->bank_name_ar }}</div>
                                    <div class="bank-item-detail">
                                        <span class="bank-item-detail-label">اسم المستفيد:</span>
                                        <span>{{ $account->account_name_ar ?: ($account->owner_name ?? '-') }}</span>
                                    </div>
                                    <div class="bank-item-detail">
                                        <span class="bank-item-detail-label">رقم الحساب:</span>
                                        <span>{{ toArabicDigits($account->account_number ?? '-') }}</span>
                                    </div>
                                    <div class="bank-item-detail">
                                        <span class="bank-item-detail-label">رقم IBAN:</span>
                                        <span dir="ltr" class="d-inline-block user-select-all" style="text-align: left;">{{ $account->iban ?? '-' }}</span>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                             <p class="text-muted small mt-3">
                                 يرجى تحويل المبلغ المطلوب إلى أحد الحسابات الموضحة أعلاه وإرسال إيصال التحويل عبر الواتساب <strong dir="ltr">{{ toArabicDigits('0536311315') }}</strong> لتأكيد حجزك.
                             </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- أزرار الإرسال --}}
            <div class="d-grid gap-3">
                <button type="submit" class="btn custom-btn custom-btn-success">
                     تأكيد الحجز والمتابعة للدفع
                </button>
                <a href="{{ route('booking.calendar', $service->id) }}" class="btn custom-btn custom-btn-outline">
                     العودة للتقويم
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // --- !!! دالة JS لتحويل الأرقام !!! ---
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

    // --- المتغيرات الأساسية للأسعار والخصم ---
    const originalFullAmount = {{ $fullAmount }}; // السعر الأصلي بدون كسور
    let currentFullAmount = originalFullAmount; // السعر الحالي (قد يتغير بالخصم)
    let currentDiscountValue = 0; // قيمة الخصم الحالية بالريال
    let currentDiscountType = null; // 'percentage' or 'fixed'
    let isDiscountApplied = false;

    // العناصر DOM
    const totalAmountDisplay = document.getElementById('total_amount_display');
    const amountToPayDisplay = document.getElementById('amount_to_pay_display');
    const paymentOptionInput = document.getElementById('payment_option_input');
    const paymentOptionItems = document.querySelectorAll('.payment-option-item');
    const fullPaymentOptionAmountSpan = document.getElementById('full_payment_option_amount');
    const downPaymentOptionAmountSpan = document.getElementById('down_payment_option_amount');
    // const downPaymentPercentageSpan = document.getElementById('down_payment_percentage'); // (يمكن إلغاء هذا إذا كانت النسبة ثابتة 50%)
    const discountInput = document.getElementById('discount_code_input');
    const checkDiscountBtn = document.getElementById('check_discount_btn');
    const discountResultDiv = document.getElementById('discount_result');
    const paymentMethodItems = document.querySelectorAll('.payment-method-item');
    const bankDetailsDiv = document.getElementById('bank-details');
    const serviceIdForDiscount = '{{ $service->id }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');


    // --- دالة لتحديث جميع الأسعار المعروضة ---
    function updateDisplayedPrices() {
        const downPayment = Math.round(currentFullAmount / 2); // حساب العربون على السعر الحالي
        const currentPaymentOption = paymentOptionInput.value;

        // تنسيق الأرقام باستخدام toFixed(0) ثم التحويل للعربية
        const formattedFullAmount = toArabicDigitsJS(currentFullAmount.toFixed(0));
        const formattedDownPayment = toArabicDigitsJS(downPayment.toFixed(0));
        const formattedAmountToPay = toArabicDigitsJS((currentPaymentOption === 'full' ? currentFullAmount : downPayment).toFixed(0));

        // تحديث العناصر في الصفحة
        if(totalAmountDisplay) totalAmountDisplay.textContent = `${formattedFullAmount} ريال سعودي`;
        if(amountToPayDisplay) amountToPayDisplay.textContent = `${formattedAmountToPay} ريال سعودي`;
        if(fullPaymentOptionAmountSpan) fullPaymentOptionAmountSpan.textContent = `${formattedFullAmount} ريال`;
        if(downPaymentOptionAmountSpan) downPaymentOptionAmountSpan.textContent = `${formattedDownPayment} ريال`;

        // إعادة تحديد الـ selection بصرياً لخيار الدفع
         paymentOptionItems.forEach(item => {
             if(item.dataset.value === currentPaymentOption){
                 item.classList.add('selected');
                 const radio = item.querySelector('input[type="radio"]');
                 if(radio) radio.checked = true;
             } else {
                 item.classList.remove('selected');
                 const radio = item.querySelector('input[type="radio"]');
                 if(radio) radio.checked = false;
             }
         });
    }


    // --- دالة لاختيار خيار الدفع (كامل/عربون) ---
    function selectPaymentOption(option) {
        paymentOptionInput.value = option; // تحديث قيمة الحقل المخفي أولاً
        updateDisplayedPrices(); // استدعاء الدالة الموحدة لتحديث كل شيء
    }


    // --- دالة لاختيار طريقة الدفع ---
    function selectPaymentMethod(method) {
         paymentMethodItems.forEach(item => {
            item.classList.remove('selected');
            const radio = item.querySelector('input[type="radio"]');
            if(radio) radio.checked = false;
         });

         const selectedItem = document.querySelector(`.payment-method-item[data-value="${method}"]`);
         if(selectedItem){
             selectedItem.classList.add('selected');
             const radio = selectedItem.querySelector('input[type="radio"]');
             if(radio) radio.checked = true;
         }

         if(bankDetailsDiv) bankDetailsDiv.style.display = (method === 'bank_transfer') ? 'block' : 'none';
    }


    // --- إضافة مستمعي الأحداث عند تحميل الصفحة ---
    document.addEventListener('DOMContentLoaded', function() {

        // مستمع حدث لخيارات الدفع (كامل/عربون)
        paymentOptionItems.forEach(item => {
            item.addEventListener('click', function() {
                // التأكد من أن الدالة selectPaymentOption متاحة قبل استدعائها
                 if (typeof selectPaymentOption === 'function') {
                    selectPaymentOption(this.dataset.value);
                }
            });
        });

        // مستمع حدث لطرق الدفع (تمارا/بنك)
        paymentMethodItems.forEach(item => {
             item.addEventListener('click', function() {
                 // التأكد من أن الدالة selectPaymentMethod متاحة
                 if (typeof selectPaymentMethod === 'function') {
                     selectPaymentMethod(this.dataset.value);
                 }
             });
         });


        // مستمع حدث لزر التحقق من الخصم
        if (checkDiscountBtn && discountInput) { // التأكد من وجود العناصر
            checkDiscountBtn.addEventListener('click', function() {
                const code = discountInput.value.trim();
                if (!code) {
                    discountResultDiv.innerHTML = '<span class="text-danger">الرجاء إدخال كود الخصم أولاً.</span>';
                    return;
                }
                discountResultDiv.innerHTML = `<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">جاري التحقق...</span></div>`;
                checkDiscountBtn.disabled = true;
                discountInput.classList.remove('is-invalid');

                fetch('{{ route("api.discount.check") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken && {'X-CSRF-TOKEN': csrfToken}),
                    },
                    body: JSON.stringify({
                        discount_code: code,
                        service_id: serviceIdForDiscount
                    })
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(({ status, body }) => {
                    checkDiscountBtn.disabled = false; // إعادة تفعيل الزر دائماً بعد الرد
                    if (status >= 200 && status < 300 && body.valid) {
                        isDiscountApplied = true;
                        currentDiscountValue = parseFloat(body.discount_value_raw || 0);
                        currentDiscountType = body.discount_type;

                        // --- !!! التحديث الرئيسي هنا !!! ---
                        currentFullAmount = parseFloat(body.new_price_raw || originalFullAmount);
                        // استدعاء دالة تحديث الأسعار لتعكس الخصم
                         if (typeof updateDisplayedPrices === 'function') {
                             updateDisplayedPrices();
                         }
                        // ---------------------------------

                        // تحديث رسالة النتيجة بالأرقام العربية
                        discountResultDiv.innerHTML = `<span class="text-success">${body.message}. تم خصم: ${toArabicDigitsJS(body.discount_amount)} ${body.currency}</span>`;
                        checkDiscountBtn.innerHTML = 'تم التحقق';
                        checkDiscountBtn.disabled = true; // تعطيل الزر بعد التحقق الناجح
                        discountInput.readOnly = true; // جعل الحقل للقراءة فقط

                    } else {
                        // إذا فشل التحقق، أعد الأسعار للأصلية
                        isDiscountApplied = false;
                        currentDiscountValue = 0;
                        currentDiscountType = null;
                        currentFullAmount = originalFullAmount;
                         if (typeof updateDisplayedPrices === 'function') {
                             updateDisplayedPrices();
                         }

                        const errorMessage = body.message || 'كود الخصم غير صالح أو حدث خطأ.';
                        discountResultDiv.innerHTML = `<span class="text-danger">${errorMessage}</span>`;
                        discountInput.classList.add('is-invalid');
                        checkDiscountBtn.innerHTML = 'التحقق';
                    }
                })
                .catch(error => {
                    console.error('Discount Check Fetch Error:', error);
                    // إعادة الأسعار للأصلية عند حدوث خطأ في الشبكة
                    isDiscountApplied = false;
                    currentDiscountValue = 0;
                    currentDiscountType = null;
                    currentFullAmount = originalFullAmount;
                     if (typeof updateDisplayedPrices === 'function') {
                         updateDisplayedPrices();
                     }

                    discountResultDiv.innerHTML = '<span class="text-danger">حدث خطأ في الشبكة. يرجى المحاولة مرة أخرى.</span>';
                    checkDiscountBtn.disabled = false;
                    checkDiscountBtn.innerHTML = 'التحقق';
                });
            });
        }

        // مستمع حدث لإعادة تعيين الأسعار إذا تم تغيير كود الخصم بعد التحقق
         if(discountInput) {
             discountInput.addEventListener('input', function() {
                 // فقط إذا كان الخصم مطبقاً أو كان الحقل غير صالح
                 if (isDiscountApplied || discountInput.classList.contains('is-invalid')) {
                     isDiscountApplied = false;
                     currentDiscountValue = 0;
                     currentDiscountType = null;
                     currentFullAmount = originalFullAmount; // العودة للسعر الأصلي

                     // التأكد من وجود الدالة قبل استدعائها
                     if (typeof updateDisplayedPrices === 'function') {
                        updateDisplayedPrices(); // تحديث الأسعار المعروضة
                     }

                     if(discountResultDiv) discountResultDiv.innerHTML = ''; // مسح رسالة الخصم
                     discountInput.classList.remove('is-invalid');
                     discountInput.readOnly = false; // السماح بالتعديل مجدداً
                     if(checkDiscountBtn) {
                         checkDiscountBtn.disabled = false; // تفعيل زر التحقق
                         checkDiscountBtn.innerHTML = 'التحقق';
                     }
                 }
             });
         }

        // استدعاء الدوال لضبط الحالة الأولية عند تحميل الصفحة
        // التأكد من وجود الدوال قبل استدعائها
        if (typeof selectPaymentOption === 'function') {
            selectPaymentOption(paymentOptionInput?.value || 'full');
        }
        if (typeof selectPaymentMethod === 'function') {
             selectPaymentMethod(document.querySelector('input[name="payment_method"]:checked')?.value || 'tamara');
         }
         // استدعاء تحديث الأسعار بشكل صريح عند التحميل الأولي للتأكد من الأرقام العربية
          if (typeof updateDisplayedPrices === 'function') {
              updateDisplayedPrices();
          }


    }); // End DOMContentLoaded
</script>
@endsection