@extends('layouts.admin')

@section('title', 'إعدادات الرسائل النصية (SMS)')

@push('styles')
<style>
    .provider-card {
        border-left: 3px solid #007bff; 
    }
    .provider-card .card-header {
        background-color: #f8f9fa;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">إدارة إعدادات الرسائل النصية (SMS)</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.settings.edit') }}">الإعدادات العامة</a></li>
                        <li class="breadcrumb-item active">إعدادات SMS</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('admin.settings.sms.update') }}" method="POST">
        @csrf
        @method('PATCH')

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">اختيار مزود الخدمة الرئيسي</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="sms_default_provider" class="form-label">مزود الخدمة للرسائل العامة <span class="text-danger">*</span></label>
                        <select name="sms_default_provider" id="sms_default_provider" class="form-select @error('sms_default_provider') is-invalid @enderror">
                            @foreach($availableProviders as $value => $label)
                                <option value="{{ $value }}" {{ ($settingsData['sms_default_provider'] ?? 'whatsapp') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('sms_default_provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">اختر مزود الخدمة الافتراضي لجميع الإشعارات.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="sms_otp_provider" class="form-label">مزود الخدمة لرسائل التحقق (OTP) <span class="text-danger">*</span></label>
                        <select name="sms_otp_provider" id="sms_otp_provider" class="form-select @error('sms_otp_provider') is-invalid @enderror">
                             @foreach($availableProviders as $value => $label)
                                <option value="{{ $value }}" {{ ($settingsData['sms_otp_provider'] ?? 'whatsapp') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('sms_otp_provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">اختر مزود الخدمة لإرسال رموز التحقق (OTP).</small>
                    </div>
                </div>
            </div>
        </div>
        {{-- Twilio Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #dc3545;">
            <div class="card-header">
                <h5 class="mb-0"><img src="{{ asset('images/twilio_logo.png') }}" alt="Twilio" style="height: 20px; margin-left: 5px; vertical-align: middle;"> إعدادات Twilio Verify</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="twilio_account_sid" class="form-label">Account SID</label>
                    <input type="text" class="form-control @error('twilio_account_sid') is-invalid @enderror" id="twilio_account_sid" name="twilio_account_sid" value="{{ old('twilio_account_sid', $settingsData['twilio_account_sid'] ?? '') }}" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('twilio_account_sid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="twilio_auth_token" class="form-label">Auth Token</label>
                    <input type="password" class="form-control @error('twilio_auth_token') is-invalid @enderror" id="twilio_auth_token" name="twilio_auth_token" value="{{ old('twilio_auth_token', $settingsData['twilio_auth_token'] ?? '') }}">
                    @error('twilio_auth_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                {{-- --- MODIFICATION START: Change From Number to Verify Service SID --- --}}
                <div class="mb-3">
                    <label for="twilio_verify_sid" class="form-label">Verify Service SID</label>
                    <input type="text" class="form-control @error('twilio_verify_sid') is-invalid @enderror" id="twilio_verify_sid" name="twilio_verify_sid" value="{{ old('twilio_verify_sid', $settingsData['twilio_verify_sid'] ?? '') }}" placeholder="VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('twilio_verify_sid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="form-text text-muted">هذا هو "Service SID" من خدمة Twilio Verify، وليس رقم الهاتف المرسل.</small>
                </div>
                {{-- --- MODIFICATION END --- --}}
            </div>
        </div>

        {{-- WhatsApp / Green API Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #25D366;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fab fa-whatsapp me-1 text-success"></i> إعدادات WhatsApp (Green API)
                </h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" value="1" {{ ($settingsData['whatsapp_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="whatsapp_enabled">تفعيل الواتساب</label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="whatsapp_green_api_id_instance" class="form-label">ID Instance</label>
                    <input type="text" class="form-control @error('whatsapp_green_api_id_instance') is-invalid @enderror" id="whatsapp_green_api_id_instance" name="whatsapp_green_api_id_instance" value="{{ old('whatsapp_green_api_id_instance', $settingsData['whatsapp_green_api_id_instance'] ?? '') }}" placeholder="مثال: 1101XXXXXX">
                    @error('whatsapp_green_api_id_instance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="whatsapp_green_api_api_token_instance" class="form-label">API Token Instance</label>
                    <input type="password" class="form-control @error('whatsapp_green_api_api_token_instance') is-invalid @enderror" id="whatsapp_green_api_api_token_instance" name="whatsapp_green_api_api_token_instance" value="{{ old('whatsapp_green_api_api_token_instance', $settingsData['whatsapp_green_api_api_token_instance'] ?? '') }}">
                    @error('whatsapp_green_api_api_token_instance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="alert alert-info border-0 shadow-none mb-0">
                    <p class="mb-0 small"><i class="fas fa-info-circle me-1"></i> يتم استخدام Green API لإرسال التنبيهات عبر الواتساب بدلاً من الرسائل النصية التقليدية في حال تفعيل الخيار أعلاه.</p>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> حفظ الإعدادات
            </button>
            <a href="{{ route('admin.settings.edit') }}" class="btn btn-outline-secondary">العودة للإعدادات العامة</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
{{-- أي سكريبتات خاصة بالصفحة يمكن إضافتها هنا --}}
@endpush
