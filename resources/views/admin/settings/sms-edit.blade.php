@extends('layouts.admin')

@section('title', 'إعدادات الرسائل النصية (SMS)')

@push('styles')
<style>
    .provider-card {
        border-left: 3px solid #007bff; /* تمييز لبطاقة المزود */
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
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.settings.sms.update') }}" method="POST">
        @csrf
        @method('PATCH')

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">اختيار مزود الخدمة الرئيسي</h5>
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
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #007bff;">
            <div class="card-header">
                <h5 class="mb-0"><img src="{{ asset('images/httpsms_logo.png') }}" alt="HTTPSMS" style="height: 20px; margin-left: 5px;"> إعدادات HTTPSMS.com</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="httpsms_api_key" class="form-label">API Key</label>
                    <input type="text" class="form-control @error('httpsms_api_key') is-invalid @enderror" id="httpsms_api_key" name="httpsms_api_key" value="{{ old('httpsms_api_key', $settingsData['httpsms_api_key'] ?? '') }}">
                    @error('httpsms_api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="httpsms_sender_phone" class="form-label">رقم هاتف المرسل (Sender Phone)</label>
                    <input type="text" class="form-control @error('httpsms_sender_phone') is-invalid @enderror" id="httpsms_sender_phone" name="httpsms_sender_phone" value="{{ old('httpsms_sender_phone', $settingsData['httpsms_sender_phone'] ?? '') }}" placeholder="مثال: +9665XXXXXXXX">
                    @error('httpsms_sender_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- SMS Gateway App (Android) Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #28a745;">
            <div class="card-header">
                 <h5 class="mb-0"><img src="{{ asset('images/android_logo.png') }}" alt="Android" style="height: 20px; margin-left: 5px;"> إعدادات SMS Gateway App (Android)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">هذه الإعدادات مخصصة إذا كنت تستخدم تطبيق بوابة رسائل على هاتف أندرويد (مثل <a href="https://smsgateway.me/" target="_blank">smsgateway.me</a> أو ما شابه). قد تختلف الحقول المطلوبة بناءً على التطبيق المستخدم.</p>
                <div class="mb-3">
                    <label for="smsgateway_server_url" class="form-label">رابط خادم التطبيق (Server URL / Base URL)</label>
                    <input type="url" class="form-control @error('smsgateway_server_url') is-invalid @enderror" id="smsgateway_server_url" name="smsgateway_server_url" value="{{ old('smsgateway_server_url', $settingsData['smsgateway_server_url'] ?? '') }}" placeholder="مثال: http://your-android-ip:port/">
                    @error('smsgateway_server_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="smsgateway_device_id" class="form-label">معرف الجهاز (Device ID) (إذا لزم الأمر)</label>
                    <input type="text" class="form-control @error('smsgateway_device_id') is-invalid @enderror" id="smsgateway_device_id" name="smsgateway_device_id" value="{{ old('smsgateway_device_id', $settingsData['smsgateway_device_id'] ?? '') }}">
                    @error('smsgateway_device_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="smsgateway_api_token" class="form-label">رمز API أو كلمة المرور (API Token/Password) (إذا لزم الأمر)</label>
                    <input type="text" class="form-control @error('smsgateway_api_token') is-invalid @enderror" id="smsgateway_api_token" name="smsgateway_api_token" value="{{ old('smsgateway_api_token', $settingsData['smsgateway_api_token'] ?? '') }}">
                    @error('smsgateway_api_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                 <small class="form-text text-muted">ملاحظة: القناة الفعلية (`AndroidSmsGatewayChannel.php`) المذكورة في تقاريرك السابقة قد تحتاج إلى تعديل لتستخدم هذه الإعدادات من قاعدة البيانات.</small>
            </div>
        </div>

        {{-- Twilio Settings --}}
        <div class="card shadow-sm mb-4 provider-card" style="border-left-color: #f0193c;">
            <div class="card-header">
                <h5 class="mb-0"><img src="{{ asset('images/twilio_logo.png') }}" alt="Twilio" style="height: 20px; margin-left: 5px;"> إعدادات Twilio (Programmable SMS)</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="twilio_account_sid" class="form-label">Account SID</label>
                    <input type="text" class="form-control @error('twilio_account_sid') is-invalid @enderror" id="twilio_account_sid" name="twilio_account_sid" value="{{ old('twilio_account_sid', $settingsData['twilio_account_sid'] ?? '') }}">
                    @error('twilio_account_sid') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="twilio_auth_token" class="form-label">Auth Token</label>
                    <input type="text" class="form-control @error('twilio_auth_token') is-invalid @enderror" id="twilio_auth_token" name="twilio_auth_token" value="{{ old('twilio_auth_token', $settingsData['twilio_auth_token'] ?? '') }}">
                    @error('twilio_auth_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="twilio_sms_from_number" class="form-label">رقم Twilio المرسل (From Number)</label>
                    <input type="text" class="form-control @error('twilio_sms_from_number') is-invalid @enderror" id="twilio_sms_from_number" name="twilio_sms_from_number" value="{{ old('twilio_sms_from_number', $settingsData['twilio_sms_from_number'] ?? '') }}" placeholder="مثال: +1234567890">
                    @error('twilio_sms_from_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                 <small class="form-text text-muted">ملاحظة: هذه الإعدادات لخدمة Twilio Programmable SMS (لإرسال رسائل عادية أو OTP مخصص). إذا كنت تستخدم Twilio Verify سابقاً، فهذه إعدادات مختلفة.</small>
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
