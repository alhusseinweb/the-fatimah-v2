{{-- resources/views/admin/discount_codes/_form.blade.php --}}

{{-- Display validation errors --}}
@if ($errors->any())
    <div class="alert alert-danger"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Code --}}
<div class="form-group mb-3"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
    <label for="code">كود الخصم <span class="text-danger">*</span></label>
    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $discountCode->code) }}" required>
    @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Type --}}
<div class="form-group mb-3">
    <label for="type">نوع الخصم <span class="text-danger">*</span></label>
    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required> {{-- استخدم كلاس form-select أو ما يعادله --}}
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
    <input type="number" name="value" id="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value', $discountCode->value) }}" required step="0.01" min="0">
    <small class="form-text text-muted">أدخل القيمة كرقم (مثال: 10 لخصم 10% أو 50 لخصم 50 ريال).</small>
    @error('value')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Start Date --}}
<div class="form-group mb-3">
    <label for="start_date">تاريخ البدء <span class="text-danger">*</span></label>
    <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $discountCode->start_date ? $discountCode->start_date->format('Y-m-d') : '') }}" required>
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

{{-- Is Active --}}
<div class="form-check form-switch mb-3"> {{-- استخدم كلاسات التنسيق المناسبة للـ checkbox --}}
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $discountCode->is_active) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">
        مفعل
    </label>
</div>

{{-- Submit/Cancel Buttons --}}
<button type="submit" class="btn btn-primary"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
    {{ $discountCode->exists ? 'تحديث الكود' : 'إضافة الكود' }}
</button>
<a href="{{ route('admin.discount-codes.index') }}" class="btn btn-secondary">إلغاء</a>