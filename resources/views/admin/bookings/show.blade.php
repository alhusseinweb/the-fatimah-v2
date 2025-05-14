@extends('layouts.admin')

@section('title', "تفاصيل الحجز #" . $booking->id)

{{-- تحميل مصفوفات ترجمة الحالات --}}
@php
    $bookingStatusTranslations = \App\Models\Booking::statuses();
    $invoiceStatusTranslations = \App\Models\Invoice::statuses();
    // استخدام الدوال المساعدة إذا كانت موجودة أو تعريفها
    if (!function_exists('getBookingStatusTranslation')) { function getBookingStatusTranslation($status, $translations) { return $translations[$status] ?? Str::title(str_replace('_', ' ', $status)); } }
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
                    <span class="status-pill {{ $booking->status_badge_class ?? 'bg-secondary' }} {{ $booking->status_badge_class ? '' : 'text-white' }} px-3 py-1">
                        {{ $booking->status_label ?? getBookingStatusTranslation($booking->status, $bookingStatusTranslations) }}
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 dl-row">
                        {{-- تفاصيل الوقت والطلب --}}
                        <dt class="col-sm-4"><i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>تاريخ ووقت الحجز:</dt>
                        <dd class="col-sm-8">{{ $booking->booking_datetime ? $booking->booking_datetime->translatedFormat('l, d F Y - h:i A') : '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>تاريخ الطلب:</dt>
                        <dd class="col-sm-8">{{ $booking->created_at ? $booking->created_at->translatedFormat('d F Y, h:i A') : '-' }}</dd>
                        <hr class="my-3">
                        {{-- تفاصيل العميل --}}
                        <dt class="col-sm-4"><i class="fas fa-user fa-fw me-1 text-muted"></i>اسم العميل:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->name ?? 'غير متوفر' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-mobile-alt fa-fw me-1 text-muted"></i>رقم الجوال:</dt>
                        <dd class="col-sm-8" dir="ltr" style="text-align: right;">{{ $booking->user?->mobile_number ?? 'غير متوفر' }}</dd>
                         <dt class="col-sm-4"><i class="fas fa-envelope fa-fw me-1 text-muted"></i>البريد الإلكتروني:</dt>
                        <dd class="col-sm-8">{{ $booking->user?->email ?? 'غير متوفر' }}</dd>
                        <hr class="my-3">
                        {{-- تفاصيل الخدمة --}}
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
                        {{-- تفاصيل الحفل --}}
                        <dt class="col-sm-4"><i class="fas fa-map-marker-alt fa-fw me-1 text-muted"></i>مكان الحفل:</dt>
                        <dd class="col-sm-8">{{ $booking->event_location ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-user-tie fa-fw me-1 text-muted"></i>اسم العريس (إنجليزي):</dt>
                        <dd class="col-sm-8">{{ $booking->groom_name_en ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-female fa-fw me-1 text-muted"></i>اسم العروس (إنجليزي):</dt>
                        <dd class="col-sm-8">{{ $booking->bride_name_en ?: '-' }}</dd>
                        <hr class="my-3">
                        {{-- ملاحظات إضافية --}}
                        <dt class="col-sm-4"><i class="far fa-sticky-note fa-fw me-1 text-muted"></i>ملاحظات العميل:</dt>
                        <dd class="col-sm-8">{{ $booking->customer_notes ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-check-square fa-fw me-1 text-muted"></i>الموافقة على السياسة:</dt>
                        <dd class="col-sm-8">{{ $booking->agreed_to_policy ? 'نعم' : 'لا' }}</dd>
                        <hr class="my-3">
                         {{-- تفاصيل الفاتورة والخصم --}}
                        <dt class="col-sm-4"><i class="fas fa-percent fa-fw me-1 text-muted"></i>كود الخصم المستخدم:</dt>
                        <dd class="col-sm-8">{{ $booking->discountCode?->code ?: '-' }}</dd>
                        <dt class="col-sm-4"><i class="fas fa-file-invoice-dollar fa-fw me-1 text-muted"></i>الفاتورة:</dt>
                        <dd class="col-sm-8">
                            @if($invoice = $booking->invoice)
                                <a href="{{ route('admin.invoices.show', $invoice->id) }}" class="fw-bold">رقم: {{ $invoice->invoice_number }}</a> |
                                المبلغ الإجمالي: {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }} |
                                الحالة: <span class="status-pill {{ $invoice->status_badge_class ?? 'bg-secondary' }} {{ $invoice->status_badge_class ? '' : 'text-white' }}">{{ $invoice->status_label ?? getInvoiceStatusTranslation($invoice->status, $invoiceStatusTranslations) }}</span> |
                                الدفع: {{ $invoice->payment_method == 'tamara' ? 'تمارا' : ($invoice->payment_method == 'bank_transfer' ? 'تحويل بنكي' : 'غير محدد') }}
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
                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form action="{{ route('admin.bookings.updateStatus', $booking) }}" method="POST" id="updateStatusForm">
                                @csrf
                                @method('PATCH')
                                <div class="mb-3">
                                    <label for="status" class="form-label">الحالة الجديدة:</label>
                                    <select name="status" id="status" class="form-select @error('status', 'updateStatus') is-invalid @enderror" required>
                                        @foreach ($statuses as $key => $label)
                                            <option value="{{ $key }}" @selected($booking->status == $key)> {{ $label }} </option>
                                        @endforeach
                                    </select>
                                    @error('status', 'updateStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                {{-- قسم تأكيد الدفع --}}
                                <div id="payment_confirmation_options" class="mb-3 border p-3 rounded bg-light" style="display: none;">
                                    <label class="form-label fw-bold">تأكيد استلام الدفعة:</label>
                                    @isset($paymentConfirmationOptions)
                                        {{-- **تعديل:** الحصول على معلومات الفاتورة والحجز مسبقاً --}}
                                        @php
                                            $invoice = $booking->invoice;
                                            $invoiceStatus = $invoice?->status;
                                            $isPartiallyPaid = $invoiceStatus === \App\Models\Invoice::STATUS_PARTIALLY_PAID;
                                            $remainingAmount = $isPartiallyPaid ? ($invoice->remaining_amount ?? 0) : 0;
                                            $currency = $invoice?->currency ?: 'SAR';

                                            // **جديد:** الحصول على حالة الحجز
                                            $bookingStatus = $booking->status;
                                            $confirmedBookingStatus = \App\Models\Booking::STATUS_CONFIRMED;

                                            // تحديد القيم المتوقعة من المتحكم
                                            $depositValue = 'deposit';
                                            $fullValue = 'full';
                                        @endphp

                                        @foreach($paymentConfirmationOptions as $value => $label)

                                            {{-- **تعديل:** إخفاء خيار العربون إذا كانت الفاتورة مدفوعة جزئياً أو الحجز مؤكداً --}}
                                            @if($value === $depositValue && ($isPartiallyPaid || $bookingStatus === $confirmedBookingStatus))
                                                @continue
                                            @endif

                                            {{-- تحديد النص المناسب للخيار --}}
                                            @php
                                                $currentLabel = $label;
                                                if ($value === $fullValue && $isPartiallyPaid) {
                                                    $currentLabel = "تأكيد استلام المبلغ المتبقي (" . number_format($remainingAmount, 2) . " $currency)";
                                                } elseif ($value === $fullValue && !$isPartiallyPaid && $invoice) {
                                                     $currentLabel = "تأكيد استلام المبلغ الكامل (" . number_format($invoice->amount, 2) . " $currency)";
                                                }
                                            @endphp

                                            <div class="form-check">
                                                {{-- تحديد الخيار المحدد تلقائياً --}}
                                                <input class="form-check-input"
                                                       type="radio"
                                                       name="payment_confirmation_type"
                                                       id="confirm_{{ $value }}"
                                                       value="{{ $value }}"
                                                       {{-- حدد "المتبقي/الكامل" إذا كانت الفاتورة مدفوعة جزئياً، أو الأول إذا لم تكن كذلك ولم يكن الحجز مؤكداً --}}
                                                       {{ ($isPartiallyPaid && $value === $fullValue) || (!$isPartiallyPaid && $bookingStatus !== $confirmedBookingStatus && $loop->first) ? 'checked' : '' }}
                                                       >
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

                                {{-- حقل مخفي لمبلغ العربون القادم من المودال --}}
                                <input type="hidden" name="deposit_amount" id="modal_deposit_amount">

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-sync-alt fa-sm me-1"></i> تحديث الحالة
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- سجل الدفعات الخاص بالفاتورة المرتبطة --}}
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
                                                @else <i class="fas fa-dollar-sign text-muted me-1"></i> {{ $payment->payment_gateway ?: 'غير محدد' }}
                                                @endif
                                            </h6>
                                            <span class="badge bg-success-soft text-success rounded-pill">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
                                        </div>
                                        <small class="text-muted d-block">
                                            <i class="far fa-clock"></i> {{ $payment->created_at->translatedFormat('d M Y, H:i') }}
                                            @if($payment->transaction_id) | <i class="fas fa-receipt"></i> {{ $payment->transaction_id }} @endif
                                            @if($payment->payment_details && isset($payment->payment_details['confirmed_by'])) | <i class="fas fa-user-edit"></i> {{ \App\Models\User::find($payment->payment_details['confirmed_by'])?->name ?? 'مدير' }} @endif
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

     {{-- Modal لإدخال مبلغ العربون --}}
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

{{-- Scripts and Styles remain the same as the previous response --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('status');
    const paymentConfirmationDiv = document.getElementById('payment_confirmation_options');
    const depositModalElement = document.getElementById('depositAmountModal');
    const depositModal = (typeof bootstrap !== 'undefined' && depositModalElement) ? new bootstrap.Modal(depositModalElement) : null;
    const depositAmountInput = document.getElementById('deposit_amount_input');
    const submitDepositModalBtn = document.getElementById('submitDepositModal');
    const modalDepositAmountHiddenInput = document.getElementById('modal_deposit_amount');
    const depositAmountError = document.getElementById('deposit_amount_error');
    const updateStatusForm = document.getElementById('updateStatusForm');

    // قيم الحالات وخيارات الدفع
    const confirmedBookingStatusValue = '{{ \App\Models\Booking::STATUS_CONFIRMED }}'; // ** حالة الحجز المؤكد **
    const partiallyPaidInvoiceStatusValue = '{{ \App\Models\Invoice::STATUS_PARTIALLY_PAID }}'; // ** حالة الفاتورة المدفوعة جزئياً **
    const depositPaymentValue = 'deposit';
    const fullPaymentValue = 'full';

    // دالة إظهار/إخفاء خيارات الدفع وتحديث الخيارات المعروضة
    function togglePaymentConfirmationOptions() {
        if (statusSelect && paymentConfirmationDiv) {
             const showOptions = statusSelect.value === confirmedBookingStatusValue;
             paymentConfirmationDiv.style.display = showOptions ? 'block' : 'none';

             if(showOptions) {
                 const invoiceStatus = '{{ $booking->invoice?->status }}';
                 const isPartiallyPaid = invoiceStatus === partiallyPaidInvoiceStatusValue;
                 const currentBookingStatus = '{{ $booking->status }}'; // **الحصول على حالة الحجز الحالية**

                 const remainingAmount = parseFloat('{{ $isPartiallyPaid ? ($booking->invoice->remaining_amount ?? 0) : 0 }}');
                 const fullAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
                 const currency = '{{ $booking->invoice?->currency ?: "SAR" }}';

                 const radios = paymentConfirmationDiv.querySelectorAll('input[name="payment_confirmation_type"]');
                 let firstVisibleRadio = null;

                 radios.forEach(radio => {
                     const radioContainer = radio.closest('.form-check');
                     const radioLabel = radioContainer.querySelector('label');
                     let originalLabel = '';
                      if (radio.value === fullPaymentValue) originalLabel = 'تأكيد استلام المبلغ الكامل';
                      else if (radio.value === depositPaymentValue) originalLabel = 'تأكيد استلام العربون فقط';

                     // ** تعديل الشرط: إخفاء العربون إذا الفاتورة مدفوعة جزئياً أو الحجز مؤكد **
                     if (radio.value === depositPaymentValue && (isPartiallyPaid || currentBookingStatus === confirmedBookingStatusValue)) {
                         radioContainer.style.display = 'none';
                         radio.checked = false;
                     } else {
                         radioContainer.style.display = 'block';
                          if (!firstVisibleRadio) {
                             firstVisibleRadio = radio;
                          }

                         // تحديث نص خيار الدفع الكامل/المتبقي
                         if (radio.value === fullPaymentValue) {
                             if (isPartiallyPaid) {
                                 radioLabel.textContent = `تأكيد استلام المبلغ المتبقي (${remainingAmount.toFixed(2)} ${currency})`;
                             } else if (fullAmount > 0) {
                                 radioLabel.textContent = `تأكيد استلام المبلغ الكامل (${fullAmount.toFixed(2)} ${currency})`;
                             } else {
                                 radioLabel.textContent = originalLabel || 'تأكيد استلام المبلغ الكامل';
                             }
                         }
                         // استعادة النص الأصلي للخيارات الأخرى (مثل العربون إذا كان ظاهراً)
                         else if (radio.value === depositPaymentValue) {
                              radioLabel.textContent = originalLabel;
                         }
                     }
                 });

                  // تحديد الخيار المناسب تلقائياً
                  const anyChecked = paymentConfirmationDiv.querySelector('input[name="payment_confirmation_type"]:checked');
                  if (!anyChecked && firstVisibleRadio) {
                      firstVisibleRadio.checked = true; // حدد أول خيار ظاهر إذا لم يتم تحديد شيء
                  }
                  // إذا كانت الفاتورة مدفوعة جزئياً، تأكد من تحديد خيار الدفع المتبقي
                  else if (isPartiallyPaid) {
                      const fullRadio = paymentConfirmationDiv.querySelector(`input[value="${fullValue}"]`);
                      if(fullRadio) fullRadio.checked = true;
                  }
             }
        }
    }

    // إضافة مستمع لتغيير الحالة وتطبيق التحديث الأولي
    if (statusSelect) {
        togglePaymentConfirmationOptions();
        statusSelect.addEventListener('change', togglePaymentConfirmationOptions);
    } else {
        console.error("Booking Show Page: Status select element (#status) not found.");
    }

     // إضافة مستمع لإرسال الفورم الرئيسي والتحقق من مودال العربون
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

            // الشرط: إذا الحالة "مؤكد" وخيار الدفع هو "عربون"
            if (selectedStatus === confirmedBookingStatusValue && selectedPaymentType === depositPaymentValue) {
                 // تأكد أن خيار العربون ليس مخفياً
                const depositRadioContainer = document.getElementById('confirm_deposit')?.closest('.form-check');
                 if(depositRadioContainer && depositRadioContainer.style.display !== 'none'){
                    event.preventDefault();
                    // عرض مودال العربون... (الكود كما هو)
                     if(depositAmountInput && depositAmountError && depositModal) {
                         // ... (إعادة تعيين وعرض المودال) ...
                          depositAmountInput.classList.remove('is-invalid');
                          depositAmountError.textContent = '';
                          depositAmountInput.value = '';
                           const invoiceAmount = parseFloat('{{ $booking->invoice?->amount ?? 0 }}');
                           if(!isNaN(invoiceAmount) && invoiceAmount > 0) {
                              depositAmountInput.placeholder = `المبلغ الإجمالي للفاتورة: ${invoiceAmount.toFixed(2)}`;
                           } else {
                              depositAmountInput.placeholder = 'أدخل مبلغ العربون';
                           }
                          depositModal.show();
                     } else { /* ... */ }
                }
            }
        });
    } else { /* ... */ }

    // إضافة مستمع لزر تأكيد مودال العربون (الكود كما هو)
    if (submitDepositModalBtn && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError && updateStatusForm) {
        submitDepositModalBtn.addEventListener('click', function() {
             let depositAmount = parseFloat(depositAmountInput.value.replace(',', '.'));
             const maxAmount = parseFloat('{{ $booking->invoice?->amount ?? 999999 }}');

             depositAmountInput.classList.remove('is-invalid');
             depositAmountError.textContent = '';

             if (isNaN(depositAmount) || depositAmount <= 0) { /* ... */ return; }
             if (depositAmount >= maxAmount) { /* ... */ return; }

             modalDepositAmountHiddenInput.value = depositAmount;
             statusSelect.value = confirmedBookingStatusValue;
             const depositRadio = document.getElementById('confirm_deposit');
             if(depositRadio) depositRadio.checked = true;

             updateStatusForm.submit();
        });
    } else { /* ... */ }

     // إعادة تعيين المودال عند الإغلاق (الكود كما هو)
     if(depositModalElement && depositAmountInput && modalDepositAmountHiddenInput && depositAmountError){
        depositModalElement.addEventListener('hidden.bs.modal', function () { /* ... */ });
     }

});
</script>
@endpush

@push('styles')
<style>
    .dl-row dt, .dl-row dd { margin-bottom: 0.6rem; font-size: 0.95em; }
    .dl-row dt { font-weight: 600; /* color: var(--dark-gray); */ color: #525f7f; }
    .dl-row dt i.fa-fw { margin-left: 5px; color: #adb5bd; }
    .list-group-flush .list-group-item { background-color: transparent; border: none; padding-left: 0; padding-right: 0; }
    .payment-log-list i { width: 1.2em; text-align: center; margin-left: 3px; }
    .payment-log-list small { font-size: 0.8em; }
    .status-pill { font-size: 0.8rem; font-weight: 600; border-radius: 50rem; vertical-align: middle; }
    /* تمييز للألوان بناءً على الفئات إن أمكن */
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