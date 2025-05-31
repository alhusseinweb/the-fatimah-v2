@extends('layouts.admin')

@section('title', "تفاصيل الحجز #" . $booking->id)

@php
    // يتم تمرير $statuses (ترجمات حالات الحجز) من BookingController@show
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
            // يمكن تركها فارغة إذا كانت الدالة معرفة بشكل عام
            // أو إضافة تعريف بسيط هنا إذا لزم الأمر فقط لهذه الصفحة
            return str_replace(range(0, 9), ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], $number);
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
                                <div class="alert alert-success">{{ session('update_status_success') }}</div>
                            @endif
                            @if(session('update_status_error'))
                                <div class="alert alert-danger">{{ session('update_status_error') }}</div>
                            @endif

                            @if ($errors->updateStatus && $errors->updateStatus->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->updateStatus->all() as $error) <li>{{ $error }}</li> @endforeach
                                    </ul>
                                </div>
                            @endif
                             @if (!$errors->updateStatus && $errors->any() && !$errors->hasBag('updatePayment')) {{-- عرض الأخطاء العامة فقط إذا لم تكن ضمن updateStatus bag أو updatePayment bag --}}
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
                                        @foreach ($statuses as $value => $label) {{-- $statuses يتم تمريرها من BookingController@show --}}
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
                                                    $currentLabel = "تأكيد استلام المبلغ المتبقي (" . toArabicDigits(number_format($remainingAmount, 2)) . " $currency)";
                                                } elseif ($value === $fullValueConstant && !$isPartiallyPaid && $currentInvoice) {
                                                     $currentLabel = "تأكيد استلام المبلغ الكامل (" . toArabicDigits(number_format($currentInvoice->amount, 2)) . " $currency)";
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
        {{-- ... (محتوى المودال كما هو) ... --}}
    </div>

@endsection

@push('scripts')
{{-- ... (سكريبتات JavaScript كما هي، مع التأكد من أن toArabicDigitsJS معرفة إذا كنت ستستخدمها هنا) ... --}}
@endpush

@push('styles')
{{-- ... (أنماط CSS كما هي) ... --}}
@endpush
