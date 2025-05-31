@extends('layouts.admin')

@section('title', "تفاصيل الحجز #" . $booking->id)

@php
    // يتم تمرير $statuses (ترجمات حالات الحجز) و $paymentConfirmationOptions من BookingController@show
    // جلب ترجمات حالات الفاتورة
    if (method_exists(\App\Models\Invoice::class, 'getStatusesWithOptions')) {
        $invoiceStatusTranslationsAdminShow = \App\Models\Invoice::getStatusesWithOptions();
    } elseif (method_exists(\App\Models\Invoice::class, 'statuses')) { // كـ fallback
        $invoiceStatusTranslationsAdminShow = \App\Models\Invoice::statuses();
    } else {
        $invoiceStatusTranslationsAdminShow = []; 
    }

    if (!function_exists('getInvoiceStatusTranslationAdminBookingShow')) { 
        function getInvoiceStatusTranslationAdminBookingShow($status, $translations) { 
            return $translations[$status] ?? Illuminate\Support\Str::title(str_replace('_', ' ', $status)); 
        } 
    }
    // دالة toArabicDigits يفترض أنها معرفة كـ helper عام أو في AppServiceProvider
    if (!function_exists('toArabicDigits')) {
        function toArabicDigits($number) {
            if (is_null($number)) return '';
            return str_replace(range(0, 9), ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], (string)$number);
        }
    }
@endphp

@section('content')

    <div class="row g-4">
        {{-- عامود المعلومات الرئيسي --}}
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-receipt me-2"></i>تفاصيل الحجز #{{ toArabicDigits($booking->id) }}</h6>
                    <span class="status-pill {{ $booking->status_badge_class }} px-3 py-1">
                        {{ $booking->status_label }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 dl-row">
                        <dt class="col-sm-4"><i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>تاريخ ووقت الحجز:</dt>
                        <dd class="col-sm-8">{{ $booking->booking_datetime ? toArabicDigits($booking->booking_datetime->translatedFormat('l, d F Y - h:i A')) : '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>تاريخ الطلب:</dt>
                        <dd class="col-sm-8">{{ $booking->created_at ? toArabicDigits($booking->created_at->translatedFormat('d F Y, h:i A')) : '-' }}</dd>
                        
                        @if($booking->cancellation_reason)
                        <hr class="my-3">
                        <dt class="col-sm-4 text-danger"><i class="fas fa-info-circle fa-fw me-1"></i>سبب الإلغاء:</dt>
                        <dd class="col-sm-8 text-danger">{{ $booking->cancellation_reason }}</dd>
                        @endif

                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-user fa-fw me-1 text-muted"></i>اسم العميل:</dt>
                        <dd class="col-sm-8"><a href="{{ route('admin.customers.show', $booking->user_id) }}">{{ $booking->user?->name ?? 'غير متوفر' }}</a></dd>
                        <dt class="col-sm-4"><i class="fas fa-mobile-alt fa-fw me-1 text-muted"></i>رقم الجوال:</dt>
                        <dd class="col-sm-8" dir="ltr" style="text-align: right;">{{ toArabicDigits($booking->user?->mobile_number ?? 'غير متوفر') }}</dd>
                         <dt class="col-sm-4"><i class="fas fa-envelope fa-fw me-1 text-muted"></i>البريد الإلكتروني:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->email ?? 'غير متوفر' }}</dd>
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-concierge-bell fa-fw me-1 text-muted"></i>الخدمة المطلوبة:</dt>
                        <dd class="col-sm-8">{{ $booking->service?->name_ar ?? 'غير متوفر' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-clock fa-fw me-1 text-muted"></i>مدة الخدمة:</dt>
                        <dd class="col-sm-8">{{ toArabicDigits($booking->service?->duration_hours ?? 'N/A') }} ساعات</dd>
                        <dt class="col-sm-4"><i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>سعر الخدمة الأصلي:</dt>
                        <dd class="col-sm-8">{{ toArabicDigits(number_format($booking->service?->price_sar ?? 0, 2)) }} ريال</dd>
                        @if($booking->service?->included_items_ar)
                            <dt class="col-sm-4"><i class="fas fa-info-circle fa-fw me-1 text-muted"></i>تشمل الخدمة:</dt>
                            <dd class="col-sm-8">{!! nl2br(e($booking->service->included_items_ar)) !!}</dd>
                        @endif
                        
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-map-marked-alt fa-fw me-1 text-muted"></i>منطقة التصوير:</dt>
                        <dd class="col-sm-8">{{ $booking->shooting_area_label }}</dd>
                        @if($booking->shooting_area === 'outside_ahsa' && $booking->outside_location_city)
                            <dt class="col-sm-4"><i class="fas fa-city fa-fw me-1 text-muted"></i>المدينة (خارج الأحساء):</dt>
                            <dd class="col-sm-8">{{ $booking->outside_location_city }}</dd>
                        @endif
                        @if($booking->outside_location_fee_applied > 0)
                            <dt class="col-sm-4"><i class="fas fa-coins fa-fw me-1 text-muted"></i>رسوم خارج المنطقة:</dt>
                            <dd class="col-sm-8">{{ toArabicDigits(number_format($booking->outside_location_fee_applied, 2)) }} ريال</dd>
                        @endif

                        <dt class="col-sm-4"><i class="fas fa-map-marker-alt fa-fw me-1 text-muted"></i>مكان الحفل (العنوان):</dt>
                        <dd class="col-sm-8">{{ $booking->event_location ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-user-tie fa-fw me-1 text-muted"></i>اسم العريس (إنجليزي):</dt>
                        <dd class="col-sm-8">{{ $booking->groom_name_en ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-female fa-fw me-1 text-muted"></i>اسم العروس (إنجليزي):</dt>
                        <dd class="col-sm-8">{{ $booking->bride_name_en ?: '-' }}</dd>
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="far fa-sticky-note fa-fw me-1 text-muted"></i>ملاحظات العميل:</dt>
                        <dd class="col-sm-8">{{ $booking->customer_notes ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-check-square fa-fw me-1 text-muted"></i>الموافقة على السياسة:</dt>
                        <dd class="col-sm-8">{{ $booking->agreed_to_policy ? 'نعم' : 'لا' }}</dd>
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-percent fa-fw me-1 text-muted"></i>كود الخصم المستخدم:</dt>
                        <dd class="col-sm-8">{{ $booking->discountCode?->code ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-file-invoice-dollar fa-fw me-1 text-muted"></i>الفاتورة:</dt>
                        <dd class="col-sm-8">
                            @if($invoice = $booking->invoice)
                                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="fw-bold">رقم: {{ $invoice->invoice_number }}</a> |
                                المبلغ الإجمالي: {{ toArabicDigits(number_format($invoice->amount, 2)) }} {{ $invoice->currency }} |
                                الحالة: <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }} {{ $invoice->status_badge_class ? '' : 'text-white' }}">{{ getInvoiceStatusTranslationAdminBookingShow($invoice->status, $invoiceStatusTranslationsAdminShow) }}</span> |
                                الدفع: {{ $invoice->payment_method_label ?? ($invoice->payment_method == 'tamara' ? 'تمارا' : ($invoice->payment_method == 'bank_transfer' ? 'تحويل بنكي' : ($invoice->payment_method == 'manual_confirmation_due_to_no_gateway' ? 'بانتظار التأكيد اليدوي' : ($invoice->payment_method ?: 'غير محدد')))) }}
                                @if($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID)
                                     | المدفوع: {{ toArabicDigits(number_format($invoice->total_paid_amount ?? 0, 2)) }} | المتبقي: {{ toArabicDigits(number_format($invoice->remaining_amount ?? 0, 2)) }} {{ $invoice->currency }}
                                @endif
                            @else
                                لم يتم إنشاء فاتورة بعد
                            @endif
                        </dd>
                    </dl>
                </div>
                 <div class="card-footer bg-white text-muted">
                     <a href="{{ route('admin.bookings.index') }}" class="btn btn-secondary btn-sm">
                         <i class="fas fa-arrow-right fa-sm me-1"></i> العودة للقائمة
                     </a>
                 </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>تغيير حالة الحجز</h6>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-11">
                            @if(session('update_status_success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('update_status_success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if(session('update_status_error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('update_status_error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif
                             @if (!$errors->updateStatus && $errors->any() && !$errors->hasBag('updatePayment'))
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('admin.bookings.updateStatus', $booking->id) }}" method="POST" id="updateBookingStatusForm">
                                @csrf
                                @method('PATCH')
                                <div class="mb-3">
                                    <label for="status" class="form-label fw-bold">الحالة الجديدة:</label>
                                    <select name="status" id="status" class="form-select @error('status', 'updateStatus') is-invalid @enderror" required>
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $booking->status) == $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status', 'updateStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3" id="cancellationReasonSection" style="display: none;">
                                    <label for="cancellation_reason" class="form-label fw-bold">سبب الإلغاء:<span class="text-danger">*</span></label>
                                    <textarea name="cancellation_reason" id="cancellation_reason" class="form-control @error('cancellation_reason', 'updateStatus') is-invalid @enderror" rows="3">{{ old('cancellation_reason', $booking->cancellation_reason) }}</textarea>
                                    @error('cancellation_reason', 'updateStatus')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div id="payment_confirmation_options" class="mb-3 border p-3 rounded bg-light" style="display: none;">
                                    <label class="form-label fw-bold">تأكيد استلام الدفعة:</label>
                                    @isset($paymentConfirmationOptions)
                                        @php
                                            $currentInvoice = $booking->invoice;
                                            $isPartiallyPaid = $currentInvoice?->status === \App\Models\Invoice::STATUS_PARTIALLY_PAID;
                                            // إذا كانت الفاتورة مدفوعة جزئياً، المبلغ المتبقي هو المهم للعرض
                                            // وإذا لم تكن مدفوعة جزئياً (مثلاً غير مدفوعة)، فمبلغ العربون هو المهم أو المبلغ الكامل
                                            $amountForFullConfirmation = $isPartiallyPaid ? ($currentInvoice->remaining_amount ?? 0) : ($currentInvoice?->amount ?? 0);
                                            $downPaymentAmountForBooking = $booking->down_payment_amount ?? ($currentInvoice ? round($currentInvoice->amount / 2, 2) : 0);
                                            
                                            $currency = $currentInvoice?->currency ?: 'SAR';
                                            $currentBookingStatus = $booking->status;
                                            $confirmedBookingStatusValueConstant = \App\Models\Booking::STATUS_CONFIRMED;
                                            $depositValueConstant = 'deposit';
                                            $fullValueConstant = 'full';
                                        @endphp

                                        @foreach($paymentConfirmationOptions as $value => $label)
                                            {{-- لا تعرض خيار العربون إذا كان الحجز مؤكدًا بالفعل أو الفاتورة مدفوعة جزئيًا --}}
                                            @if($value === $depositValueConstant && ($isPartiallyPaid || $currentBookingStatus === $confirmedBookingStatusValueConstant))
                                                @continue
                                            @endif

                                            @php
                                                $currentDisplayLabel = $label;
                                                if ($value === $fullValueConstant) {
                                                    $currentDisplayLabel = "تأكيد استلام المبلغ الكامل/المتبقي (" . toArabicDigits(number_format($amountForFullConfirmation, 2)) . " $currency)";
                                                } elseif ($value === $depositValueConstant) {
                                                    $currentDisplayLabel = "تأكيد استلام العربون فقط (" . toArabicDigits(number_format($downPaymentAmountForBooking, 2)) . " $currency)";
                                                }
                                            @endphp
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_confirmation_type" id="confirm_{{ $value }}" value="{{ $value }}">
                                                <label class="form-check-label" for="confirm_{{ $value }}">
                                                    {{ $currentDisplayLabel }}
                                                </label>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="text-danger small mb-0">خطأ: لم يتم تمرير خيارات تأكيد الدفع من المتحكم.</p>
                                    @endisset
                                    @error('payment_confirmation_type', 'updateStatus') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <input type="hidden" name="deposit_amount" id="modal_deposit_amount">

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-sync-alt fa-sm me-1"></i> تحديث الحالة
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if($invoice = $booking->invoice)
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-history me-2"></i>سجل دفعات الفاتورة #{{ $invoice->invoice_number }}</h6>
                    </div>
                    <div class="card-body">
                        @php $invoice->loadMissing('payments'); @endphp
                        @if($invoice->payments->isNotEmpty())
                            <ul class="list-group list-group-flush payment-log-list">
                                @foreach($invoice->payments->sortByDesc('created_at') as $payment)
                                    <li class="list-group-item px-0 py-2">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1 fw-bold small">
                                                @if($payment->payment_gateway == 'tamara') <i class="fas fa-credit-card text-primary me-1"></i> تمارا
                                                @elseif($payment->payment_gateway == 'bank_transfer') <i class="fas fa-university text-info me-1"></i> تحويل بنكي
                                                @elseif($payment->payment_gateway == 'manual_admin_deposit') <i class="fas fa-hand-holding-usd text-warning me-1"></i> عربون يدوي
                                                @elseif($payment->payment_gateway == 'manual_admin_full') <i class="fas fa-user-check text-success me-1"></i> تأكيد دفع كامل يدوي
                                                @else <i class="fas fa-dollar-sign text-muted me-1"></i> {{ $payment->payment_gateway_label ?? ($payment->payment_gateway ?: 'غير محدد') }}
                                                @endif
                                            </h6>
                                            <span class="badge bg-success-soft text-success rounded-pill">{{ toArabicDigits(number_format($payment->amount, 2)) }} {{ $payment->currency }}</span>
                                        </div>
                                        <small class="text-muted d-block">
                                            <i class="far fa-clock"></i> {{ toArabicDigits($payment->created_at->translatedFormat('d M Y, H:i')) }}
                                            @if($payment->transaction_id) | <i class="fas fa-receipt"></i> {{ Str::limit($payment->transaction_id, 20) }} @endif
                                            @if($payment->payment_details && (isset($payment->payment_details['confirmed_by_admin_id']) || isset($payment->payment_details['admin_name'])))
                                                | <i class="fas fa-user-edit"></i> 
                                                بواسطة: {{ $payment->payment_details['admin_name'] ?? (\App\Models\User::find($payment->payment_details['confirmed_by_admin_id'] ?? null)?->name ?? 'مدير') }}
                                            @endif
                                        </small>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted mb-0">لا توجد سجلات دفع لهذه الفاتورة.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="depositAmountModal" tabindex="-1" aria-labelledby="depositAmountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="depositAmountModalLabel">تسجيل مبلغ العربون</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <p>لتأكيد الحجز مع عربون، يرجى إدخال مبلغ العربون الذي تم استلامه:</p>
                    <div class="mb-3">
                        <label for="deposit_amount_input" class="form-label">مبلغ العربون المدفوع ({{ $booking->invoice?->currency ?: 'SAR' }})</label>
                        <input type="text" inputmode="decimal" class="form-control" id="deposit_amount_input" required placeholder="أدخل مبلغ العربون">
                        <div class="invalid-feedback" id="deposit_amount_error"></div>
                    </div>
                    <p class="text-muted small">سيتم تحديث حالة الحجز إلى "مؤكد" وحالة الفاتورة إلى "مدفوعة جزئياً" وإنشاء سجل دفع بهذا المبلغ.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="submitDepositModal">تأكيد العربون وتحديث الحالة</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('status');
    const paymentConfirmationDiv = document.getElementById('payment_confirmation_options');
    const cancellationReasonSection = document.getElementById('cancellationReasonSection');
    const cancellationReasonTextarea = document.getElementById('cancellation_reason');
    
    const depositModalElement = document.getElementById('depositAmountModal');
    const depositModalInstance = (typeof bootstrap !== 'undefined' && depositModalElement) ? new bootstrap.Modal(depositModalElement) : null;
    
    const depositAmountInput = document.getElementById('deposit_amount_input');
    const submitDepositModalBtn = document.getElementById('submitDepositModal');
    const modalDepositAmountHiddenInput = document.getElementById('modal_deposit_amount');
    const depositAmountError = document.getElementById('deposit_amount_error');
    const updateStatusForm = document.getElementById('updateBookingStatusForm');

    const cancellationStatusesRequiringReasonJS = @json(\App\Models\Booking::getCancellationStatusesRequiringReason());
    const confirmedBookingStatusValueJS = '{{ \App\Models\Booking::STATUS_CONFIRMED }}';
    const depositPaymentValueJS = 'deposit'; // القيمة المستخدمة في الـ radio button لخيار العربون
    const fullPaymentValueJS = 'full'; // القيمة المستخدمة في الـ radio button لخيار الدفع الكامل

    function toggleCancellationReasonField() {
        if (!statusSelect || !cancellationReasonSection || !cancellationReasonTextarea) return;
        const selectedStatus = statusSelect.value;
        if (cancellationStatusesRequiringReasonJS.includes(selectedStatus)) {
            cancellationReasonSection.style.display = 'block';
            cancellationReasonTextarea.setAttribute('required', 'required');
        } else {
            cancellationReasonSection.style.display = 'none';
            cancellationReasonTextarea.removeAttribute('required');
             cancellationReasonTextarea.value = ''; // إفراغ الحقل عند الإخفاء
        }
    }

    function togglePaymentConfirmationOptions() {
        if (!statusSelect || !paymentConfirmationDiv) return;
        const showOptions = statusSelect.value === confirmedBookingStatusValueJS;
        paymentConfirmationDiv.style.display = showOptions ? 'block' : 'none';

        if(showOptions) {
            const invoiceStatusJS = '{{ $booking->invoice?->status }}';
            const isInvoicePartiallyPaidJS = invoiceStatusJS === '{{ \App\Models\Invoice::STATUS_PARTIALLY_PAID }}';
            const currentBookingStatusJS = '{{ $booking->status }}';
            
            const radios = paymentConfirmationDiv.querySelectorAll('input[name="payment_confirmation_type"]');
            let firstVisibleRadioToSelect = null;
            
            radios.forEach(radio => {
                const radioContainer = radio.closest('.form-check');
                // إخفاء خيار العربون إذا كان الحجز مؤكدًا بالفعل أو الفاتورة مدفوعة جزئيًا
                if (radio.value === depositPaymentValueJS && (isInvoicePartiallyPaidJS || currentBookingStatusJS === confirmedBookingStatusValueJS)) {
                    radioContainer.style.display = 'none';
                    if (radio.checked) radio.checked = false; 
                } else {
                    radioContainer.style.display = 'block';
                    if (!firstVisibleRadioToSelect && !paymentConfirmationDiv.querySelector('input[name="payment_confirmation_type"]:checked')) {
                        firstVisibleRadioToSelect = radio;
                    }
                }
            });
            
            // تحديد خيار افتراضي إذا لم يكن هناك شيء محدد وكان القسم ظاهرًا
            if (!paymentConfirmationDiv.querySelector('input[name="payment_confirmation_type"]:checked')) {
                if (isInvoicePartiallyPaidJS) { // إذا كانت مدفوعة جزئيا، الافتراضي هو الدفع الكامل للمتبقي
                    const fullPayRadio = paymentConfirmationDiv.querySelector('input[value="' + fullPaymentValueJS + '"]');
                    if (fullPayRadio && fullPayRadio.closest('.form-check').style.display !== 'none') fullPayRadio.checked = true;
                } else if (firstVisibleRadioToSelect) { // وإلا، حدد أول خيار ظاهر
                    firstVisibleRadioToSelect.checked = true;
                }
            }
        }
    }

    if (statusSelect) {
        togglePaymentConfirmationOptions(); // استدعاء عند التحميل
        statusSelect.addEventListener('change', togglePaymentConfirmationOptions);
        toggleCancellationReasonField(); // استدعاء عند التحميل
        statusSelect.addEventListener('change', toggleCancellationReasonField);
    }

    if(updateStatusForm && statusSelect && depositModalElement) { // استخدام depositModalElement للتحقق الأولي
        updateStatusForm.addEventListener('submit', function (event) {
            const selectedStatus = statusSelect.value;
            const paymentOptionsDiv = document.getElementById('payment_confirmation_options');
            let selectedPaymentTypeRadio = null;
            let selectedPaymentType = null;

            if (paymentOptionsDiv && paymentOptionsDiv.style.display === 'block') {
                selectedPaymentTypeRadio = paymentOptionsDiv.querySelector('input[name="payment_confirmation_type"]:checked');
                if (selectedPaymentTypeRadio) {
                    selectedPaymentType = selectedPaymentTypeRadio.value;
                } else {
                    // إذا كان القسم ظاهرًا ولكن لا يوجد خيار محدد
                    // هذا لا يجب أن يحدث إذا كان منطق التحديد الافتراضي يعمل
                    console.warn('Payment confirmation options visible, but NO payment type is selected.');
                    // event.preventDefault(); // منع الإرسال في هذه الحالة
                    // alert('يرجى تحديد نوع تأكيد الدفع.');
                    // return; 
                }
            }
            
            const depositRadioElement = document.getElementById('confirm_deposit');
            const depositRadioContainer = depositRadioElement ? depositRadioElement.closest('.form-check') : null;
            
            console.debug("Form Submit Check:", {
                selectedStatus,
                confirmedBookingStatusValueJS,
                selectedPaymentType,
                depositPaymentValueJS,
                depositRadioContainerVisible: depositRadioContainer ? depositRadioContainer.style.display !== 'none' : false
            });

            if (selectedStatus === confirmedBookingStatusValueJS && 
                selectedPaymentType === depositPaymentValueJS && 
                depositRadioContainer && 
                depositRadioContainer.style.display !== 'none') {
                
                event.preventDefault(); 
                
                if(depositAmountInput && depositAmountError && depositModalInstance) {
                    depositAmountInput.classList.remove('is-invalid');
                    if(depositAmountError) depositAmountError.textContent = '';
                    depositAmountInput.value = ''; 
                    
                    const downPaymentForBooking = parseFloat('{{ $booking->down_payment_amount ?? ($booking->invoice ? round($booking->invoice->amount / 2, 2) : 0) }}');
                    depositAmountInput.placeholder = `مثلاً: ${downPaymentForBooking > 0 ? downPaymentForBooking.toFixed(2) : 'أدخل مبلغ العربون'}`;
                    
                    if(modalDepositAmountHiddenInput) modalDepositAmountHiddenInput.value = '';
                    depositModalInstance.show();
                } else {
                    console.error('Modal elements for deposit amount are not found or depositModalInstance is null!');
                    alert('حدث خطأ في تهيئة نافذة إدخال العربون. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
                }
            } else {
                if(modalDepositAmountHiddenInput) modalDepositAmountHiddenInput.value = ''; // مسح قيمة العربون إذا لم يكن مطلوباً
                console.debug('Proceeding with normal form submission. Modal not required.');
            }
        });
    }

    if (submitDepositModalBtn && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError && updateStatusForm) {
        submitDepositModalBtn.addEventListener('click', function() {
            let depositAmountStr = depositAmountInput.value.trim().replace(',', '.');
            let depositAmount = parseFloat(depositAmountStr);
            const maxAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}'); 

            if(depositAmountError) depositAmountError.textContent = '';
            depositAmountInput.classList.remove('is-invalid');

            if (isNaN(depositAmount) || depositAmount <= 0) {
                depositAmountInput.classList.add('is-invalid');
                if(depositAmountError) depositAmountError.textContent = 'الرجاء إدخال مبلغ صحيح للعربون (أكبر من صفر).';
                return;
            }
            if (maxAmount > 0 && depositAmount >= maxAmount) {
                depositAmountInput.classList.add('is-invalid');
                if(depositAmountError) depositAmountError.textContent = 'مبلغ العربون لا يمكن أن يكون مساوياً أو أكبر من المبلغ الإجمالي للفاتورة.';
                return;
            }

            modalDepositAmountHiddenInput.value = depositAmount;
            
            submitDepositModalBtn.disabled = true;
            submitDepositModalBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري...';
            
            // لا حاجة لتحديد الراديو هنا مرة أخرى، فقط أرسل الفورم
            updateStatusForm.submit();
        });
    }
    
    if(depositModalElement && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError){
       depositModalElement.addEventListener('hidden.bs.modal', function () {
           depositAmountInput.classList.remove('is-invalid');
           if(depositAmountError) depositAmountError.textContent = '';
           if(modalDepositAmountHiddenInput) modalDepositAmountHiddenInput.value = ''; // إفراغ عند إغلاق المودال
           // إعادة تفعيل زر المودال إذا تم تعطيله
           if(submitDepositModalBtn) {
               submitDepositModalBtn.disabled = false;
               submitDepositModalBtn.innerHTML = 'تأكيد العربون وتحديث الحالة';
           }
       });
    }
});
</script>
@endpush

@push('styles')
<style>
    .dl-row dt, .dl-row dd { margin-bottom: 0.6rem; font-size: 0.95em; }
    .dl-row dt { font-weight: 600; color: #525f7f; }
    .dl-row dt i.fa-fw { margin-left: 5px; color: #adb5bd; }
    .list-group-flush .list-group-item { background-color: transparent; border: none; padding-left: 0; padding-right: 0; }
    .payment-log-list i { width: 1.2em; text-align: center; margin-left: 3px; }
    .payment-log-list small { font-size: 0.8em; }
    .status-pill { font-size: 0.8rem; font-weight: 600; border-radius: 50rem; vertical-align: middle; }
    .bg-success-soft { background-color: rgba(40, 167, 69, 0.1); } .text-success { color: #28a745 !important; }
    .bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); } .text-warning { color: #ffc107 !important; }
    .bg-info-soft { background-color: rgba(23, 162, 184, 0.1); } .text-info { color: #17a2b8 !important; }
    .bg-primary-soft { background-color: rgba(0, 123, 255, 0.1); } 
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); } .text-secondary { color: #6c757d !important; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); } 
</style>
@endpush
