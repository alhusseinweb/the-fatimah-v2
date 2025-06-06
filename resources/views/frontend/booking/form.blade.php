@php
    // تعريف المتغيرات هنا لتجنب الأخطاء إذا لم تكن معرفة
    $bookingPolicyAr = $bookingPolicyAr ?? '';
    $bookingPolicyEn = $bookingPolicyEn ?? '';
    $bankAccounts = $bankAccounts ?? collect();

    if (!isset($settingsHomepage)) {
        $settingsHomepage = \App\Models\Setting::pluck('value', 'key')->all();
    }
    $outsideAhsaFeeFromSettings = (float)($settingsHomepage['outside_ahsa_fee'] ?? 300.00);
    $baseServicePrice = $service->price_sar ?? 0;

    $outsideAhsaCities = [
        'الخبر' => 'الخبر',
        'الظهران' => 'الظهران',
        'الدمام' => 'الدمام',
        'سيهات' => 'سيهات',
        'القطيف' => 'القطيف',
    ];

    $addOnServices = $addOnServices ?? collect();
    $downPaymentAmountBasedOnService = round($baseServicePrice / 2, 2);

    if (!function_exists('formatAmountConditionallyBookingForm')) {
        function formatAmountConditionallyBookingForm($value) {
            $value = (float) $value;
            $roundedToTwoDecimals = floor($value * 100) / 100;
            $hasSignificantFraction = (($roundedToTwoDecimals - floor($roundedToTwoDecimals)) > 0.001);
            $formattedNumber = number_format($roundedToTwoDecimals, $hasSignificantFraction ? 2 : 0, '.', '');
            if (function_exists('toArabicDigits')) {
                return toArabicDigits($formattedNumber);
            }
            return $formattedNumber;
        }
    }
    $baseServicePriceFormatted = formatAmountConditionallyBookingForm($baseServicePrice);
    $downPaymentAmountBasedOnServiceFormatted = formatAmountConditionallyBookingForm($downPaymentAmountBasedOnService);
    $outsideAhsaFeeFormatted = formatAmountConditionallyBookingForm($outsideAhsaFeeFromSettings);

    $isTamaraEnabled = $isTamaraEnabled ?? ($settingsHomepage['tamara_enabled'] ?? false);
    $isBankTransferEnabled = $isBankTransferEnabled ?? ($settingsHomepage['enable_bank_transfer'] ?? false);

    $enableBankTransferDiscountPopup = filter_var($settingsHomepage['enable_bank_transfer_discount_popup'] ?? '0', FILTER_VALIDATE_BOOLEAN);
    $bankTransferDiscountCode = $settingsHomepage['bank_transfer_discount_code'] ?? '';
    $bankTransferPopupMessage = (app()->getLocale() == 'en' && !empty($settingsHomepage['bank_transfer_discount_popup_message_en']))
                                ? $settingsHomepage['bank_transfer_discount_popup_message_en']
                                : ($settingsHomepage['bank_transfer_discount_popup_message_ar'] ?? 'لا تفوت الفرصة! خصم خاص عند الدفع بالتحويل البنكي.');
@endphp

@extends('layouts.app')

@section('title', 'تأكيد الحجز')

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
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
    .booking-price, #amount_to_pay_display { font-size: 1.2rem; font-weight: 700; color: #28a745; }
    .form-label { font-weight: 500; color: #333; margin-bottom: 8px; }
    .form-control, .form-select { border-radius: 8px; padding: 10px 15px; border: 1px solid #e0e0e0; transition: all 0.3s ease; }
    .form-control:focus, .form-select:focus { border-color: #555; box-shadow: 0 0 0 0.2rem rgba(85, 85, 85, 0.15); }
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
    .payment-options, .payment-methods, .region-options { display: flex; flex-direction: column; gap: 15px; }
    .payment-option-item, .payment-method-item, .region-option-item { display: flex; align-items: center; padding: 12px; border: 1px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; }
    .payment-option-item:hover, .payment-method-item:hover, .region-option-item:hover { background-color: #f9f9f9; border-color: #ccc; }
    .payment-option-item.selected, .payment-method-item.selected, .region-option-item.selected { border-color: #555; background-color: rgba(85, 85, 85, 0.05); box-shadow: 0 0 5px rgba(85,85,85,0.1); }
    .payment-option-item .amount, .payment-method-item img { margin: 0 8px; }
    .payment-option-item .amount { font-weight: 600; color: #28a745; }
    #bank-details { background-color: #f9f9f9; border-radius: 10px; padding: 15px; margin-top: 15px; }
    #bank-details h6 { font-weight: 700; margin-bottom: 15px; color: #333; }
    #bank-details ul { padding-right: 0; }
    #bank-details li { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; list-style: none; }
    #bank-details li:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    #bank-details .bank-item-header { font-weight: 700; color: #333; margin-bottom: 8px; }
    #bank-details .bank-item-detail { margin-bottom: 5px; display: flex; align-items: center; font-size: 0.9rem;}
    #bank-details .bank-item-detail-label { font-weight: 500; min-width: 90px; }
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
    .add-on-services-list { margin-top: 0; }
    .add-on-service-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px dashed #eee; }
    .add-on-service-item:last-child { border-bottom: none; }
    .add-on-service-item .form-check-input { margin-left: 10px; margin-top: 0; }
    html[dir="ltr"] .add-on-service-item .form-check-input { margin-left: 0; margin-right: 10px;}
    .add-on-service-item label { font-weight: 500; cursor: pointer; flex-grow: 1; margin-bottom: 0; }
    .add-on-service-price { font-weight: 600; color: #495057; margin-right: auto; white-space: nowrap; }
    .modal-header .btn-close { margin: -0.5rem -0.5rem -0.5rem auto;}
    .discount-popup-icon { font-size: 2rem; color: #198754; margin-left: 1rem; }
    html[dir="ltr"] .discount-popup-icon { margin-left: 0; margin-right: 1rem; }
    .btn-apply-discount-modal { background-color: #28a745; color: white; }
    .btn-apply-discount-modal:hover { background-color: #218838; }

    /* --- MODIFICATION START: Styles for new discount section --- */
    .discount-section .form-label { font-size: 0.9rem; font-weight: 600; color: #6c757d; }
    #available-discounts-container { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
    .available-discount-btn { cursor: pointer; background-color: #e9f5ff; border: 1px solid #b8dcfd; color: #0d6efd; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.85rem; transition: all 0.2s ease; }
    .available-discount-btn:hover { background-color: #d1e7ff; }
    #applied-discount-container { display: none; padding: 0.75rem; background-color: #d1e7dd; border-radius: 8px; border: 1px solid #a3cfbb; }
    #applied-discount-info { display: flex; justify-content: space-between; align-items: center; }
    #applied-discount-text { font-weight: 500; color: #0a3622; }
    #cancel-discount-btn { background: none; border: none; font-size: 1.2rem; color: #dc3545; opacity: 0.7; }
    #cancel-discount-btn:hover { opacity: 1; }
    #discount-loader { display: none; }
    #toggle-manual-discount { color: #0d6efd; cursor: pointer; font-size: 0.85rem; text-decoration: underline; margin-top: 0.5rem; display: inline-block; }
    #manual-discount-input-group { display: none; }
    /* --- MODIFICATION END --- */

</style>
@endsection

@section('content')
<div class="booking-form-wrapper">
    <div class="container booking-container">
        {{-- ... (Header and Summary sections remain the same) ... --}}
        <div class="booking-header">
            <h1 class="mb-2">تأكيد تفاصيل الحجز</h1>
            <p class="text-muted">يرجى تعبئة جميع البيانات المطلوبة لإتمام الحجز</p>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <div class="booking-card mb-4">
            <div class="card-header primary-header">
                <h5 class="mb-0">ملخص الحجز</h5>
            </div>
            <div class="card-body booking-summary">
                <dl class="row mb-0">
                    <dt class="col-sm-3">الخدمة:</dt>
                    <dd class="col-sm-9">{{ $service->{'name_' . app()->getLocale()} ?? $service->name_ar }}</dd>
                    <dt class="col-sm-3">التاريخ والوقت:</dt>
                    <dd class="col-sm-9">{{ $bookingDateTime ? (function_exists('toArabicDigits') ? toArabicDigits($bookingDateTime->translatedFormat('l, d F Y - h:i A')) : $bookingDateTime->translatedFormat('l, d F Y - h:i A')) : 'غير محدد' }}</dd>
                    <dt class="col-sm-3">السعر الإجمالي:</dt>
                    <dd class="col-sm-9 booking-price" id="total_amount_display">{{ $baseServicePriceFormatted }} ريال سعودي</dd>
                    <dt class="col-sm-3">المبلغ المطلوب للدفع الآن:</dt>
                    <dd class="col-sm-9 booking-price" id="amount_to_pay_display">{{ $baseServicePriceFormatted }} ريال سعودي</dd>
                </dl>
            </div>
        </div>
        
        <form action="{{ route('booking.submit') }}" method="POST" id="booking-form">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">
            <input type="hidden" name="date" value="{{ $selectedDate }}">
            <input type="hidden" name="time" id="booking_time_hidden_input" value="{{ $selectedTime }}">
            <input type="hidden" name="payment_option" id="payment_option_input" value="full">
            <input type="hidden" name="discount_code" id="discount_code_input" value="{{ old('discount_code') }}">

            {{-- ... (Region, Add-ons, and Additional Info sections remain the same) ... --}}
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0"> اختر منطقة التصوير <span class="text-danger">*</span> </h5> </div>
                <div class="card-body">
                    <div class="region-options">
                        <div class="region-option-item selected" data-value="inside_ahsa">
                            <input class="form-check-input" type="radio" name="shooting_area_option" id="area_inside_ahsa" value="inside_ahsa" {{ old('shooting_area_option', 'inside_ahsa') == 'inside_ahsa' ? 'checked' : '' }} required>
                            <label for="area_inside_ahsa" style="cursor:pointer; margin-right: 8px;">داخل الأحساء</label>
                        </div>
                        <div class="region-option-item" data-value="outside_ahsa">
                            <input class="form-check-input" type="radio" name="shooting_area_option" id="area_outside_ahsa" value="outside_ahsa" {{ old('shooting_area_option') == 'outside_ahsa' ? 'checked' : '' }} required>
                            <label for="area_outside_ahsa" style="cursor:pointer; margin-right: 8px;">خارج الأحساء (+{{ $outsideAhsaFeeFormatted }} ريال رسوم إضافية)</label>
                        </div>
                    </div>
                    <div id="outside_ahs_city_group" class="mt-3" style="display: {{ old('shooting_area_option') == 'outside_ahsa' ? 'block' : 'none' }};">
                        <label for="outside_ahs_city" class="form-label">الرجاء اختيار المدينة (خارج الأحساء) <span class="text-danger">*</span></label>
                        <select class="form-select" name="outside_ahs_city" id="outside_ahs_city" {{ old('shooting_area_option') == 'outside_ahsa' ? 'required' : '' }}>
                            <option value="">-- اختر المدينة --</option>
                            @foreach($outsideAhsaCities as $value => $label)
                                <option value="{{ $value }}" {{ old('outside_ahs_city') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @if($addOnServices && $addOnServices->count() > 0)
            <div class="booking-card mb-4">
                <div class="card-header"><h5 class="mb-0">خدمات إضافية (اختياري)</h5></div>
                <div class="card-body add-on-services-list">
                    @foreach($addOnServices as $addOn)
                    <div class="add-on-service-item">
                        <input class="form-check-input add-on-checkbox" type="checkbox" name="add_on_services[]" value="{{ $addOn->id }}" id="add_on_{{ $addOn->id }}" data-price="{{ $addOn->price }}" {{ is_array(old('add_on_services')) && in_array($addOn->id, old('add_on_services')) ? 'checked' : '' }}>
                        <label for="add_on_{{ $addOn->id }}">{{ $addOn->getLocalizedNameAttribute() }}</label>
                        <span class="add-on-service-price me-auto">{{ formatAmountConditionallyBookingForm($addOn->price) }} ريال</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0">معلومات إضافية</h5> </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="event_location" class="form-label">مكان الحفل/المناسبة (اسم القاعة، الحي، إلخ)</label>
                            <input type="text" class="form-control" id="event_location" name="event_location" value="{{ old('event_location') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                             <label for="groom_name_en" class="form-label">اسم العريس (بالإنجليزية)</label>
                             <input type="text" class="form-control" id="groom_name_en" name="groom_name_en" value="{{ old('groom_name_en') }}">
                        </div>
                         <div class="col-md-6 mb-3">
                             <label for="bride_name_en" class="form-label">اسم العروس (بالإنجليزية)</label>
                             <input type="text" class="form-control" id="bride_name_en" name="bride_name_en" value="{{ old('bride_name_en') }}">
                         </div>
                        <div class="col-md-12 mb-3">
                            <label for="customer_notes" class="form-label">ملاحظات إضافية (اختياري)</label>
                            <textarea class="form-control" id="customer_notes" name="customer_notes" rows="3">{{ old('customer_notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- --- MODIFICATION: Sections reordered --- --}}
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0"> اختر خيار الدفع <span class="text-danger">*</span> </h5> </div>
                <div class="card-body">
                    <div class="payment-options">
                        <div class="payment-option-item selected" data-value="full">
                            <input class="form-check-input" type="radio" name="payment_option_radio_display" id="pay_full" value="full" checked>
                            <label for="pay_full" style="cursor:pointer; margin-right: 8px;">دفع المبلغ كاملاً</label>
                            <span class="ms-auto amount" id="full_payment_option_amount">{{ $baseServicePriceFormatted }} ريال</span>
                        </div>
                        <div class="payment-option-item" data-value="down_payment">
                            <input class="form-check-input" type="radio" name="payment_option_radio_display" id="pay_down_payment" value="down_payment">
                            <label for="pay_down_payment" style="cursor:pointer; margin-right: 8px;">دفع عربون (٥٠%) لتأكيد الحجز</label>
                            <span class="ms-auto amount" id="down_payment_option_amount">{{ $downPaymentAmountBasedOnServiceFormatted }} ريال</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="booking-card mb-4">
                <div class="card-header"> <h5 class="mb-0"> اختر طريقة الدفع <span class="text-danger">*</span> </h5> </div>
                <div class="card-body">
                    @php
                        $defaultPaymentMethod = old('payment_method');
                        if (!$defaultPaymentMethod) {
                            if ($isTamaraEnabled) $defaultPaymentMethod = 'tamara';
                            elseif ($isBankTransferEnabled) $defaultPaymentMethod = 'bank_transfer';
                        }
                    @endphp

                    <div class="payment-methods">
                        @if($isTamaraEnabled)
                            <div class="payment-method-item {{ $defaultPaymentMethod == 'tamara' ? 'selected' : '' }}" data-value="tamara">
                                 <input class="form-check-input" type="radio" name="payment_method" id="pay_tamara" value="tamara" {{ $defaultPaymentMethod == 'tamara' ? 'checked' : '' }} required>
                                 <img src="{{ asset('images/tamara.png') }}" height="28" alt="Tamara" style="margin-right: 8px; max-width: 100px; vertical-align: middle;">
                                 <label for="pay_tamara" style="cursor:pointer;" class="ms-2"> الدفع لاحقاً أو على أقساط مع تمارا </label>
                            </div>
                        @endif

                        @if($isBankTransferEnabled)
                             <div class="payment-method-item {{ $defaultPaymentMethod == 'bank_transfer' ? 'selected' : '' }}" data-value="bank_transfer">
                                 <input class="form-check-input" type="radio" name="payment_method" id="pay_bank" value="bank_transfer" {{ $defaultPaymentMethod == 'bank_transfer' ? 'checked' : '' }} required>
                                 <img src="{{ asset('images/bank-icon.jpg') }}" height="24" alt="Bank Transfer" style="margin-right: 8px; margin-left: 5px; vertical-align: middle;">
                                 <label for="pay_bank" style="cursor:pointer;" class="ms-1"> تحويل بنكي </label>
                            </div>
                        @endif
                    </div>

                    @if(!$isTamaraEnabled && !$isBankTransferEnabled)
                        <div class="alert alert-warning py-3">
                            عفواً، لا توجد طرق دفع إلكترونية مفعلة حالياً. سيكتمل حجزك مبدئياً وسيتم التواصل معك لترتيب عملية الدفع.
                        </div>
                         <input type="hidden" name="payment_method" value="manual_confirmation_due_to_no_gateway">
                    @endif
                    
                    @if($isBankTransferEnabled)
                        <div id="bank-details" style="display: {{ $defaultPaymentMethod == 'bank_transfer' ? 'block' : 'none' }};">
                            <hr class="my-3">
                            <h6> بيانات التحويل البنكي:</h6>
                            @if($bankAccounts && $bankAccounts->count() > 0)
                                <ul class="list-unstyled mb-2 small">
                                    @foreach($bankAccounts as $account)
                                    <li class="mb-3 pb-3 border-bottom">
                                        <div class="bank-item-header">{{ $account->{'bank_name_' . app()->getLocale()} ?? $account->bank_name_ar }}</div>
                                        <div class="bank-item-detail">
                                            <span class="bank-item-detail-label">اسم المستفيد:</span>
                                            <span>{{ $account->{'account_name_' . app()->getLocale()} ?? $account->account_name_ar }}</span>
                                        </div>
                                        <div class="bank-item-detail">
                                            <span class="bank-item-detail-label">رقم الحساب:</span>
                                            <span dir="ltr" class="d-inline-block user-select-all" style="text-align: left;">{{ $account->account_number ?? '-' }}</span>
                                        </div>
                                        <div class="bank-item-detail">
                                            <span class="bank-item-detail-label">رقم IBAN:</span>
                                            <span dir="ltr" class="d-inline-block user-select-all" style="text-align: left;">{{ $account->iban ?? '-' }}</span>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                                 <p class="text-muted small mt-3">
                                     يرجى تحويل المبلغ المطلوب إلى أحد الحسابات الموضحة أعلاه وإرسال إيصال التحويل عبر الواتساب <strong dir="ltr">{{ function_exists('toArabicDigits') ? toArabicDigits(App\Models\Setting::where('key', 'contact_whatsapp')->first()->value ?? '') : (App\Models\Setting::where('key', 'contact_whatsapp')->first()->value ?? '') }}</strong> لتأكيد حجزك.
                                 </p>
                            @else
                                <p class="alert alert-secondary small">لم يتم إضافة حسابات بنكية بواسطة الإدارة بعد. إذا اخترت التحويل البنكي، سيتم التواصل معك لتزويدك بالبيانات.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            
            {{-- --- MODIFICATION START: New Discount Section --- --}}
            <div class="booking-card mb-4 discount-section">
                <div class="card-header"><h5 class="mb-0">الخصومات والعروض</h5></div>
                <div class="card-body">
                    {{-- Container for applied discount --}}
                    <div id="applied-discount-container">
                        <div id="applied-discount-info">
                            <span id="applied-discount-text"></span>
                            <button type="button" id="cancel-discount-btn" title="إلغاء الخصم">&times;</button>
                        </div>
                    </div>

                    {{-- Container for available discounts and manual input --}}
                    <div id="discount-options-container">
                        <label class="form-label" id="available-discounts-label">الخصومات المتاحة لطريقة الدفع المختارة:</label>
                        <div id="discount-loader" class="text-center py-2">
                             <div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">جاري البحث عن خصومات...</span></div>
                        </div>
                        <div id="available-discounts-container">
                           {{-- Available discount buttons will be injected here by JS --}}
                        </div>
                        <div id="no-discounts-message" class="text-muted small mt-2" style="display: none;">
                            لا توجد خصومات متاحة حالياً لطريقة الدفع المختارة.
                        </div>
                        <hr>
                        <a href="#" id="toggle-manual-discount">أو أدخل كود الخصم يدويًا</a>
                        <div class="input-group mt-2" id="manual-discount-input-group">
                             <input type="text" class="form-control" placeholder="أدخل كود الخصم هنا" id="manual_discount_code" aria-label="كود الخصم اليدوي" dir="ltr">
                             <button class="btn btn-outline-secondary" type="button" id="check_manual_discount_btn">التحقق</button>
                        </div>
                        <div id="manual_discount_result" class="mt-1" style="min-height: 22px; font-size: 0.9rem;"></div>
                    </div>
                </div>
            </div>
            {{-- --- MODIFICATION END --- --}}

            <div class="booking-card mb-4">
                <div class="card-header"><h5 class="mb-0">سياسة الحجز</h5></div>
                <div class="card-body">
                    <div class="policy-box">
                         @php
                             $policy = app()->getLocale() == 'ar' ? ($bookingPolicyAr ?? '') : ($bookingPolicyEn ?? '');
                             if(empty($policy) && app()->getLocale() == 'en') $policy = $bookingPolicyAr ?? '';
                             if(empty($policy) && app()->getLocale() == 'ar' && !empty($bookingPolicyEn)) $policy = $bookingPolicyEn ?? '';
                             if(empty($policy)) $policy = 'لم يتم تحديد سياسة الحجز بعد.';
                         @endphp
                         {!! nl2br(e($policy)) !!}
                    </div>
                    <div class="policy-check-container">
                        <input class="policy-check-input form-check-input" type="checkbox" value="1" id="agreed_to_policy" name="agreed_to_policy" {{ old('agreed_to_policy') ? 'checked' : '' }} required>
                        <label class="policy-check-label form-check-label" for="agreed_to_policy">
                            لقد قرأت سياسة الحجز وأوافق عليها <span class="text-danger">*</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-3 mt-4">
                <button type="submit" class="btn custom-btn custom-btn-success btn-lg" id="submit_booking_btn">
                     تأكيد الحجز والمتابعة للدفع
                </button>
                <a href="{{ route('booking.calendar', $service->id) }}" class="btn custom-btn custom-btn-outline">
                     العودة للتقويم
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ... (Bank Transfer Discount Modal HTML remains the same) ... --}}
@if($isBankTransferEnabled && $enableBankTransferDiscountPopup && !empty($bankTransferDiscountCode))
<div class="modal fade" id="bankTransferDiscountModal" tabindex="-1" aria-labelledby="bankTransferDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <div class="modal-header bg-light border-0 align-items-center" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                <h5 class="modal-title w-100 text-center" id="bankTransferDiscountModalLabel">
                    <i class="fas fa-tags text-success me-2"></i> فرصة خصم خاصة!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="lead mb-3">{!! nl2br(e($bankTransferPopupMessage)) !!}</p>
                <p class="mb-3">رمز الخصم: <strong class="text-primary" dir="ltr">{{ $bankTransferDiscountCode }}</strong></p>
                <button type="button" class="btn btn-success btn-lg w-100 btn-apply-discount-modal" id="applyBankDiscountBtn">
                    <i class="fas fa-check-circle me-2"></i> نعم، قم بتطبيق الخصم!
                </button>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0 pb-3">
                 <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">لا شكراً، المتابعة بدون خصم</button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script>
    // ... (toArabicDigitsJS and initial variable setup as before) ...
    function toArabicDigitsJS(str) {
        if (str === null || str === undefined) return '';
        const western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.'];
        const eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٫'];
        let numStr = String(str);
        western.forEach((digit, index) => {
            numStr = numStr.replace(new RegExp(digit.replace('.', '\\.'), "g"), eastern[index]);
        });
        return numStr;
    }

    const baseServicePriceJS = {{ (float)($baseServicePrice) }};
    const outsideAhsaFeeConstJS = {{ (float)($outsideAhsaFeeFromSettings) }};
    let priceAfterDiscountJS = baseServicePriceJS; 
    let currentDiscountValueRawJS = 0;
    let currentShootingAreaFeeJS = 0; 
    let isDiscountAppliedJS = false;
    let totalAddOnServicesPriceJS = 0;

    const totalAmountDisplayEl = document.getElementById('total_amount_display');
    const amountToPayDisplayEl = document.getElementById('amount_to_pay_display');
    const paymentOptionInputEl = document.getElementById('payment_option_input');
    const paymentOptionItemsEl = document.querySelectorAll('.payment-option-item');
    const fullPaymentOptionAmountSpanEl = document.getElementById('full_payment_option_amount');
    const downPaymentOptionAmountSpanEl = document.getElementById('down_payment_option_amount');
    
    // --- MODIFICATION START: New elements for discount UI ---
    const discountCodeInputEl = document.getElementById('discount_code_input'); // This is the hidden input now
    const manualDiscountInputEl = document.getElementById('manual_discount_code');
    const checkManualDiscountBtnEl = document.getElementById('check_manual_discount_btn');
    const manualDiscountResultDivEl = document.getElementById('manual_discount_result');
    const availableDiscountsContainer = document.getElementById('available-discounts-container');
    const noDiscountsMessage = document.getElementById('no-discounts-message');
    const discountLoader = document.getElementById('discount-loader');
    const appliedDiscountContainer = document.getElementById('applied-discount-container');
    const appliedDiscountText = document.getElementById('applied-discount-text');
    const cancelDiscountBtn = document.getElementById('cancel-discount-btn');
    const discountOptionsContainer = document.getElementById('discount-options-container');
    const toggleManualDiscountLink = document.getElementById('toggle-manual-discount');
    const manualDiscountInputGroup = document.getElementById('manual-discount-input-group');
    // --- MODIFICATION END ---
    
    const paymentMethodItemsEl = document.querySelectorAll('.payment-method-item');
    const bankDetailsDivEl = document.getElementById('bank-details');
    const serviceIdForDiscountJS = '{{ $service->id }}';
    const bookingTimeForDiscountJS = document.getElementById('booking_time_hidden_input')?.value || '{{ $selectedTime }}';
    const csrfTokenJS = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const submitBookingBtnEl = document.getElementById('submit_booking_btn');

    const regionOptionItemsEl = document.querySelectorAll('.region-option-item');
    const outsideAhsaCityGroupEl = document.getElementById('outside_ahs_city_group');
    const outsideAhsaCitySelectEl = document.getElementById('outside_ahs_city');
    const addOnCheckboxes = document.querySelectorAll('.add-on-checkbox');

    const enableBankTransferDiscountPopupJS = {{ $enableBankTransferDiscountPopup ? 'true' : 'false' }};
    const bankTransferDiscountCodeJS = "{{ $bankTransferDiscountCode ?? '' }}";
    let bankTransferDiscountModalInstance = null;
    const bankTransferDiscountModalEl = document.getElementById('bankTransferDiscountModal');
    const applyBankDiscountBtn = document.getElementById('applyBankDiscountBtn');
    let bankDiscountModalShownOnce = false;

    function formatDisplayAmountJS(value) { /* ... as before ... */ return toArabicDigitsJS( (Math.round(parseFloat(value) * 100) / 100).toFixed( (Math.abs(parseFloat(value) % 1) > 0.0001) ? 2 : 0 ) ); }
    function calculateFinalTotalJS() { /* ... as before ... */ return priceAfterDiscountJS + currentShootingAreaFeeJS + totalAddOnServicesPriceJS; }
    function updateDisplayedPricesJS() { /* ... as before ... */
        const finalTotal = calculateFinalTotalJS();
        const downPaymentCalculated = Math.round(finalTotal * 50) / 100; 
        const currentPaymentOptionValue = paymentOptionInputEl.value;
        const formattedFinalTotal = formatDisplayAmountJS(finalTotal);
        const formattedDownPayment = formatDisplayAmountJS(downPaymentCalculated);
        const amountToPayNow = currentPaymentOptionValue === 'full' ? finalTotal : downPaymentCalculated;
        const formattedAmountToPayNow = formatDisplayAmountJS(amountToPayNow);
        
        if(totalAmountDisplayEl) totalAmountDisplayEl.textContent = `${formattedFinalTotal} ريال سعودي`;
        if(amountToPayDisplayEl) amountToPayDisplayEl.textContent = `${formattedAmountToPayNow} ريال سعودي`;
        if(fullPaymentOptionAmountSpanEl) fullPaymentOptionAmountSpanEl.textContent = `${formattedFinalTotal} ريال`;
        if(downPaymentOptionAmountSpanEl) downPaymentOptionAmountSpanEl.textContent = `${formattedDownPayment} ريال`;

        paymentOptionItemsEl.forEach(item => {
            item.classList.toggle('selected', item.dataset.value === currentPaymentOptionValue);
            const radio = item.querySelector('input[type="radio"]');
            if (radio) radio.checked = (item.dataset.value === currentPaymentOptionValue);
        });
    }

    // --- MODIFICATION START: New functions for discount UI ---

    function showAppliedDiscountState(message) {
        if(appliedDiscountContainer) appliedDiscountContainer.style.display = 'block';
        if(appliedDiscountText) appliedDiscountText.textContent = message;
        if(discountOptionsContainer) discountOptionsContainer.style.display = 'none';
    }

    function showDiscountOptionsState() {
        if(appliedDiscountContainer) appliedDiscountContainer.style.display = 'none';
        if(appliedDiscountText) appliedDiscountText.textContent = '';
        if(discountOptionsContainer) discountOptionsContainer.style.display = 'block';
    }

    function resetDiscountStateJS() {
        isDiscountAppliedJS = false;
        priceAfterDiscountJS = baseServicePriceJS; 
        currentDiscountValueRawJS = 0;
        if(discountCodeInputEl) discountCodeInputEl.value = ''; // Clear hidden main input
        if(manualDiscountInputEl) {
             manualDiscountInputEl.value = '';
             manualDiscountInputEl.classList.remove('is-invalid');
        }
        if(manualDiscountResultDivEl) manualDiscountResultDivEl.innerHTML = '';
        
        showDiscountOptionsState(); // Show the discount options again
        updateDisplayedPricesJS(); 
    }
    
    async function fetchAvailableDiscounts(paymentMethod) {
        if(!availableDiscountsContainer || !discountLoader || !noDiscountsMessage) return;

        discountLoader.style.display = 'block';
        availableDiscountsContainer.innerHTML = '';
        noDiscountsMessage.style.display = 'none';
        
        try {
            // سنفترض وجود مسار API جديد. إذا لم يكن موجودًا، سيفشل هذا وسيعرض رسالة خطأ.
            // const response = await fetch('{{-- route("api.discounts.get-available") --}}', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfTokenJS },
            //     body: JSON.stringify({
            //         service_id: serviceIdForDiscountJS,
            //         payment_method: paymentMethod,
            //     })
            // });
            // if(!response.ok) throw new Error('Network response was not ok');
            // const data = await response.json();
            
            // **محاكاة مؤقتة لبيانات API**
            const data = { available_discounts: [] }; 
            // example: const data = { available_discounts: [ {code: 'BANK10', description: 'خصم 10% للتحويل البنكي'}, {code: 'WELCOME5', description: 'خصم 5% ترحيبي'} ] };
            
            if(data.available_discounts && data.available_discounts.length > 0) {
                data.available_discounts.forEach(discount => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'available-discount-btn';
                    btn.textContent = discount.description || discount.code;
                    btn.dataset.code = discount.code;
                    btn.addEventListener('click', () => checkDiscountFunctionalityJS(discount.code));
                    availableDiscountsContainer.appendChild(btn);
                });
            } else {
                noDiscountsMessage.style.display = 'block';
            }

        } catch (error) {
            console.error('Failed to fetch available discounts:', error);
            noDiscountsMessage.textContent = 'حدث خطأ في جلب الخصومات المتاحة.';
            noDiscountsMessage.style.display = 'block';
        } finally {
            discountLoader.style.display = 'none';
        }
    }

    // --- MODIFICATION END ---

    function selectPaymentMethodJS(methodValue) {
        const previousPaymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        let methodFoundAndSelected = false;
        paymentMethodItemsEl.forEach(item => {
            const radio = item.querySelector('input[name="payment_method"]');
            if (item.dataset.value === methodValue) {
                item.classList.add('selected');
                if (radio) radio.checked = true;
                methodFoundAndSelected = true;
            } else {
                item.classList.remove('selected');
                if (radio) radio.checked = false;
            }
        });
        if(!methodFoundAndSelected && !document.querySelector('input[name="payment_method"]:checked')) {
            const firstAvailableMethodRadio = document.querySelector('.payment-method-item input[name="payment_method"]');
            if(firstAvailableMethodRadio){
                firstAvailableMethodRadio.checked = true;
                const parentItem = firstAvailableMethodRadio.closest('.payment-method-item');
                if(parentItem) parentItem.classList.add('selected');
                methodValue = firstAvailableMethodRadio.value;
            }
        }
        if(bankDetailsDivEl) bankDetailsDivEl.style.display = (methodValue === 'bank_transfer') ? 'block' : 'none';
        
        // --- MODIFICATION START: Reset discount when payment method changes ---
        if(isDiscountAppliedJS && methodValue !== previousPaymentMethod) {
            resetDiscountStateJS();
            // يمكنك إضافة رسالة إعلامية هنا
        }
        // --- MODIFICATION END ---
        
        if (methodValue === 'bank_transfer' && enableBankTransferDiscountPopupJS && bankTransferDiscountCodeJS && bankTransferDiscountModalInstance && !bankDiscountModalShownOnce && !isDiscountAppliedJS) {
            bankTransferDiscountModalInstance.show();
            bankDiscountModalShownOnce = true; 
        }
        
        fetchAvailableDiscounts(methodValue); // جلب الخصومات لطريقة الدفع الجديدة
    }
    
    function checkDiscountFunctionalityJS(code) {
        if (!code) { /* ... */ return; }
        
        const resultContainer = manualDiscountResultDivEl;
        resultContainer.innerHTML = `<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">جاري التحقق...</span></div>`;
        if(checkManualDiscountBtnEl) checkManualDiscountBtnEl.disabled = true;

        const selectedPaymentMethodValue = document.querySelector('input[name="payment_method"]:checked')?.value || null;
        const payload = {
            discount_code: code,
            service_id: serviceIdForDiscountJS,
            booking_time: bookingTimeForDiscountJS, 
            selected_payment_method: selectedPaymentMethodValue,
        };

        fetch('{{ route("api.discount.check") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfTokenJS, },
            body: JSON.stringify(payload)
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            if(checkManualDiscountBtnEl) checkManualDiscountBtnEl.disabled = false;
            if (status >= 200 && status < 300 && body.valid) {
                isDiscountAppliedJS = true;
                currentDiscountValueRawJS = parseFloat(body.discount_value_raw || 0);
                priceAfterDiscountJS = parseFloat(body.new_price_raw || baseServicePriceJS); 
                updateDisplayedPricesJS();
                const formattedDiscountTaken = formatDisplayAmountJS(currentDiscountValueRawJS);
                showAppliedDiscountState(`${body.message}. تم خصم: ${formattedDiscountTaken} ${body.currency || 'ريال'}`);
                if(discountCodeInputEl) discountCodeInputEl.value = code; // تعبئة الحقل المخفي
                if (bankTransferDiscountModalInstance) bankTransferDiscountModalInstance.hide();
            } else {
                const errorMessage = body.message || 'كود الخصم غير صالح أو حدث خطأ.';
                resultContainer.innerHTML = `<span class="text-danger">${errorMessage}</span>`;
                if(manualDiscountInputEl) manualDiscountInputEl.classList.add('is-invalid');
            }
        })
        .catch(error => {
            console.error('Discount Check Fetch Error:', error);
            resultContainer.innerHTML = '<span class="text-danger">حدث خطأ في الشبكة. يرجى المحاولة مرة أخرى.</span>';
            if(checkManualDiscountBtnEl) checkManualDiscountBtnEl.disabled = false;
        });
    }

    // ... (باقي الدوال مثل handleRegionChangeJS و handleAddOnServiceChangeJS تبقى كما هي) ...
    function selectPaymentOptionJS(option) { /* ... as before ... */ }
    function handleRegionChangeJS() { /* ... as before ... */ }
    function handleAddOnServiceChangeJS() { /* ... as before ... */ }

    document.addEventListener('DOMContentLoaded', function() {
        if (bankTransferDiscountModalEl && typeof bootstrap !== 'undefined') {
            bankTransferDiscountModalInstance = new bootstrap.Modal(bankTransferDiscountModalEl);
        }
        if (applyBankDiscountBtn && bankTransferDiscountCodeJS) {
            applyBankDiscountBtn.addEventListener('click', function() {
                checkDiscountFunctionalityJS(bankTransferDiscountCodeJS);
            });
        }
        
        // --- MODIFICATION START: New event listeners for discount UI ---
        if(cancelDiscountBtn) {
            cancelDiscountBtn.addEventListener('click', resetDiscountStateJS);
        }
        if(toggleManualDiscountLink && manualDiscountInputGroup) {
            toggleManualDiscountLink.addEventListener('click', function(e) {
                e.preventDefault();
                manualDiscountInputGroup.style.display = 'flex';
                this.style.display = 'none';
            });
        }
        if(checkManualDiscountBtnEl && manualDiscountInputEl) {
            checkManualDiscountBtnEl.addEventListener('click', function() {
                checkDiscountFunctionalityJS(manualDiscountInputEl.value.trim());
            });
        }
        if(manualDiscountInputEl){
            manualDiscountInputEl.addEventListener('input', function() {
                manualDiscountInputEl.classList.remove('is-invalid');
                if(manualDiscountResultDivEl) manualDiscountResultDivEl.innerHTML = '';
            });
        }
        // --- MODIFICATION END ---
        
        paymentOptionItemsEl.forEach(item => { item.addEventListener('click', function() { selectPaymentOptionJS(this.dataset.value); }); });
        paymentMethodItemsEl.forEach(item => { item.addEventListener('click', function() { selectPaymentMethodJS(this.dataset.value); }); });
        
        regionOptionItemsEl.forEach(item => {
            item.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if(radio) radio.checked = true;
                handleRegionChangeJS();
            });
        });
        
        addOnCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', handleAddOnServiceChangeJS);
        });
        handleAddOnServiceChangeJS();
        
        const initialRegionOption = document.querySelector('input[name="shooting_area_option"]:checked')?.value || 'inside_ahsa';
        if (initialRegionOption === 'outside_ahsa') { 
            currentShootingAreaFeeJS = outsideAhsaFeeConstJS;
            if(outsideAhsaCityGroupEl) outsideAhsaCityGroupEl.style.display = 'block';
            if(outsideAhsaCitySelectEl) outsideAhsaCitySelectEl.required = true;
        } else {
            currentShootingAreaFeeJS = 0;
        }
        regionOptionItemsEl.forEach(item => {
            item.classList.toggle('selected', item.dataset.value === initialRegionOption);
        });

        const initialPaymentOption = "{{ old('payment_option', 'full') }}";
        selectPaymentOptionJS(initialPaymentOption);

        const initialPaymentMethodValue = "{{ old('payment_method') }}";
        let defaultInitialMethod = '';
        if (initialPaymentMethodValue) {
            if ( (initialPaymentMethodValue === 'tamara' && {{ $isTamaraEnabled ? 'true' : 'false' }}) ||
                 (initialPaymentMethodValue === 'bank_transfer' && {{ $isBankTransferEnabled ? 'true' : 'false' }}) ) {
                defaultInitialMethod = initialPaymentMethodValue;
            }
        }
        
        if (!defaultInitialMethod) {
            if ({{ $isTamaraEnabled ? 'true' : 'false' }}) { defaultInitialMethod = 'tamara'; } 
            else if ({{ $isBankTransferEnabled ? 'true' : 'false' }}) { defaultInitialMethod = 'bank_transfer'; }
        }
        if (defaultInitialMethod) { 
            selectPaymentMethodJS(defaultInitialMethod); 
        } 
        
        updateDisplayedPricesJS(); 
    });
</script>
@endsection
