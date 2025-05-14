{{-- resources/views/admin/invoices/show.blade.php --}}

@extends('layouts.admin')

@section('title', "تفاصيل الفاتورة #" . $invoice->invoice_number)

{{-- تحميل مصفوفة ترجمة الحالات --}}
@php
    $invoiceStatusTranslations = method_exists($invoice, 'statuses') ? \App\Models\Invoice::statuses() : [];
    if (!function_exists('getInvoiceStatusTranslation')) { function getInvoiceStatusTranslation($status, $translations) { return $translations[$status] ?? Str::title(str_replace('_', ' ', $status)); } }
@endphp

@section('content')

    <div class="row g-4">
        {{-- عامود معلومات الفاتورة وتحديث الحالة --}}
        <div class="col-lg-7">
            {{-- تفاصيل الفاتورة --}}
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">تفاصيل الفاتورة #{{ $invoice->invoice_number }}</h6>
                    <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }} {{ $invoice->status_badge_class ? '' : 'text-white' }} px-3 py-1">
                        {{ $invoice->status_label ?? getInvoiceStatusTranslation($invoice->status, $invoiceStatusTranslations) }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 dl-row">
                        <dt class="col-sm-4"><i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>المبلغ الإجمالي:</dt>
                        <dd class="col-sm-8">{{ number_format($invoice->amount, 2) }} {{ $invoice->currency ?: 'ريال سعودي' }}</dd>

                        @if(isset($invoice->total_paid_amount) && ($invoice->status == \App\Models\Invoice::STATUS_PAID || $invoice->status == \App\Models\Invoice::STATUS_PARTIALLY_PAID))
                            <dt class="col-sm-4"><i class="fas fa-money-check-alt fa-fw me-1 text-muted"></i>المبلغ المدفوع:</dt>
                            <dd class="col-sm-8">{{ number_format($invoice->total_paid_amount, 2) }} {{ $invoice->currency }}</dd>
                        @endif
                        @if(isset($invoice->remaining_amount) && !in_array($invoice->status, [\App\Models\Invoice::STATUS_PAID, \App\Models\Invoice::STATUS_CANCELLED]))
                            <dt class="col-sm-4"><i class="fas fa-hand-holding-usd fa-fw me-1 text-muted"></i>المبلغ المتبقي:</dt>
                            <dd class="col-sm-8 fw-bold">{{ number_format($invoice->remaining_amount, 2) }} {{ $invoice->currency }}</dd>
                        @endif

                        <dt class="col-sm-4"><i class="fas fa-calendar-times fa-fw me-1 text-muted"></i>تاريخ الاستحقاق:</dt>
                        <dd class="col-sm-8">{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'غير محدد' }}</dd>

                        <dt class="col-sm-4"><i class="fas fa-calendar-check fa-fw me-1 text-muted"></i>تاريخ أول دفعة:</dt>
                        <dd class="col-sm-8">{{ $invoice->paid_at ? $invoice->paid_at->translatedFormat('l, d F Y - h:i A') : '-' }}</dd>

                        <dt class="col-sm-4"><i class="fas fa-credit-card fa-fw me-1 text-muted"></i>طريقة الدفع:</dt>
                        <dd class="col-sm-8">
                            {{-- **ترجمة طرق الدفع** --}}
                            @if($invoice->payment_method == 'bank_transfer') تحويل بنكي
                            @elseif($invoice->payment_method == 'tamara') تمارا
                            {{-- **ترجمة القيمة الافتراضية** --}}
                            @else {{ $invoice->payment_method ?: 'غير محدد' }}
                            @endif
                        </dd>

                        @if ($invoice->payment_gateway_ref)
                            {{-- **ترجمة Payment Ref** --}}
                            <dt class="col-sm-4"><i class="fas fa-receipt fa-fw me-1 text-muted"></i>مرجع الدفع:</dt>
                            <dd class="col-sm-8">{{ $invoice->payment_gateway_ref }}</dd>
                        @endif

                        <dt class="col-sm-4"><i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>تاريخ الإنشاء:</dt>
                        <dd class="col-sm-8">{{ $invoice->created_at ? $invoice->created_at->translatedFormat('d F Y, h:i A') : '-' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- قسم تأكيد التحويل البنكي --}}
            @if ($invoice->payment_method == 'bank_transfer' && $invoice->status == \App\Models\Invoice::STATUS_UNPAID)
                <div class="card shadow-sm mb-4 border-0 border-start border-success border-4">
                    <div class="card-header bg-success-soft py-3">
                        {{-- **ترجمة العنوان** --}}
                        <h6 class="m-0 fw-bold text-success"><i class="fas fa-university me-2"></i>تأكيد استلام التحويل البنكي</h6>
                    </div>
                    <div class="card-body">
                        {{-- **ترجمة النص** --}}
                        <p>اضغط الزر أدناه لتأكيد استلام المبلغ عن طريق التحويل البنكي.</p>
                        {{-- **ترجمة رسالة التأكيد** --}}
                        <form action="{{ route('admin.invoices.confirm-bank-transfer', $invoice->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من تغيير حالة الفاتورة إلى مدفوعة؟ لا يمكن التراجع عن هذا الإجراء.');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success">
                                <span class="icon text-white-50"><i class="fas fa-check-circle me-1"></i></span>
                                 {{-- **ترجمة نص الزر** --}}
                                <span class="text">تأكيد استلام المبلغ</span>
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($invoice->payment_method == 'bank_transfer' && $invoice->status == \App\Models\Invoice::STATUS_PAID)
                 <div class="alert alert-success shadow-sm">
                     {{-- **ترجمة النص** --}}
                    <i class="fas fa-check-circle me-1"></i> تم تأكيد استلام التحويل البنكي بتاريخ: {{ $invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i') : '' }}
                 </div>
            @endif


            {{-- تحديث حالة الفاتورة (عام) --}}
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-edit me-2"></i>تغيير حالة الفاتورة</h6>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-10 col-lg-8">
                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('admin.invoices.updateStatus', $invoice) }}" method="POST" id="updateStatusForm">
                                @csrf
                                @method('PATCH')
                                <div class="mb-3">
                                    <label for="status" class="form-label">الحالة الجديدة:</label>
                                    <select name="status" id="status" class="form-select @error('status', 'updateStatus') is-invalid @enderror" required>
                                        @php $availableStatuses = $invoiceStatusTranslations; @endphp
                                        @foreach ($availableStatuses as $key => $label)
                                            @if(!empty($label))
                                                <option value="{{ $key }}" @selected($invoice->status == $key)>{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('status', 'updateStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <small class="form-text text-muted">
                                        سيؤدي تحديد "مدفوعة" أو "مدفوعة جزئياً" إلى محاولة تسجيل دفعة.
                                    </small>
                                </div>
                                <input type="hidden" name="paid_amount" id="modal_paid_amount">
                                <button type="submit" class="btn btn-primary w-100">
                                    <span class="icon text-white-50"><i class="fas fa-sync-alt me-1"></i></span>
                                    <span class="text">تحديث الحالة</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- عامود معلومات الحجز المرتبط وسجل الدفع --}}
        <div class="col-lg-5">
           {{-- (معلومات الحجز المرتبط كما هي) --}}
            <div class="card shadow-sm mb-4 border-0"> <div class="card-header bg-white py-3"> <h6 class="m-0 fw-bold text-primary"><i class="fas fa-link me-2"></i>الحجز المرتبط</h6> </div> <div class="card-body"> @if($invoice->booking) <dl class="row mb-0"> <dt class="col-sm-4">رقم الحجز:</dt> <dd class="col-sm-8"><a href="{{ route('admin.bookings.show', $invoice->booking_id) }}">#{{ $invoice->booking_id }}</a></dd> <dt class="col-sm-4">العميل:</dt> <dd class="col-sm-8">{{ $invoice->booking->user?->name ?? 'N/A' }}</dd> <dt class="col-sm-4">الخدمة:</dt> <dd class="col-sm-8">{{ $invoice->booking->service?->name_ar ?? 'N/A' }}</dd> <dt class="col-sm-4">التاريخ والوقت:</dt> <dd class="col-sm-8">{{ $invoice->booking->booking_datetime ? $invoice->booking->booking_datetime->translatedFormat('l, d M Y - h:i A') : '-' }}</dd> </dl> @else <p class="text-danger mb-0">الحجز المرتبط بهذه الفاتورة غير موجود.</p> @endif </div> </div>

            {{-- (سجل الدفع كما هو) --}}
             <div class="card shadow-sm mb-4 border-0"> <div class="card-header bg-white py-3"> <h6 class="m-0 fw-bold text-primary"><i class="fas fa-history me-2"></i>سجل الدفعات</h6> </div> <div class="card-body"> @php $invoice->loadMissing('payments'); @endphp @if($invoice->payments->isNotEmpty()) <ul class="list-group list-group-flush payment-log-list"> @foreach($invoice->payments->sortByDesc('created_at') as $payment) <li class="list-group-item px-0 py-2"> <div class="d-flex w-100 justify-content-between"> <h6 class="mb-1 fw-bold small"> @if($payment->payment_gateway == 'tamara') <i class="fas fa-credit-card text-primary me-1"></i> تمارا @elseif($payment->payment_gateway == 'bank_transfer') <i class="fas fa-university text-info me-1"></i> تحويل بنكي @elseif($payment->payment_gateway == 'manual_admin') <i class="fas fa-user-check text-success me-1"></i> تأكيد يدوي @else <i class="fas fa-dollar-sign text-muted me-1"></i> {{ $payment->payment_gateway ?: 'غير محدد' }} @endif </h6> <span class="badge bg-success-soft text-success rounded-pill">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span> </div> <small class="text-muted d-block"> <i class="far fa-clock"></i> {{ $payment->created_at->translatedFormat('d M Y, H:i') }} @if($payment->transaction_id) | <i class="fas fa-receipt"></i> {{ $payment->transaction_id }} @endif @if($payment->payment_details && isset($payment->payment_details['confirmed_by'])) | <i class="fas fa-user-edit"></i> {{ \App\Models\User::find($payment->payment_details['confirmed_by'])?->name ?? 'مدير' }} @endif </small> </li> @endforeach </ul> @else <p class="text-muted mb-0">لا توجد سجلات دفع مسجلة لهذه الفاتورة بعد.</p> @endif </div> </div>

        </div>
    </div>


    {{-- Modal لإدخال المبلغ المدفوع جزئياً --}}
    <div class="modal fade" id="paymentAmountModal" tabindex="-1" aria-labelledby="paymentAmountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentAmountModalLabel">تسجيل دفعة يدوية</h5>
                     {{-- **ترجمة زر الإغلاق** --}}
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <p>يرجى إدخال المبلغ الذي تم استلامه لهذه الدفعة:</p>
                    <div class="mb-3">
                        <label for="paid_amount_input" class="form-label">المبلغ المدفوع ({{ $invoice->currency ?: 'SAR' }})</label>
                        <input type="text" inputmode="decimal" class="form-control" id="paid_amount_input" required placeholder="ادخل المبلغ هنا">
                        <div class="invalid-feedback" id="paid_amount_error"></div>
                    </div>
                    <p class="text-muted small">سيتم تحديث حالة الفاتورة إلى "مدفوعة جزئياً" وإنشاء سجل دفع بهذا المبلغ.</p>
                </div>
                <div class="modal-footer">
                    {{-- **ترجمة أزرار المودال** --}}
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="submitPaymentModal">تأكيد الدفعة</button>
                </div>
            </div>
        </div>
    </div>

@endsection

{{-- (الستايلات المضمنة والسكريبتات كما هي) --}}
@push('styles')
<style>
    .card-body dt { font-weight: 600; }
    .list-group-item h6 { font-weight: 600; }
    .list-group-item i { width: 1.2em; text-align: center; margin-left: 3px;}
    .dl-row dd, .dl-row dt { margin-bottom: 0.6rem; font-size: 0.95em; }
    .dl-row dt i.fa-fw { margin-left: 5px; color: #adb5bd; }
    .payment-log-list small { font-size: 0.8em; }
</style>
@endpush

@push('scripts')
<script>
    // (السكريبت الخاص بالمودال كما هو)
    document.addEventListener('DOMContentLoaded', function () {
        const statusSelect = document.getElementById('status');
        const updateStatusForm = document.getElementById('updateStatusForm');
        const paymentModalElement = document.getElementById('paymentAmountModal');
        const paymentModal = paymentModalElement ? new bootstrap.Modal(paymentModalElement) : null;
        const paidAmountInput = document.getElementById('paid_amount_input');
        const submitPaymentModalBtn = document.getElementById('submitPaymentModal');
        const modalPaidAmountHiddenInput = document.getElementById('modal_paid_amount');
        const paidAmountError = document.getElementById('paid_amount_error');

        const partiallyPaidStatus = '{{ \App\Models\Invoice::STATUS_PARTIALLY_PAID }}';
        const paidStatus = '{{ \App\Models\Invoice::STATUS_PAID }}';
        const currentInvoiceStatus = '{{ $invoice->status }}';

        if(updateStatusForm && statusSelect && paymentModal && paidAmountInput && submitPaymentModalBtn && modalPaidAmountHiddenInput && paidAmountError) {

            updateStatusForm.addEventListener('submit', function (event) {
                const selectedStatus = statusSelect.value;

                // افتح المودال فقط إذا تم اختيار "مدفوعة جزئياً" والفاتورة ليست مدفوعة بالفعل
                if (selectedStatus === partiallyPaidStatus && currentInvoiceStatus !== paidStatus && currentInvoiceStatus !== partiallyPaidStatus) {
                    event.preventDefault();
                    paidAmountInput.classList.remove('is-invalid');
                    paidAmountError.textContent = '';
                    paidAmountInput.value = '';
                    const maxAmount = parseFloat('{{ $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount }}');
                     if(!isNaN(maxAmount) && maxAmount > 0) {
                        paidAmountInput.placeholder = `المبلغ المتبقي: ${maxAmount.toFixed(2)}`;
                    } else {
                        paidAmountInput.placeholder = 'ادخل المبلغ هنا';
                    }
                    paymentModal.show();
                }
            });

            submitPaymentModalBtn.addEventListener('click', function() {
                 let paidAmount = parseFloat(paidAmountInput.value.replace(',', '.'));
                 const maxAmount = parseFloat('{{ $invoice->remaining_amount > 0 ? $invoice->remaining_amount : $invoice->amount }}');

                 paidAmountInput.classList.remove('is-invalid');
                 paidAmountError.textContent = '';

                 if (isNaN(paidAmount) || paidAmount <= 0) {
                     paidAmountInput.classList.add('is-invalid');
                     paidAmountError.textContent = 'الرجاء إدخال مبلغ صحيح أكبر من صفر.';
                     return;
                 }
                 if (paidAmount > maxAmount) {
                     paidAmountInput.classList.add('is-invalid');
                     paidAmountError.textContent = `المبلغ المدخل (${paidAmount.toFixed(2)}) لا يمكن أن يتجاوز المبلغ المتبقي (${maxAmount.toFixed(2)}).`;
                     return;
                 }

                 modalPaidAmountHiddenInput.value = paidAmount;
                 statusSelect.value = partiallyPaidStatus;
                 updateStatusForm.submit();
             });

            paymentModalElement.addEventListener('hidden.bs.modal', function () {
                 paidAmountInput.value = '';
                 paidAmountInput.classList.remove('is-invalid');
                 paidAmountError.textContent = '';
                 modalPaidAmountHiddenInput.value = '';
             });

        } else {
            console.error("Invoice Show Page: One or more elements required for the partial payment modal functionality were not found. Check element IDs.");
        }
    });
</script>
@endpush