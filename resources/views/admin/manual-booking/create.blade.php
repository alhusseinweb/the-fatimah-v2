@extends('layouts.admin')

@section('title', 'إنشاء حجز يدوي لعميل جديد')

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

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.manual-booking.store') }}" method="POST">
                @csrf

                {{-- Customer Details --}}
                <div class="form-section">
                    <h5 class="card-title mb-3">1. بيانات العميل</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="customer_name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required>
                            @error('customer_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_mobile" class="form-label">رقم الجوال <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_mobile') is-invalid @enderror" id="customer_mobile" name="customer_mobile" value="{{ old('customer_mobile') }}" placeholder="05XXXXXXXX" required>
                            @error('customer_mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('customer_email') is-invalid @enderror" id="customer_email" name="customer_email" value="{{ old('customer_email') }}" required>
                            @error('customer_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Booking Details --}}
                <div class="form-section">
                    <h5 class="card-title mb-3">2. تفاصيل الحجز</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="service_id" class="form-label">الخدمة المطلوبة <span class="text-danger">*</span></label>
                            <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                                <option value="">اختر الخدمة...</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-price="{{ $service->price_sar }}" {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                        {{ $service->name_ar }} ({{ $service->price_sar }} ريال)
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                         <div class="col-md-3 mb-3">
                            <label for="booking_date" class="form-label">تاريخ الحجز <span class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr-date @error('booking_date') is-invalid @enderror" id="booking_date" name="booking_date" value="{{ old('booking_date') }}" placeholder="YYYY-MM-DD" required>
                            @error('booking_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="booking_time" class="form-label">وقت الحجز <span class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr-time @error('booking_time') is-invalid @enderror" id="booking_time" name="booking_time" value="{{ old('booking_time') }}" placeholder="HH:MM" required>
                            @error('booking_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="event_location" class="form-label">مكان المناسبة</label>
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
                    <h5 class="card-title mb-3">3. تفاصيل الدفع</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="booking_amount" class="form-label">مبلغ الحجز الإجمالي <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control @error('booking_amount') is-invalid @enderror" id="booking_amount" name="booking_amount" value="{{ old('booking_amount') }}" placeholder="مثال: 1200" required>
                             <div class="form-text">هذا هو المبلغ الإجمالي للفاتورة قبل أي دفعات.</div>
                            @error('booking_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount_paid" class="form-label">المبلغ المدفوع <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control @error('amount_paid') is-invalid @enderror" id="amount_paid" name="amount_paid" value="{{ old('amount_paid', 0) }}" placeholder="مثال: 550 أو 0" required>
                            <div class="form-text">المبلغ الذي دفعه العميل بالفعل (أو 0 إذا لم يدفع شيء بعد).</div>
                            @error('amount_paid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary me-2">إلغاء</a>
                    <button type="submit" class="btn btn-primary">
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
            locale: "ar", // Apply Arabic localization
            minDate: "today"
        });
        flatpickr(".flatpickr-time", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            minuteIncrement: 15,
            time_24hr: false // Use 12hr format if preferred, then adjust time format validation if needed
        });

        const serviceSelect = document.getElementById('service_id');
        const bookingAmountInput = document.getElementById('booking_amount');

        if (serviceSelect && bookingAmountInput) {
            serviceSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.dataset.price;
                if (price) {
                    bookingAmountInput.value = parseFloat(price).toFixed(2);
                } else {
                    bookingAmountInput.value = '';
                }
            });
            // Trigger change on load if a service is pre-selected (e.g., from old input)
            if(serviceSelect.value) {
                 serviceSelect.dispatchEvent(new Event('change'));
            }
        }
    });
</script>
@endpush
