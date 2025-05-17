@extends('layouts.admin')

@section('title', "تفاصيل الحجز #" . $booking->id)

@php
    // استخدام الدالة المساعدة من الموديل مباشرة إذا لم يتم تمريرها من المتحكم
    // ولكن المتحكم BookingController@show يقوم بتمرير $statuses
    // $bookingStatusOptions = \App\Models\Booking::getStatusesWithOptions();
    $invoiceStatusTranslations = \App\Models\Invoice::statuses(); // افترض أن Invoice model لديه دالة statuses()
    if (!function_exists('getInvoiceStatusTranslation')) { function getInvoiceStatusTranslation($status, $translations) { return $translations[$status] ?? Str::title(str_replace('_', ' ', $status)); } }
@endphp

@section('content')

    <div class="row g-4">
        {{-- عامود المعلومات الرئيسي --}}
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-receipt me-2"></i>تفاصيل الحجز #{{ $booking->id }}</h6>
                    <span class="status-pill {{ $booking->status_badge_class }} px-3 py-1">
                        {{ $booking->status_label }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 dl-row">
                        <dt class="col-sm-4"><i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>تاريخ ووقت الحجز:</dt>
                        <dd class="col-sm-8">{{ $booking->booking_datetime ? $booking->booking_datetime->translatedFormat('l, d F Y - h:i A') : '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>تاريخ الطلب:</dt>
                        <dd class="col-sm-8">{{ $booking->created_at ? $booking->created_at->translatedFormat('d F Y, h:i A') : '-' }}</dd>
                        
                        @if($booking->cancellation_reason)
                        <hr class="my-3">
                        <dt class="col-sm-4 text-danger"><i class="fas fa-info-circle fa-fw me-1"></i>سبب الإلغاء:</dt>
                        <dd class="col-sm-8 text-danger">{{ $booking->cancellation_reason }}</dd>
                        @endif

                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-user fa-fw me-1 text-muted"></i>اسم العميل:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->name ?? 'غير متوفر' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-mobile-alt fa-fw me-1 text-muted"></i>رقم الجوال:</dt>
                        <dd class="col-sm-8" dir="ltr" style="text-align: right;">{{ $booking->user?->mobile_number ?? 'غير متوفر' }}</dd>
                         <dt class="col-sm-4"><i class="fas fa-envelope fa-fw me-1 text-muted"></i>البريد الإلكتروني:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->email ?? 'غير متوفر' }}</dd>
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-concierge-bell fa-fw me-1 text-muted"></i>الخدمة المطلوبة:</dt>
                        <dd class="col-sm-8">{{ $booking->service?->name_ar ?? 'غير متوفر' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-clock fa-fw me-1 text-muted"></i>مدة الخدمة:</dt>
                        <dd class="col-sm-8">{{ $booking->service?->duration_hours ?? 'N/A' }} ساعات</dd>
                        <dt class="col-sm-4"><i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>سعر الخدمة الأصلي:</dt>
                        <dd class="col-sm-8">{{ number_format($booking->service?->price_sar ?? 0, 2) }} ريال</dd>
                        @if($booking->service?->included_items_ar)
                            <dt class="col-sm-4"><i class="fas fa-info-circle fa-fw me-1 text-muted"></i>تشمل الخدمة:</dt>
                            <dd class="col-sm-8">{!! nl2br(e($booking->service->included_items_ar)) !!}</dd>
                        @endif
                        <hr class="my-3">
                        <dt class="col-sm-4"><i class="fas fa-map-marker-alt fa-fw me-1 text-muted"></i>مكان الحفل:</dt>
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

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>تغيير حالة الحجز</h6>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-11">
                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif
                             @if (!$errors->updateStatus && $errors->any()) {{-- عرض الأخطاء العامة إذا لم تكن ضمن updateStatus bag --}}
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
                                        {{-- $statuses يتم تمريرها من BookingController@show --}}
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
                                                @elseif($payment->payment_gateway == 'manual_admin') <i class="fas fa-user-check text-success me-1"></i> تأكيد يدوي
                                                @else <i class="fas fa-dollar-sign text-muted me-1"></i> {{ $payment->payment_gateway_label ?? ($payment->payment_gateway ?: 'غير محدد') }}
                                                @endif
                                            </h6>
                                            <span class="badge bg-success-soft text-success rounded-pill">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                                        </div>
                                        <small class="text-muted d-block">
                                            <i class="far fa-clock"></i> {{ $payment->created_at->translatedFormat('d M Y, H:i') }}
                                            @if($payment->transaction_id) | <i class="fas fa-receipt"></i> {{ Str::limit($payment->transaction_id, 20) }} @endif
                                            {{-- تعديل لعرض اسم المدير الذي قام بالتأكيد --}}
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
    const depositModal = (typeof bootstrap !== 'undefined' && depositModalElement) ? new bootstrap.Modal(depositModalElement) : null;
    const depositAmountInput = document.getElementById('deposit_amount_input');
    const submitDepositModalBtn = document.getElementById('submitDepositModal');
    const modalDepositAmountHiddenInput = document.getElementById('modal_deposit_amount');
    const depositAmountError = document.getElementById('deposit_amount_error');
    const updateStatusForm = document.getElementById('updateBookingStatusForm'); // تم تصحيح اسم الفورم هنا

    const cancellationStatusesRequiringReasonJS = @json(\App\Models\Booking::getCancellationStatusesRequiringReason());
    const confirmedBookingStatusValueJS = '{{ \App\Models\Booking::STATUS_CONFIRMED }}';
    const partiallyPaidInvoiceStatusValueJS = '{{ \App\Models\Invoice::STATUS_PARTIALLY_PAID }}';
    const depositPaymentValueJS = 'deposit';
    const fullPaymentValueJS = 'full';

    function toggleCancellationReasonField() {
        if (!statusSelect || !cancellationReasonSection || !cancellationReasonTextarea) return;
        const selectedStatus = statusSelect.value;
        if (cancellationStatusesRequiringReasonJS.includes(selectedStatus)) {
            cancellationReasonSection.style.display = 'block';
            cancellationReasonTextarea.setAttribute('required', 'required');
        } else {
            cancellationReasonSection.style.display = 'none';
            cancellationReasonTextarea.removeAttribute('required');
        }
    }

    function togglePaymentConfirmationOptions() {
        if (!statusSelect || !paymentConfirmationDiv) return;
        const showOptions = statusSelect.value === confirmedBookingStatusValueJS;
        paymentConfirmationDiv.style.display = showOptions ? 'block' : 'none';

        if(showOptions) {
            const invoiceStatusJS = '{{ $booking->invoice?->status }}';
            const isPartiallyPaidJS = invoiceStatusJS === partiallyPaidInvoiceStatusValueJS;
            const currentBookingStatusJS = '{{ $booking->status }}';
            const remainingAmountJS = parseFloat('{{ $booking->invoice && $isPartiallyPaid ? ($booking->invoice->remaining_amount ?? 0) : 0 }}');
            const fullAmountJS = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
            const currencyJS = '{{ $booking->invoice?->currency ?: "SAR" }}';
            const radios = paymentConfirmationDiv.querySelectorAll('input[name="payment_confirmation_type"]');
            let firstVisibleRadio = null;
            let isAnyRadioCheckedInitially = paymentConfirmationDiv.querySelector('input[name="payment_confirmation_type"]:checked') !== null;

            radios.forEach(radio => {
                const radioContainer = radio.closest('.form-check');
                const radioLabel = radioContainer.querySelector('label');
                let originalLabelText = '';
                if (radio.value === fullPaymentValueJS) originalLabelText = 'تأكيد استلام المبلغ الكامل/المتبقي';
                else if (radio.value === depositPaymentValueJS) originalLabelText = 'تأكيد استلام العربون فقط';

                if (radio.value === depositPaymentValueJS && (isPartiallyPaidJS || currentBookingStatusJS === confirmedBookingStatusValueJS)) {
                    radioContainer.style.display = 'none';
                    if(radio.checked) radio.checked = false; // ألغِ التحديد إذا كان مخفيًا ومحددًا
                } else {
                    radioContainer.style.display = 'block';
                    if (!firstVisibleRadio && !isAnyRadioCheckedInitially) { // حدد الأول فقط إذا لم يكن هناك شيء محدد
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
            });
            
            if(!paymentConfirmationDiv.querySelector('input[name="payment_confirmation_type"]:checked') && firstVisibleRadio) {
                 firstVisibleRadio.checked = true;
            }
             else if (isPartiallyPaidJS) {
                const fullRadioToSelect = paymentConfirmationDiv.querySelector(`input[value="${fullPaymentValueJS}"]`);
                if (fullRadioToSelect && fullRadioToSelect.closest('.form-check').style.display !== 'none') {
                    fullRadioToSelect.checked = true;
                }
            }
        }
    }

    if (statusSelect) {
        togglePaymentConfirmationOptions();
        statusSelect.addEventListener('change', togglePaymentConfirmationOptions);
        toggleCancellationReasonField();
        statusSelect.addEventListener('change', toggleCancellationReasonField);
    }

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
                    depositAmountInput.value = '';
                    const invoiceAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
                    depositAmountInput.placeholder = invoiceAmount > 0 ? `المبلغ الإجمالي للفاتورة: ${invoiceAmount.toFixed(2)}` : 'أدخل مبلغ العربون';
                    depositModal.show();
                }
            }
        });
    }

    if (submitDepositModalBtn && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError && updateStatusForm) {
        submitDepositModalBtn.addEventListener('click', function() {
            let depositAmount = parseFloat(depositAmountInput.value.replace(',', '.'));
            const maxAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}'); // يجب أن يكون أقل من المبلغ الإجمالي

            depositAmountInput.classList.remove('is-invalid');
            depositAmountError.textContent = '';

            if (isNaN(depositAmount) || depositAmount <= 0) {
                depositAmountInput.classList.add('is-invalid');
                depositAmountError.textContent = 'الرجاء إدخال مبلغ صحيح للعربون (أكبر من صفر).';
                return;
            }
            // تأكد أن مبلغ العربون لا يساوي أو يتجاوز المبلغ الإجمالي للفاتورة إذا كان المبلغ الإجمالي أكبر من صفر
            if (maxAmount > 0 && depositAmount >= maxAmount) {
                depositAmountInput.classList.add('is-invalid');
                depositAmountError.textContent = 'مبلغ العربون لا يمكن أن يكون مساوياً أو أكبر من المبلغ الإجمالي للفاتورة.';
                return;
            }

            modalDepositAmountHiddenInput.value = depositAmount;
            statusSelect.value = confirmedBookingStatusValueJS;
            // التأكد من تحديد خيار العربون إذا كان ظاهراً
            const depositRadio = document.getElementById('confirm_deposit');
            if(depositRadio && depositRadio.closest('.form-check').style.display !== 'none') {
                 depositRadio.checked = true;
            } else {
                // إذا كان خيار العربون مخفيًا، قد تحتاج إلى تحديد خيار الدفع الكامل كبديل
                const fullRadio = document.getElementById('confirm_full');
                if(fullRadio) fullRadio.checked = true;
            }
            updateStatusForm.submit();
        });
    }
    
    if(depositModalElement && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError){
       depositModalElement.addEventListener('hidden.bs.modal', function () {
           depositAmountInput.classList.remove('is-invalid');
           depositAmountError.textContent = '';
           modalDepositAmountHiddenInput.value = '';
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
    /* الألوان الناعمة */
    .bg-success-soft { background-color: rgba(40, 167, 69, 0.1); } .text-success { color: #28a745 !important; }
    .bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); } .text-warning { color: #ffc107 !important; }
    .bg-info-soft { background-color: rgba(23, 162, 184, 0.1); } .text-info { color: #17a2b8 !important; }
    .bg-primary-soft { background-color: rgba(0, 123, 255, 0.1); } /* .text-primary { color: #007bff !important; } */ /* قد يتعارض مع لون الرابط */
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); } .text-secondary { color: #6c757d !important; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); } /* .text-danger { color: #dc3545 !important; } */ /* قد يتعارض مع لون الخطأ */
</style>
@endpush
