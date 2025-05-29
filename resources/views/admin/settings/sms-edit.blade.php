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
                                <option value="{{ $value }}" {{ ($settingsData['sms_default_provider'] ?? 'httpsms') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('sms_default_provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">اختر مزود الخدمة الافتراضي لإرسال الإشعارات العامة (تأكيد الحجز، تحديثات الحالة، إلخ).</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="sms_otp_provider" class="form-label">مزود الخدمة لرسائل التحقق (OTP) <span class="text-danger">*</span></label>
                        <select name="sms_otp_provider" id="sms_otp_provider" class="form-select @error('sms_otp_provider') is-invalid @enderror">
                             @foreach($availableProviders as $value => $label)
                                <option value="{{ $value }}" {{ ($settingsData['sms_otp_provider'] ?? 'httpsms') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('sms_otp_provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">اختر مزود الخدمة لإرسال رموز التحقق الثنائي (OTP) عند تسجيل الدخول أو التسجيل.</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- HTTPSMS.com Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #0d6efd;">
            <div class="card-header">
                <h5 class="mb-0"><img src="{{ asset('images/httpsms_logo.png') }}" alt="HTTPSMS" style="height: 20px; margin-left: 5px; vertical-align: middle;"> إعدادات HTTPSMS.com</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="httpsms_api_key" class="form-label">API Key</label>
                    <input type="text" class="form-control @error('httpsms_api_key') is-invalid @enderror" id="httpsms_api_key" name="httpsms_api_key" value="{{ old('httpsms_api_key', $settingsData['httpsms_api_key'] ?? '') }}">
                    @error('httpsms_api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="httpsms_sender_phone" class="form-label">رقم هاتف المرسل (Sender Phone)</label>
                    <input type="text" class="form-control @error('httpsms_sender_phone') is-invalid @enderror" id="httpsms_sender_phone" name="httpsms_sender_phone" value="{{ old('httpsms_sender_phone', $settingsData['httpsms_sender_phone'] ?? '') }}" placeholder="مثال: +9665XXXXXXXX أو المعرف الخاص بك">
                    @error('httpsms_sender_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- SMS Gateway App (Android) Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #198754;">
            <div class="card-header">
                 <h5 class="mb-0"><img src="{{ asset('images/android_logo.png') }}" alt="Android" style="height: 20px; margin-left: 5px; vertical-align: middle;"> إعدادات SMS Gateway App (Android)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">هذه الإعدادات مخصصة إذا كنت تستخدم تطبيق بوابة رسائل على هاتف أندرويد. قد تختلف الحقول المطلوبة بناءً على التطبيق المستخدم.</p>
                <div class="mb-3">
                    <label for="smsgateway_server_url" class="form-label">رابط خادم التطبيق (Server URL)</label>
                    <input type="url" class="form-control @error('smsgateway_server_url') is-invalid @enderror" id="smsgateway_server_url" name="smsgateway_server_url" value="{{ old('smsgateway_server_url', $settingsData['smsgateway_server_url'] ?? '') }}" placeholder="مثال: http://192.168.1.100:9090/sendsms">
                    @error('smsgateway_server_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="smsgateway_device_id" class="form-label">معرف الجهاز (Device ID) (إذا لزم الأمر)</label>
                    <input type="text" class="form-control @error('smsgateway_device_id') is-invalid @enderror" id="smsgateway_device_id" name="smsgateway_device_id" value="{{ old('smsgateway_device_id', $settingsData['smsgateway_device_id'] ?? '') }}">
                    @error('smsgateway_device_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="smsgateway_api_token" class="form-label">رمز API أو كلمة المرور (إذا لزم الأمر)</label>
                    <input type="password" class="form-control @error('smsgateway_api_token') is-invalid @enderror" id="smsgateway_api_token" name="smsgateway_api_token" value="{{ old('smsgateway_api_token', $settingsData['smsgateway_api_token'] ?? '') }}">
                    @error('smsgateway_api_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
