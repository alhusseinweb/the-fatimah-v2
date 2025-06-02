@extends('layouts.admin')

@section('title', 'إنشاء حجز يدوي لعميل')

@push('styles')
{{--flatpickr--}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-weight: bold;
    }
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
    }
    .form-section:last-of-type {
        border-bottom: none;
        padding-bottom: 0;
    }
    .flatpickr-time input.form-control[readonly] {
        background-color: #fff;
    }
    #outside_ahs_city_group {
        display: none; /* Hidden by default */
    }
    .total-booking-amount-display {
        font-size: 1.1rem;
        font-weight: bold;
        color: #28a745; /* Green color for positive amount */
    }
</style>
@endpush


@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">إنشاء حجز يدوي لعميل</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">إنشاء حجز يدوي</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- لعرض رسائل الخطأ العامة --}}
    @if(session('error'))
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

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.manual-booking.store') }}" method="POST" id="manualBookingForm">
                @csrf

                {{-- Customer Details --}}
                <div class="form-section">
                    <h5 class="card-title mb-3 text-primary"><i class="fas fa-user-edit me-2"></i>1. بيانات العميل</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="customer_name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
                            @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_mobile" class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_mobile') is-invalid @enderror" id="customer_mobile" name="customer_mobile" value="{{ old('customer_mobile') }}" placeholder="05XXXXXXXX" required dir="ltr">
                            @error('customer_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('customer_email') is-invalid @enderror" id="customer_email" name="customer_email" value="{{ old('customer_email') }}" required dir="ltr">
                            @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Booking Details --}}
                <div class="form-section">
                    <h5 class="card-title mb-3 text-primary"><i class="fas fa-calendar-check me-2"></i>2. تفاصيل الحجز</h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="service_id" class="form-label">الخدمة المطلوبة <span class="text-danger">*</span></label>
                            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                                <option value="">-- اختر الخدمة --</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-price="{{ $service->price_sar }}" {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                        {{ $service->name_ar }} ({{ $service->price_sar }} ريال)
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <input type="hidden" name="service_price_from_form" id="service_price_from_form" value="{{ old('service_price_from_form', 0) }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="booking_date" class="form-label">تاريخ الحجز <span class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr-date @error('booking_date') is-invalid @enderror" id="booking_date" name="booking_date" value="{{ old('booking_date') }}" placeholder="اختر التاريخ" required>
                            @error('booking_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="booking_time" class="form-label">وقت الحجز <span class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr-time @error('booking_time') is-invalid @enderror" id="booking_time" name="booking_time" value="{{ old('booking_time') }}" placeholder="اختر الوقت" required>
                            @error('booking_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    {{-- START: Location Section --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">منطقة التصوير <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shooting_area_option" id="area_inside_ahsa" value="inside_ahsa" {{ old('shooting_area_option', 'inside_ahsa') == 'inside_ahsa' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="area_inside_ahsa">داخل الأحساء</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shooting_area_option" id="area_outside_ahsa" value="outside_ahsa" {{ old('shooting_area_option') == 'outside_ahsa' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="area_outside_ahsa">خارج الأحساء (سيتم إضافة رسوم)</label>
                            </div>
                            @error('shooting_area_option') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3" id="outside_ahs_city_group" style="display: {{ old('shooting_area_option') == 'outside_ahsa' ? 'block' : 'none' }};">
                            <label for="outside_ahs_city" class="form-label">الرجاء اختيار المدينة (خارج الأحساء) <span id="outside_ahs_city_required_star" class="text-danger" style="display:none;">*</span></label>
                            <select class="form-select @error('outside_ahs_city') is-invalid @enderror" name="outside_ahs_city" id="outside_ahs_city">
                                <option value="">-- اختر المدينة --</option>
                                @foreach($outsideAhsaCities as $cityValue => $cityLabel)
                                    <option value="{{ $cityValue }}" {{ old('outside_ahs_city') == $cityValue ? 'selected' : '' }}>{{ $cityLabel }}</option>
                                @endforeach
                            </select>
                            @error('outside_ahs_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    {{-- END: Location Section --}}
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="event_location" class="form-label">مكان المناسبة (اسم القاعة، الحي، إلخ)</label>
                            <input type="text" class="form-control @error('event_location') is-invalid @enderror" id="event_location" name="event_location" value="{{ old('event_location') }}">
                            @error('event_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="groom_name_en" class="form-label">اسم العريس (إنجليزي - اختياري)</label>
                            <input type="text" class="form-control @error('groom_name_en') is-invalid @enderror" id="groom_name_en" name="groom_name_en" value="{{ old('groom_name_en') }}">
                            @error('groom_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bride_name_en" class="form-label">اسم العروس (إنجليزي - اختياري)</label>
                            <input type="text" class="form-control @error('bride_name_en') is-invalid @enderror" id="bride_name_en" name="bride_name_en" value="{{ old('bride_name_en') }}">
                            @error('bride_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="customer_notes" class="form-label">ملاحظات إضافية (اختياري)</label>
                        <textarea class="form-control @error('customer_notes') is-invalid @enderror" id="customer_notes" name="customer_notes" rows="3">{{ old('customer_notes') }}</textarea>
                        @error('customer_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Payment Details --}}
                <div class="form-section">
                    <h5 class="card-title mb-3 text-primary"><i class="fas fa-money-bill-wave me-2"></i>3. تفاصيل الدفع</h5>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i>
                        هذا القسم لتسجيل الدفعات اليدوية التي تتم مع المدير. إذا كان هناك مبلغ متبقي، سيتمكن العميل من دفعه لاحقاً عبر تمارا (إذا كانت مفعلة).
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">السعر الأساسي للخدمة:</label>
                            <div id="base_service_price_display" class="form-control-plaintext fw-bold">0.00 ريال</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">رسوم خارج الأحساء:</label>
                            <div id="outside_fee_display" class="form-control-plaintext fw-bold">0.00 ريال</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">إجمالي مبلغ الحجز المطلوب:</label>
                            <div id="total_booking_amount_display" class="form-control-plaintext fw-bold total-booking-amount-display">0.00 ريال</div>
                            {{-- حقل مخفي لإرسال الإجمالي المحسوب --}}
                            <input type="hidden" name="booking_total_amount_from_form" id="booking_total_amount_from_form_hidden" value="{{ old('booking_total_amount_from_form', 0) }}">
                            @error('booking_total_amount_from_form') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="amount_paid_manually" class="form-label">المبلغ المدفوع يدوياً للمدير حالياً</label>
                            <input type="number" step="0.01" class="form-control @error('amount_paid_manually') is-invalid @enderror" id="amount_paid_manually" name="amount_paid_manually" value="{{ old('amount_paid_manually', 0) }}" placeholder="مثال: 550 أو 0">
                            <div class="form-text">المبلغ الذي دفعه العميل بالفعل (أو 0 إذا لم يدفع شيء بعد للمدير).</div>
                            @error('amount_paid_manually') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_option_for_customer" class="form-label">خيار الدفع المتوقع من العميل (للمتبقي عبر تمارا) <span class="text-danger">*</span></label>
                            <select class="form-select @error('payment_option_for_customer') is-invalid @enderror" id="payment_option_for_customer" name="payment_option_for_customer" required>
                                <option value="full" {{ old('payment_option_for_customer') == 'full' ? 'selected' : '' }}>دفع المبلغ كاملاً عبر تمارا</option>
                                <option value="down_payment" {{ old('payment_option_for_customer', 'down_payment') == 'down_payment' ? 'selected' : '' }}>دفع عربون (٥٠% من الإجمالي) عبر تمارا</option>
                            </select>
                            <div class="form-text">يحدد هذا الخيار ما إذا كان العميل سيدفع عربونًا أو المبلغ كاملاً للمبلغ المتبقي عبر تمارا.</div>
                            @error('payment_option_for_customer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                     <div class="mt-2">
                         <strong>المبلغ المتبقي المتوقع دفعه من العميل عبر تمارا: <span id="remaining_for_customer_display" class="fw-bold">0.00 ريال</span></strong>
                     </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary me-2">إلغاء</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-1"></i> إنشاء الحجز والعميل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/ar.js"></script> {{-- For Arabic localization --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    flatpickr(".flatpickr-date", {
        altInput: true,
        altFormat: "j F, Y",
        dateFormat: "Y-m-d",
        locale: "ar",
        // allowInput: true, // اسمح بالإدخال اليدوي إذا أردت
        // minDate: "today" // يمكنك تفعيله إذا أردت منع الحجز في الماضي
    });

    flatpickr(".flatpickr-time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        altInput: true,
        altFormat: "h:i K",
        minuteIncrement: 15,
        locale: "ar"
    });

    const serviceSelect = document.getElementById('service_id');
    const baseServicePriceDisplay = document.getElementById('base_service_price_display');
    const outsideFeeDisplay = document.getElementById('outside_fee_display');
    const totalBookingAmountDisplay = document.getElementById('total_booking_amount_display');
    const bookingTotalAmountHiddenInput = document.getElementById('booking_total_amount_from_form_hidden');
    
    const shootingAreaOptions = document.querySelectorAll('input[name="shooting_area_option"]');
    const outsideAhsaCityGroup = document.getElementById('outside_ahs_city_group');
    const outsideAhsaCitySelect = document.getElementById('outside_ahs_city');
    const outsideAhsaCityRequiredStar = document.getElementById('outside_ahs_city_required_star');

    const amountPaidManuallyInput = document.getElementById('amount_paid_manually');
    const paymentOptionForCustomerSelect = document.getElementById('payment_option_for_customer');
    const remainingForCustomerDisplay = document.getElementById('remaining_for_customer_display');


    const outsideAhsaFeeValue = parseFloat('{{ $outsideAhsaFee ?? 0 }}');

    function toArabicLocal(number, decimals = 2) {
        if (isNaN(parseFloat(number))) return '0.00';
        const options = {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        };
        // لا نستخدم toLocaleString('ar-SA') هنا لأننا نريد الأرقام الهندية/العربية لاحقا
        return parseFloat(number).toFixed(decimals); // يعيد رقمًا إنجليزيًا مع فاصلة عشرية
    }
    function toArabicDigitsForDisplay(str) {
        if (str === null || str === undefined) return '';
        const western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.'];
        const eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٫'];
        let numStr = String(str);
        western.forEach((digit, index) => {
            numStr = numStr.replace(new RegExp(digit.replace('.', '\\.'), "g"), eastern[index]);
        });
        return numStr;
    }


    function calculateAndDisplayTotals() {
        let selectedServicePrice = 0;
        const selectedServiceOption = serviceSelect.options[serviceSelect.selectedIndex];
        if (selectedServiceOption && selectedServiceOption.dataset.price) {
            selectedServicePrice = parseFloat(selectedServiceOption.dataset.price);
        }
        if(isNaN(selectedServicePrice)) selectedServicePrice = 0;
        if(baseServicePriceDisplay) baseServicePriceDisplay.textContent = `${toArabicDigitsForDisplay(toArabicLocal(selectedServicePrice))} ريال`;
        
        // تحديث قيمة الحقل المخفي لسعر الخدمة الأساسي
        const servicePriceHiddenInput = document.getElementById('service_price_from_form');
        if(servicePriceHiddenInput) servicePriceHiddenInput.value = selectedServicePrice.toFixed(2);


        let currentOutsideFee = 0;
        const outsideAhsaSelected = document.querySelector('input[name="shooting_area_option"]:checked')?.value === 'outside_ahsa';
        if (outsideAhsaSelected) {
            currentOutsideFee = outsideAhsaFeeValue;
        }
        if(outsideFeeDisplay) outsideFeeDisplay.textContent = `${toArabicDigitsForDisplay(toArabicLocal(currentOutsideFee))} ريال`;

        const finalTotalInvoiceAmount = selectedServicePrice + currentOutsideFee;
        if(totalBookingAmountDisplay) totalBookingAmountDisplay.textContent = `${toArabicDigitsForDisplay(toArabicLocal(finalTotalInvoiceAmount))} ريال`;
        if(bookingTotalAmountHiddenInput) bookingTotalAmountHiddenInput.value = finalTotalInvoiceAmount.toFixed(2); // إرسال الإجمالي للخادم

        // تحديث المبلغ المتبقي المتوقع من العميل
        const amountPaidManuallyVal = parseFloat(amountPaidManuallyInput.value) || 0;
        let remainingForCustomer = 0;
        const customerPaymentOption = paymentOptionForCustomerSelect.value;

        if (customerPaymentOption === 'down_payment') {
            const downPaymentTotal = Math.round(finalTotalInvoiceAmount * 50) / 100; // 50% من الإجمالي
            remainingForCustomer = Math.max(0, downPaymentTotal - amountPaidManuallyVal);
        } else { // full payment
            remainingForCustomer = Math.max(0, finalTotalInvoiceAmount - amountPaidManuallyVal);
        }
        if(remainingForCustomerDisplay) remainingForCustomerDisplay.textContent = `${toArabicDigitsForDisplay(toArabicLocal(remainingForCustomer))} ريال`;

    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', calculateAndDisplayTotals);
        // Trigger change on load if a service is pre-selected (e.g., from old input)
        if(serviceSelect.value) {
            calculateAndDisplayTotals();
        }
    }

    shootingAreaOptions.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'outside_ahsa') {
                if(outsideAhsaCityGroup) outsideAhsaCityGroup.style.display = 'block';
                if(outsideAhsaCitySelect) outsideAhsaCitySelect.required = true;
                if(outsideAhsaCityRequiredStar) outsideAhsaCityRequiredStar.style.display = 'inline';
            } else {
                if(outsideAhsaCityGroup) outsideAhsaCityGroup.style.display = 'none';
                if(outsideAhsaCitySelect) {
                     outsideAhsaCitySelect.required = false;
                     outsideAhsaCitySelect.value = ''; // إفراغ القيمة عند الإخفاء
                }
                if(outsideAhsaCityRequiredStar) outsideAhsaCityRequiredStar.style.display = 'none';
            }
            calculateAndDisplayTotals(); // إعادة حساب الإجمالي عند تغيير المنطقة
        });
    });
    
    // مستمعات لتحديث المبلغ المتبقي عند تغيير المبلغ المدفوع يدويًا أو خيار الدفع للعميل
    if(amountPaidManuallyInput) amountPaidManuallyInput.addEventListener('input', calculateAndDisplayTotals);
    if(paymentOptionForCustomerSelect) paymentOptionForCustomerSelect.addEventListener('change', calculateAndDisplayTotals);


    // استدعاء لحساب الإجمالي عند تحميل الصفحة (للتعامل مع old input)
    calculateAndDisplayTotals();
    // تفعيل إظهار/إخفاء قائمة المدن بناءً على القيمة القديمة
    const initialShootingArea = document.querySelector('input[name="shooting_area_option"]:checked')?.value;
    if (initialShootingArea === 'outside_ahsa') {
        if(outsideAhsaCityGroup) outsideAhsaCityGroup.style.display = 'block';
        if(outsideAhsaCitySelect) outsideAhsaCitySelect.required = true;
        if(outsideAhsaCityRequiredStar) outsideAhsaCityRequiredStar.style.display = 'inline';
    }

});
</script>
@endpush
