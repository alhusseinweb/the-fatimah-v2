{{-- resources/views/admin/discount_codes/_form.blade.php --}}

{{-- Display validation errors --}}
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Code --}}
<div class="form-group mb-3">
    <label for="code">كود الخصم <span class="text-danger">*</span></label>
    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $discountCode->code) }}" required dir="ltr">
    @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Type --}}
<div class="form-group mb-3">
    <label for="type">نوع الخصم <span class="text-danger">*</span></label>
    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
        <option value="">-- اختر النوع --</option>
        @foreach ($types as $key => $label)
            <option value="{{ $key }}" {{ old('type', $discountCode->type) == $key ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Value --}}
<div class="form-group mb-3">
    <label for="value">القيمة <span class="text-danger">*</span></label>
    <input type="number" name="value" id="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value', $discountCode->value) }}" required step="0.01" min="0.01">
    <small class="form-text text-muted">أدخل القيمة كرقم (مثال: 10 لخصم 10% أو 50 لخصم 50 ريال).</small>
    @error('value')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Start Date --}}
<div class="form-group mb-3">
    <label for="start_date">تاريخ البدء <span class="text-danger">*</span></label>
    <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $discountCode->start_date ? $discountCode->start_date->format('Y-m-d') : \Carbon\Carbon::today()->format('Y-m-d')) }}" required>
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- End Date --}}
<div class="form-group mb-3">
    <label for="end_date">تاريخ الانتهاء (اختياري)</label>
    <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $discountCode->end_date ? $discountCode->end_date->format('Y-m-d') : '') }}">
     <small class="form-text text-muted">اتركه فارغاً ليكون الكود فعالاً بلا تاريخ انتهاء.</small>
    @error('end_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Max Uses --}}
<div class="form-group mb-3">
    <label for="max_uses">الحد الأقصى للاستخدام (اختياري)</label>
    <input type="number" name="max_uses" id="max_uses" class="form-control @error('max_uses') is-invalid @enderror" value="{{ old('max_uses', $discountCode->max_uses) }}" min="1" step="1">
     <small class="form-text text-muted">اتركه فارغاً ليكون عدد الاستخدامات غير محدود.</small>
    @error('max_uses')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Allowed Payment Methods --}}
<div class="form-group mb-3">
    <label>طرق الدفع المسموح بها (اختياري)</label>
    <div>
        @if(isset($paymentMethodOptions) && !empty($paymentMethodOptions))
            @foreach ($paymentMethodOptions as $key => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input @error('allowed_payment_methods.' . $key) is-invalid @enderror"
                           type="checkbox"
                           name="allowed_payment_methods[]"
                           value="{{ $key }}"
                           id="payment_method_{{ $key }}"
                           {{ (is_array(old('allowed_payment_methods')) && in_array($key, old('allowed_payment_methods'))) || (isset($discountCode->allowed_payment_methods) && is_array($discountCode->allowed_payment_methods) && in_array($key, $discountCode->allowed_payment_methods)) ? 'checked' : '' }}>
                    <label class="form-check-label" for="payment_method_{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach
        @else
            <p class="small text-muted">لم يتم تحديد خيارات طرق الدفع.</p>
        @endif
    </div>
    <small class="form-text text-muted">إذا لم تختر أي طريقة، سينطبق الخصم على جميع طرق الدفع.</small>
    @error('allowed_payment_methods')
        <div class="d-block invalid-feedback">{{ $message }}</div>
    @enderror
    @error('allowed_payment_methods.*')
        <div class="d-block invalid-feedback">{{ $message }}</div>
    @enderror
</div>


{{-- Applicable From Time --}}
<div class="form-group mb-3">
    <label for="applicable_from_time">وقت بدء تطبيق الخصم (اختياري)</label>
    <input type="time" name="applicable_from_time" id="applicable_from_time" class="form-control @error('applicable_from_time') is-invalid @enderror" value="{{ old('applicable_from_time', $discountCode->applicable_from_time) }}">
    <small class="form-text text-muted">اتركه فارغاً ليكون فعالاً طوال اليوم (من بداية اليوم).</small>
    @error('applicable_from_time')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Applicable To Time --}}
<div class="form-group mb-3">
    <label for="applicable_to_time">وقت انتهاء تطبيق الخصم (اختياري)</label>
    <input type="time" name="applicable_to_time" id="applicable_to_time" class="form-control @error('applicable_to_time') is-invalid @enderror" value="{{ old('applicable_to_time', $discountCode->applicable_to_time) }}">
    <small class="form-text text-muted">اتركه فارغاً ليكون فعالاً طوال اليوم (حتى نهاية اليوم). تأكد أن وقت الانتهاء بعد وقت البدء إذا حددتهما معاً.</small>
    @error('applicable_to_time')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>


{{-- Is Active --}}
<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $discountCode->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">
        مفعل
    </label>
</div>

{{-- Submit/Cancel Buttons --}}
<button type="submit" class="btn btn-primary">
    {{ $discountCode->exists ? 'تحديث الكود' : 'إضافة الكود' }}
</button>
<a href="{{ route('admin.discount-codes.index') }}" class="btn btn-secondary">إلغاء</a>
