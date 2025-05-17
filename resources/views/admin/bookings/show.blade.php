@extends('layouts.admin')

@section('title', "تفاصيل الحجز #" . $booking->id)

@php
    // استخدام الدالة المساعدة من الموديل مباشرة
    $bookingStatusOptions = \App\Models\Booking::getStatusesWithOptions();
    $invoiceStatusTranslations = \App\Models\Invoice::statuses(); // افترض أن لديك دالة مشابهة في موديل Invoice
    if (!function_exists('getInvoiceStatusTranslation')) { function getInvoiceStatusTranslation($status, $translations) { return $translations[$status] ?? Str::title(str_replace('_', ' ', $status)); } }
@endphp

@section('content')

    <div class="row g-4">
        {{-- عامود المعلومات الرئيسي --}}
        <div class="col-lg-7">
            {{-- بطاقة تفاصيل الحجز --}}
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-receipt me-2"></i>تفاصيل الحجز #{{ $booking->id }}</h6>
                    <span class="status-pill {{ $booking->status_badge_class }} px-3 py-1">
                        {{ $booking->status_label }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 dl-row">
                        {{-- ... (بقية تفاصيل الحجز كما هي في ملفك) ... --}}
                        <dt class="col-sm-4"><i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>تاريخ ووقت الحجز:</dt>
                        <dd class="col-sm-8">{{ $booking->booking_datetime ? $booking->booking_datetime->translatedFormat('l, d F Y - h:i A') : '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>تاريخ الطلب:</dt>
                        <dd class="col-sm-8">{{ $booking->created_at ? $booking->created_at->translatedFormat('d F Y, h:i A') : '-' }}</dd>
                        
                        {{-- *** بداية: عرض سبب الإلغاء إذا كان موجودًا *** --}}
                        @if($booking->cancellation_reason)
                        <hr class="my-3">
                        <dt class="col-sm-4 text-danger"><i class="fas fa-info-circle fa-fw me-1"></i>سبب الإلغاء:</dt>
                        <dd class="col-sm-8 text-danger">{{ $booking->cancellation_reason }}</dd>
                        @endif
                        {{-- *** نهاية: عرض سبب الإلغاء *** --}}

                        <hr class="my-3">
                        {{-- ... (بقية تفاصيل الحجز مثل العميل، الخدمة، إلخ) ... --}}
                         <dt class="col-sm-4"><i class="fas fa-user fa-fw me-1 text-muted"></i>اسم العميل:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->name ?? 'غير متوفر' }}</dd>
                        {{-- ... (أكمل بقية الحقول كما كانت) ... --}}
                        <dt class="col-sm-4"><i class="fas fa-file-invoice-dollar fa-fw me-1 text-muted"></i>الفاتورة:</dt>
                        <dd class="col-sm-8">
                            @if($invoice = $booking->invoice)
                                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="fw-bold">رقم: {{ $invoice->invoice_number }}</a> |
                                المبلغ الإجمالي: {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }} |
                                الحالة: <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }} {{ $invoice->status_badge_class ? '' : 'text-white' }}">{{ $invoice->status_label ?? getInvoiceStatusTranslation($invoice->status, $invoiceStatusTranslations) }}</span> |
                                الدفع: {{ $invoice->payment_method_label ?? ($invoice->payment_method == 'tamara' ? 'تمارا' : ($invoice->payment_method == 'bank_transfer' ? 'تحويل بنكي' : 'غير محدد')) }}
                                @if($invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID)
                                     | المدفوع: {{ number_format($invoice->paid_amount ?? 0, 2) }} | المتبقي: {{ number_format($invoice->remaining_amount ?? 0, 2) }} {{ $invoice->currency }}
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

        {{-- عامود تحديث الحالة وسجل الدفعات --}}
        <div class="col-lg-5">
            {{-- تحديث حالة الحجز --}}
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>تغيير حالة الحجز</h6>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-11">
                            {{-- عرض أخطاء التحقق الخاصة بنموذج تحديث الحالة --}}
                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif
                            {{-- عرض أخطاء التحقق العامة (إذا لم تكن مرتبطة بـ updateStatus) --}}
                             @if (!$errors->updateStatus && $errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif


                            <form action="{{ route('admin.bookings.updateStatus', $booking->id) }}" method="POST" id="updateBookingStatusForm">
                                @csrf
                                @method('PATCH') {{-- أو PUT بناءً على تعريف المسار --}}
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label fw-bold">الحالة الجديدة:</label>
                                    <select name="status" id="status" class="form-select @error('status', 'updateStatus') is-invalid @enderror" required>
                                        {{-- $statuses تم تمريرها من المتحكم، يجب أن تكون مصفوفة key => label --}}
                                        @foreach ($statuses as $value => $label) 
                                            <option value="{{ $value }}" @selected(old('status', $booking->status) == $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status', 'updateStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                {{-- *** بداية: حقل سبب الإلغاء (مخفي افتراضيًا) *** --}}
                                <div class="mb-3" id="cancellationReasonSection" style="display: none;">
                                    <label for="cancellation_reason" class="form-label fw-bold">سبب الإلغاء:<span class="text-danger">*</span></label>
                                    <textarea name="cancellation_reason" id="cancellation_reason" class="form-control @error('cancellation_reason', 'updateStatus') is-invalid @enderror" rows="3">{{ old('cancellation_reason', $booking->cancellation_reason) }}</textarea>
                                    @error('cancellation_reason', 'updateStatus')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- *** نهاية: حقل سبب الإلغاء *** --}}


                                {{-- قسم تأكيد الدفع (يبقى كما هو في ملفك الأصلي) --}}
                                <div id="payment_confirmation_options" class="mb-3 border p-3 rounded bg-light" style="display: none;">
                                    {{-- ... (كود قسم تأكيد الدفع كما هو) ... --}}
                                     <label class="form-label fw-bold">تأكيد استلام الدفعة:</label>
                                    @isset($paymentConfirmationOptions)
                                        @php
                                            $currentInvoice = $booking->invoice;
                                            $isPartiallyPaid = $currentInvoice?->status === \App\Models\Invoice::STATUS_PARTIALLY_PAID;
                                            $remainingAmount = $isPartiallyPaid ? ($currentInvoice->remaining_amount ?? 0) : 0;
                                            $currency = $currentInvoice?->currency ?: 'SAR';
                                            $currentBookingStatus = $booking->status;
                                            $confirmedBookingStatusValueConstant = \App\Models\Booking::STATUS_CONFIRMED;
                                            $depositValueConstant = 'deposit';
                                            $fullValueConstant = 'full';
                                        @endphp

                                        @foreach($paymentConfirmationOptions as $value => $label)
                                            @if($value === $depositValueConstant && ($isPartiallyPaid || $currentBookingStatus === $confirmedBookingStatusValueConstant))
                                                @continue
                                            @endif
                                            @php
                                                $currentLabel = $label;
                                                if ($value === $fullValueConstant && $isPartiallyPaid) {
                                                    $currentLabel = "تأكيد استلام المبلغ المتبقي (" . number_format($remainingAmount, 2) . " $currency)";
                                                } elseif ($value === $fullValueConstant && !$isPartiallyPaid && $currentInvoice) {
                                                     $currentLabel = "تأكيد استلام المبلغ الكامل (" . number_format($currentInvoice->amount, 2) . " $currency)";
                                                }
                                            @endphp
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_confirmation_type" id="confirm_{{ $value }}" value="{{ $value }}"
                                                       {{ ($isPartiallyPaid && $value === $fullValueConstant) || (!$isPartiallyPaid && $currentBookingStatus !== $confirmedBookingStatusValueConstant && $loop->first && !($value === $depositValueConstant && ($isPartiallyPaid || $currentBookingStatus === $confirmedBookingStatusValueConstant)) ) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="confirm_{{ $value }}">
                                                    {{ $currentLabel }}
                                                </label>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="text-danger small mb-0">خطأ: لم يتم تمرير خيارات تأكيد الدفع.</p>
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

            {{-- سجل الدفعات الخاص بالفاتورة المرتبطة (يبقى كما هو في ملفك الأصلي) --}}
            @if($invoice = $booking->invoice)
                <div class="card shadow-sm mb-4 border-0">
                    {{-- ... (كود سجل الدفعات كما هو) ... --}}
                </div>
            @endif

        </div>
    </div>

     {{-- Modal لإدخال مبلغ العربون (يبقى كما هو في ملفك الأصلي) --}}
    <div class="modal fade" id="depositAmountModal" tabindex="-1" aria-labelledby="depositAmountModalLabel" aria-hidden="true">
        {{-- ... (كود المودال كما هو) ... --}}
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('status');
    const paymentConfirmationDiv = document.getElementById('payment_confirmation_options');
    // ... (بقية متغيرات JavaScript من ملفك الأصلي للمودال)

    // *** بداية: JavaScript لإظهار/إخفاء حقل سبب الإلغاء ***
    const cancellationReasonSection = document.getElementById('cancellationReasonSection');
    const cancellationReasonTextarea = document.getElementById('cancellation_reason');

    // استخدم الثوابت من PHP المحولة إلى JavaScript
    // تأكد من أن هذه القيم هي القيم الفعلية المحفوظة في قاعدة البيانات
    const cancellationStatusesRequiringReasonJS = @json(\App\Models\Booking::getCancellationStatusesRequiringReason());

    function toggleCancellationReasonField() {
        if (!statusSelect || !cancellationReasonSection || !cancellationReasonTextarea) return; // تحقق من وجود العناصر

        const selectedStatus = statusSelect.value;
        if (cancellationStatusesRequiringReasonJS.includes(selectedStatus)) {
            cancellationReasonSection.style.display = 'block';
            cancellationReasonTextarea.setAttribute('required', 'required');
        } else {
            cancellationReasonSection.style.display = 'none';
            cancellationReasonTextarea.removeAttribute('required');
            // cancellationReasonTextarea.value = ''; // يمكنك اختيار مسح القيمة عند الإخفاء
        }
    }
    // *** نهاية: JavaScript لإظهار/إخفاء حقل سبب الإلغاء ***


    // دالة إظهار/إخفاء خيارات الدفع وتحديث الخيارات المعروضة (من ملفك الأصلي مع التعديلات)
    // ... (دالة togglePaymentConfirmationOptions من ملفك الأصلي مع التعديلات التي أرسلتها سابقًا) ...
    const depositModalElement = document.getElementById('depositAmountModal');
    const depositModal = (typeof bootstrap !== 'undefined' && depositModalElement) ? new bootstrap.Modal(depositModalElement) : null;
    const depositAmountInput = document.getElementById('deposit_amount_input');
    const submitDepositModalBtn = document.getElementById('submitDepositModal');
    const modalDepositAmountHiddenInput = document.getElementById('modal_deposit_amount');
    const depositAmountError = document.getElementById('deposit_amount_error');
    const updateStatusForm = document.getElementById('updateStatusForm');

    const confirmedBookingStatusValueJS = '{{ \App\Models\Booking::STATUS_CONFIRMED }}';
    const partiallyPaidInvoiceStatusValueJS = '{{ \App\Models\Invoice::STATUS_PARTIALLY_PAID }}';
    const depositPaymentValueJS = 'deposit';
    const fullPaymentValueJS = 'full';

    function togglePaymentConfirmationOptions() {
        if (!statusSelect || !paymentConfirmationDiv) return;
        
        const showOptions = statusSelect.value === confirmedBookingStatusValueJS;
        paymentConfirmationDiv.style.display = showOptions ? 'block' : 'none';

        if(showOptions) {
            const invoiceStatusJS = '{{ $booking->invoice?->status }}';
            const isPartiallyPaidJS = invoiceStatusJS === partiallyPaidInvoiceStatusValueJS;
            const currentBookingStatusJS = '{{ $booking->status }}';

            const remainingAmountJS = parseFloat('{{ $isPartiallyPaid ? ($booking->invoice->remaining_amount ?? 0) : 0 }}');
            const fullAmountJS = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
            const currencyJS = '{{ $booking->invoice?->currency ?: "SAR" }}';

            const radios = paymentConfirmationDiv.querySelectorAll('input[name="payment_confirmation_type"]');
            let firstVisibleRadio = null;
            let isAnyRadioCheckedInitially = false;

            radios.forEach(radio => {
                const radioContainer = radio.closest('.form-check');
                const radioLabel = radioContainer.querySelector('label');
                let originalLabelText = ''; // النص الأصلي من HTML
                if (radio.value === fullPaymentValueJS) originalLabelText = 'تأكيد استلام المبلغ الكامل';
                else if (radio.value === depositPaymentValueJS) originalLabelText = 'تأكيد استلام العربون فقط';

                if (radio.value === depositPaymentValueJS && (isPartiallyPaidJS || currentBookingStatusJS === confirmedBookingStatusValueJS)) {
                    radioContainer.style.display = 'none';
                    radio.checked = false; // ألغِ التحديد إذا كان مخفيًا
                } else {
                    radioContainer.style.display = 'block';
                    if (!firstVisibleRadio) {
                        firstVisibleRadio = radio;
                    }
                    if (radio.value === fullPaymentValueJS) {
                        if (isPartiallyPaidJS) {
                            radioLabel.textContent = `تأكيد استلام المبلغ المتبقي (${remainingAmountJS.toFixed(2)} ${currencyJS})`;
                        } else if (fullAmountJS > 0) {
                            radioLabel.textContent = `تأكيد استلام المبلغ الكامل (${fullAmountJS.toFixed(2)} ${currencyJS})`;
                        } else {
                             radioLabel.textContent = originalLabelText || 'تأكيد استلام المبلغ الكامل';
                        }
                    } else if (radio.value === depositPaymentValueJS) {
                         radioLabel.textContent = originalLabelText;
                    }
                }
                if(radio.checked) isAnyRadioCheckedInitially = true;
            });
            
            // حدد الخيار الأول الظاهر فقط إذا لم يكن هناك أي خيار محدد بالفعل
            if(!isAnyRadioCheckedInitially && firstVisibleRadio) {
                firstVisibleRadio.checked = true;
            }
            // إذا كانت الفاتورة مدفوعة جزئيًا، تأكد أن خيار "المتبقي" محدد
            if (isPartiallyPaidJS) {
                const fullRadioToSelect = paymentConfirmationDiv.querySelector(`input[value="${fullPaymentValueJS}"]`);
                if (fullRadioToSelect && fullRadioToSelect.closest('.form-check').style.display !== 'none') {
                    fullRadioToSelect.checked = true;
                }
            }
        }
    }


    if (statusSelect) {
        togglePaymentConfirmationOptions(); // استدعاء عند تحميل الصفحة
        statusSelect.addEventListener('change', togglePaymentConfirmationOptions);
        
        toggleCancellationReasonField(); // *** استدعاء عند تحميل الصفحة لسبب الإلغاء ***
        statusSelect.addEventListener('change', toggleCancellationReasonField); // *** استدعاء عند تغيير حالة الحجز لسبب الإلغاء ***
    } else {
        console.error("Booking Show Page: Status select element (#status) not found.");
    }

    // ... (بقية كود JavaScript الخاص بالمودال من ملفك الأصلي، تأكد من أنه لا يتعارض) ...
    // هذا الجزء مهم لإرسال النموذج بعد تأكيد المودال
    if(updateStatusForm && statusSelect && depositModal) {
        updateStatusForm.addEventListener('submit', function (event) {
            const selectedStatus = statusSelect.value;
            const paymentOptionsDiv = document.getElementById('payment_confirmation_options');
            let selectedPaymentType = null;

            if (paymentOptionsDiv && paymentOptionsDiv.style.display === 'block') {
                const checkedRadio = paymentOptionsDiv.querySelector('input[name="payment_confirmation_type"]:checked');
                if (checkedRadio) {
                    selectedPaymentType = checkedRadio.value;
                }
            }
            
            const depositRadioContainer = document.getElementById('confirm_deposit')?.closest('.form-check');
            if (selectedStatus === confirmedBookingStatusValueJS && selectedPaymentType === depositPaymentValueJS && depositRadioContainer && depositRadioContainer.style.display !== 'none') {
                event.preventDefault(); 
                if(depositAmountInput && depositAmountError && depositModal) {
                    depositAmountInput.classList.remove('is-invalid');
                    depositAmountError.textContent = '';
                    depositAmountInput.value = ''; // مسح القيمة القديمة
                    const invoiceAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
                    if(!isNaN(invoiceAmount) && invoiceAmount > 0) {
                        depositAmountInput.placeholder = `المبلغ الإجمالي للفاتورة: ${invoiceAmount.toFixed(2)}`;
                    } else {
                        depositAmountInput.placeholder = 'أدخل مبلغ العربون';
                    }
                    depositModal.show();
                }
            }
        });
    }

    if (submitDepositModalBtn && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError && updateStatusForm) {
        submitDepositModalBtn.addEventListener('click', function() {
            let depositAmount = parseFloat(depositAmountInput.value.replace(',', '.'));
            const maxAmount = parseFloat('{{ $booking->invoice?->amount ?? 999999 }}'); // المبلغ الكلي للفاتورة

            depositAmountInput.classList.remove('is-invalid');
            depositAmountError.textContent = '';

            if (isNaN(depositAmount) || depositAmount <= 0) {
                depositAmountInput.classList.add('is-invalid');
                depositAmountError.textContent = 'الرجاء إدخال مبلغ صحيح للعربون.';
                return;
            }
            if (depositAmount >= maxAmount) {
                depositAmountInput.classList.add('is-invalid');
                depositAmountError.textContent = 'مبلغ العربون لا يمكن أن يكون مساوياً أو أكبر من المبلغ الإجمالي للفاتورة.';
                return;
            }

            modalDepositAmountHiddenInput.value = depositAmount;
            // تأكد أن الحالة هي "مؤكد" وأن خيار الدفع هو "عربون" قبل الإرسال
            statusSelect.value = confirmedBookingStatusValueJS;
            const depositRadio = document.getElementById('confirm_deposit');
            if(depositRadio) depositRadio.checked = true;
            
            // قد تحتاج إلى إخفاء خيارات الدفع الأخرى هنا أو التأكد من أن payment_confirmation_type صحيح
            const paymentTypeRadios = document.querySelectorAll('input[name="payment_confirmation_type"]');
            paymentTypeRadios.forEach(radio => {
                if(radio.value === depositPaymentValueJS) radio.checked = true;
                else radio.checked = false;
            });
            
            updateStatusForm.submit();
        });
    }
    
    if(depositModalElement && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError){
       depositModalElement.addEventListener('hidden.bs.modal', function () {
           depositAmountInput.classList.remove('is-invalid');
           depositAmountError.textContent = '';
           modalDepositAmountHiddenInput.value = ''; // مسح القيمة المخفية
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
    .bg-success-soft { background-color: rgba(40, 167, 69, 0.1); }
    .text-success { color: #28a745 !important; }
    .bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); }
    .text-warning { color: #ffc107 !important; }
    .bg-info-soft { background-color: rgba(23, 162, 184, 0.1); }
    .text-info { color: #17a2b8 !important; }
    .bg-primary-soft { background-color: rgba(0, 123, 255, 0.1); }
    .text-primary { color: #007bff !important; }
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); }
    .text-secondary { color: #6c757d !important; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
    .text-danger { color: #dc3545 !important; }
</style>
@endpush
