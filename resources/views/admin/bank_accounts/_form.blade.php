{{-- resources/views/admin/bank_accounts/_form.blade.php --}}

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

<div class="form-group mb-3"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
    <label for="bank_name_ar">اسم البنك (بالعربية) <span class="text-danger">*</span></label>
    <input type="text" name="bank_name_ar" id="bank_name_ar" class="form-control @error('bank_name_ar') is-invalid @enderror" value="{{ old('bank_name_ar', $bankAccount->bank_name_ar) }}" required>
    @error('bank_name_ar')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="bank_name_en">اسم البنك (بالإنجليزية)</label>
    <input type="text" name="bank_name_en" id="bank_name_en" class="form-control @error('bank_name_en') is-invalid @enderror" value="{{ old('bank_name_en', $bankAccount->bank_name_en) }}">
    @error('bank_name_en')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="account_name_ar">اسم صاحب الحساب (بالعربية) <span class="text-danger">*</span></label>
    <input type="text" name="account_name_ar" id="account_name_ar" class="form-control @error('account_name_ar') is-invalid @enderror" value="{{ old('account_name_ar', $bankAccount->account_name_ar) }}" required>
    @error('account_name_ar')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="account_name_en">اسم صاحب الحساب (بالإنجليزية)</label>
    <input type="text" name="account_name_en" id="account_name_en" class="form-control @error('account_name_en') is-invalid @enderror" value="{{ old('account_name_en', $bankAccount->account_name_en) }}">
    @error('account_name_en')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="account_number">رقم الحساب <span class="text-danger">*</span></label>
    <input type="text" name="account_number" id="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ old('account_number', $bankAccount->account_number) }}" required>
    @error('account_number')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="iban">رقم IBAN <span class="text-danger">*</span></label>
    <input type="text" name="iban" id="iban" class="form-control @error('iban') is-invalid @enderror" value="{{ old('iban', $bankAccount->iban) }}" required dir="ltr" style="text-align: left;"> {{-- Suggest LTR for IBAN --}}
    @error('iban')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-check form-switch mb-3"> {{-- استخدم كلاسات التنسيق المناسبة للـ checkbox --}}
    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" {{ old('is_active', $bankAccount->is_active) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">
        مفعل
    </label>
</div>

<button type="submit" class="btn btn-primary"> {{-- استخدم كلاسات التنسيق المناسبة لديك --}}
    {{ $bankAccount->exists ? 'تحديث الحساب' : 'إضافة الحساب' }}
</button>
<a href="{{ route('admin.bank-accounts.index') }}" class="btn btn-secondary">إلغاء</a> {{-- رابط للعودة --}}