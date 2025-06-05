@extends('layouts.admin')

@section('title', 'الإعدادات العامة والتكوين')

@push('styles')
<style>
    .card-header h5 { margin-bottom: 0; display: flex; align-items: center; }
    .card-header i.fas { margin-left: 0.5rem; }
    html[dir="ltr"] .card-header i.fas { margin-left: 0; margin-right: 0.5rem; }
    .nav-tabs .nav-link.active { background-color: #f8f9fa; border-bottom-color: #dee2e6; }
    .form-text { font-size: 0.8rem; }
    .image-preview-container { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
    .image-preview-item { position: relative; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
    .image-preview-item img { max-width: 100px; max-height: 100px; object-fit: cover; }
    .delete-image-btn { position: absolute; top: -5px; right: -5px; background-color: rgba(255, 0, 0, 0.7); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; line-height: 20px; text-align: center; cursor: pointer; padding: 0; }
     html[dir="ltr"] .delete-image-btn { right: auto; left: -5px; }
    .flash-highlight { animation: flash-animation 1s 2; }
    @keyframes flash-animation { 0% { background-color: transparent; } 50% { background-color: rgba(255, 255, 0, 0.3); } 100% { background-color: transparent; } }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">الإعدادات العامة والتكوين</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الإعدادات العامة</li>
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

    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-settings-tab" data-bs-toggle="tab" data-bs-target="#general-settings" type="button" role="tab" aria-controls="general-settings" aria-selected="true">
                    <i class="fas fa-cog me-1"></i>إعدادات الموقع الأساسية
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contact-settings-tab" data-bs-toggle="tab" data-bs-target="#contact-settings" type="button" role="tab" aria-controls="contact-settings" aria-selected="false">
                    <i class="fas fa-address-book me-1"></i>معلومات التواصل
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="booking-settings-tab" data-bs-toggle="tab" data-bs-target="#booking-settings" type="button" role="tab" aria-controls="booking-settings" aria-selected="false">
                    <i class="fas fa-calendar-check me-1"></i>إعدادات الحجز
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payment-settings-tab" data-bs-toggle="tab" data-bs-target="#payment-settings" type="button" role="tab" aria-controls="payment-settings" aria-selected="false">
                    <i class="fas fa-credit-card me-1"></i>إعدادات الدفع
                </button>
            </li>
             <li class="nav-item" role="presentation">
                <button class="nav-link" id="data-management-tab" data-bs-toggle="tab" data-bs-target="#data-management" type="button" role="tab" aria-controls="data-management" aria-selected="false">
                    <i class="fas fa-database me-1"></i>إدارة البيانات
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabsContent">
            <div class="tab-pane fade show active" id="general-settings" role="tabpanel" aria-labelledby="general-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> معلومات الموقع الأساسية</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="site_name_ar" class="form-label">اسم الموقع (عربي)</label>
                            <input type="text" class="form-control @error('site_name_ar') is-invalid @enderror" id="site_name_ar" name="site_name_ar" value="{{ old('site_name_ar', $settings['site_name_ar'] ?? config('app.name', 'Fatimah Booking')) }}">
                            @error('site_name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="site_name_en" class="form-label">اسم الموقع (إنجليزي)</label>
                            <input type="text" class="form-control @error('site_name_en') is-invalid @enderror" id="site_name_en" name="site_name_en" value="{{ old('site_name_en', $settings['site_name_en'] ?? '') }}">
                            @error('site_name_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="logo_light_file" class="form-label">الشعار (النسخة الفاتحة/الرئيسية)</label>
                                <input type="file" class="form-control @error('logo_light_file') is-invalid @enderror" id="logo_light_file" name="logo_light_file">
                                @if(isset($settings['logo_path_light']) && $settings['logo_path_light']) <img src="{{ asset($settings['logo_path_light']) }}" alt="الشعار الحالي" style="max-height: 50px; margin-top: 10px;"> @endif
                                @error('logo_light_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="logo_dark_file" class="form-label">الشعار (النسخة الداكنة - للقوائم)</label>
                                <input type="file" class="form-control @error('logo_dark_file') is-invalid @enderror" id="logo_dark_file" name="logo_dark_file">
                                @if(isset($settings['logo_path_dark']) && $settings['logo_path_dark']) <img src="{{ asset($settings['logo_path_dark']) }}" alt="الشعار الداكن الحالي" style="max-height: 50px; margin-top: 10px; background-color: #333; padding: 5px;"> @endif
                                @error('logo_dark_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="favicon_file" class="form-label">أيقونة الموقع (Favicon)</label>
                                <input type="file" class="form-control @error('favicon_file') is-invalid @enderror" id="favicon_file" name="favicon_file">
                                @if(isset($settings['favicon_path']) && $settings['favicon_path']) <img src="{{ asset($settings['favicon_path']) }}" alt="Favicon الحالي" style="max-height: 32px; margin-top: 10px;"> @endif
                                @error('favicon_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="slider_images" class="form-label">صور السلايدر في الصفحة الرئيسية</label>
                            <input type="file" class="form-control @error('slider_images.*') is-invalid @enderror" id="slider_images" name="slider_images[]" multiple>
                            <small class="form-text text-muted">يمكنك اختيار عدة صور. الصور الجديدة ستُضاف إلى الصور الحالية.</small>
                            @error('slider_images.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if(!empty($settings['homepage_slider_images']))
                                <div class="mt-2"><strong>الصور الحالية:</strong></div>
                                <div class="image-preview-container">
                                    @foreach($settings['homepage_slider_images'] as $imagePath)
                                        @if($imagePath)
                                        <div class="image-preview-item" id="slider-item-{{ md5($imagePath) }}">
                                            <img src="{{ asset($imagePath) }}" alt="صورة سلايدر">
                                            <button type="button" class="delete-image-btn" onclick="deleteSliderImage('{{ $imagePath }}', 'slider-item-{{ md5($imagePath) }}')">&times;</button>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                <input type="hidden" name="deleted_slider_images_json" id="deleted_slider_images_input" value="[]">
                            @endif
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" {{ old('maintenance_mode', $settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="maintenance_mode">تفعيل وضع الصيانة</label>
                            </div>
                            @error('maintenance_mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="contact-settings" role="tabpanel" aria-labelledby="contact-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-phone-alt"></i> معلومات التواصل الأساسية</h5>
                    </div>
                    <div class="card-body">
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label">البريد الإلكتروني للتواصل</label>
                                <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" value="{{ old('contact_email', $settings['contact_email'] ?? '') }}">
                                @error('contact_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_phone" class="form-label">رقم الهاتف للتواصل (اختياري)</label>
                                <input type="text" class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}">
                                @error('contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fab fa-whatsapp"></i> إعدادات الواتساب</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_whatsapp" class="form-label">رقم الواتساب (مع رمز الدولة)</label>
                            <input type="text" class="form-control @error('contact_whatsapp') is-invalid @enderror" id="contact_whatsapp" name="contact_whatsapp" value="{{ old('contact_whatsapp', $settings['contact_whatsapp'] ?? '') }}" placeholder="+9665XXXXXXXX">
                            @error('contact_whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="display_whatsapp_contact" name="display_whatsapp_contact" value="1" {{ old('display_whatsapp_contact', $settings['display_whatsapp_contact'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="display_whatsapp_contact">تفعيل عرض رابط الواتساب في الصفحة الرئيسية</label>
                            </div>
                            @error('display_whatsapp_contact') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fab fa-instagram"></i> إعدادات الإنستقرام</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_instagram_url" class="form-label">رابط حساب الإنستقرام</label>
                            <input type="url" class="form-control @error('contact_instagram_url') is-invalid @enderror" id="contact_instagram_url" name="contact_instagram_url" value="{{ old('contact_instagram_url', $settings['contact_instagram_url'] ?? '') }}" placeholder="https://www.instagram.com/username">
                            @error('contact_instagram_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="display_instagram_contact" name="display_instagram_contact" value="1" {{ old('display_instagram_contact', $settings['display_instagram_contact'] ?? '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="display_instagram_contact">تفعيل عرض رابط الإنستقرام في الصفحة الرئيسية</label>
                            </div>
                            @error('display_instagram_contact') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="booking-settings" role="tabpanel" aria-labelledby="booking-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-clock"></i> إعدادات الحجوزات والتوافر</h5>
                    </div>
                    <div class="card-body">
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="booking_availability_months" class="form-label">عدد الأشهر المتاحة للحجز مقدماً</label>
                                <input type="number" class="form-control @error('booking_availability_months') is-invalid @enderror" id="booking_availability_months" name="booking_availability_months" value="{{ old('booking_availability_months', $settings['booking_availability_months'] ?? '3') }}" min="1" max="24">
                                @error('booking_availability_months') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="booking_buffer_time" class="form-label">فترة الراحة بين المواعيد (بالدقائق)</label>
                                <input type="number" class="form-control @error('booking_buffer_time') is-invalid @enderror" id="booking_buffer_time" name="booking_buffer_time" value="{{ old('booking_buffer_time', $settings['booking_buffer_time'] ?? '0') }}" min="0">
                                @error('booking_buffer_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="outside_ahsa_fee" class="form-label">رسوم التصوير خارج الأحساء (ريال سعودي)</label>
                                <input type="number" step="0.01" class="form-control @error('outside_ahsa_fee') is-invalid @enderror" id="outside_ahsa_fee" name="outside_ahsa_fee" value="{{ old('outside_ahsa_fee', $settings['outside_ahsa_fee'] ?? '300.00') }}" min="0">
                                <small class="form-text text-muted">القيمة التي ستتم إضافتها على الفاتورة عند اختيار التصوير خارج الأحساء.</small>
                                @error('outside_ahsa_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="policy_ar" class="form-label">سياسة الحجز (عربي)</label>
                            <textarea class="form-control @error('policy_ar') is-invalid @enderror" id="policy_ar" name="policy_ar" rows="5">{{ old('policy_ar', $settings['policy_ar'] ?? '') }}</textarea>
                            @error('policy_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="policy_en" class="form-label">سياسة الحجز (إنجليزي - اختياري)</label>
                            <textarea class="form-control @error('policy_en') is-invalid @enderror" id="policy_en" name="policy_en" rows="5">{{ old('policy_en', $settings['policy_en'] ?? '') }}</textarea>
                            @error('policy_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="payment-settings" role="tabpanel" aria-labelledby="payment-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-money-check-alt"></i> خيارات الدفع العامة</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enable_bank_transfer" name="enable_bank_transfer" value="1" {{ old('enable_bank_transfer', $settings['enable_bank_transfer'] ?? '0') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="enable_bank_transfer">تفعيل خيار التحويل البنكي</label>
                                </div>
                                @error('enable_bank_transfer') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- --- MODIFICATION START: Bank Transfer Discount Popup Settings --- --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-piggy-bank me-1"></i> إعدادات نافذة خصم التحويل البنكي</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="enable_bank_transfer_discount_popup" name="enable_bank_transfer_discount_popup" value="1" {{ old('enable_bank_transfer_discount_popup', $settings['enable_bank_transfer_discount_popup'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="enable_bank_transfer_discount_popup">تفعيل ظهور نافذة خصم التحويل البنكي للعميل</label>
                            </div>
                            <small class="form-text text-muted">إذا تم تفعيل هذا الخيار، ستظهر نافذة منبثقة للعميل عند اختيار التحويل البنكي إذا كانت الرسالة وكود الخصم مُدخلين أدناه.</small>
                            @error('enable_bank_transfer_discount_popup') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_code" class="form-label">كود الخصم الخاص بالتحويل البنكي</label>
                            <input type="text" class="form-control @error('bank_transfer_discount_code') is-invalid @enderror" id="bank_transfer_discount_code" name="bank_transfer_discount_code" value="{{ old('bank_transfer_discount_code', $settings['bank_transfer_discount_code'] ?? '') }}" placeholder="مثال: BANKD15">
                            <small class="form-text text-muted">أدخل كود الخصم الذي سيتم تطبيقه تلقائيًا. تأكد أن هذا الكود مُعرَّف وصالح في قسم "أكواد الخصم" وأن شروطه (مثل طريقة الدفع) تتوافق مع "تحويل بنكي".</small>
                            @error('bank_transfer_discount_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_popup_message_ar" class="form-label">رسالة نافذة خصم التحويل البنكي (عربي)</label>
                            <textarea class="form-control @error('bank_transfer_discount_popup_message_ar') is-invalid @enderror" id="bank_transfer_discount_popup_message_ar" name="bank_transfer_discount_popup_message_ar" rows="3">{{ old('bank_transfer_discount_popup_message_ar', $settings['bank_transfer_discount_popup_message_ar'] ?? 'لا تفوت الفرصة! استخدم كود الخصم الخاص بالتحويل البنكي.') }}</textarea>
                            <small class="form-text text-muted">هذه الرسالة ستظهر للعميل في النافذة المنبثقة.</small>
                            @error('bank_transfer_discount_popup_message_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_popup_message_en" class="form-label">رسالة نافذة خصم التحويل البنكي (إنجليزي - اختياري)</label>
                            <textarea class="form-control @error('bank_transfer_discount_popup_message_en') is-invalid @enderror" id="bank_transfer_discount_popup_message_en" name="bank_transfer_discount_popup_message_en" rows="3">{{ old('bank_transfer_discount_popup_message_en', $settings['bank_transfer_discount_popup_message_en'] ?? '') }}</textarea>
                            @error('bank_transfer_discount_popup_message_en') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                {{-- --- MODIFICATION END --- --}}

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><img src="{{ asset('images/tamara.png') }}" alt="Tamara" style="height: 20px; margin-left: 8px; vertical-align: middle;">إعدادات بوابة الدفع تمارا (Tamara)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="tamara_enabled" name="tamara_enabled" value="1" {{ old('tamara_enabled', $settings['tamara_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="tamara_enabled">تفعيل الدفع عبر تمارا</label>
                                </div>
                                @error('tamara_enabled') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tamara_api_url" class="form-label">رابط API الخاص بتمارا (Tamara API URL)</label>
                            <input type="url" class="form-control @error('tamara_api_url') is-invalid @enderror" id="tamara_api_url" name="tamara_api_url" value="{{ old('tamara_api_url', $settings['tamara_api_url'] ?? '') }}" placeholder="https://api.tamara.co أو https://api-sandbox.tamara.co">
                            @error('tamara_api_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-text text-muted">عادة ما يكون للبيئة التجريبية (sandbox) والبيئة الحية (production).</small>
                        </div>
                        <div class="mb-3">
                            <label for="tamara_api_token" class="form-label">توكن API الخاص بتمارا (Tamara API Token)</label>
                            <input type="text" class="form-control @error('tamara_api_token') is-invalid @enderror" id="tamara_api_token" name="tamara_api_token" value="{{ old('tamara_api_token', $settings['tamara_api_token'] ?? '') }}">
                            @error('tamara_api_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="tamara_notification_token" class="form-label">توكن إشعارات الويب هوك (Tamara Notification Token)</label>
                            <input type="text" class="form-control @error('tamara_notification_token') is-invalid @enderror" id="tamara_notification_token" name="tamara_notification_token" value="{{ old('tamara_notification_token', $settings['tamara_notification_token'] ?? '') }}">
                            @error('tamara_notification_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="form-text text-muted">يستخدم للتحقق من صحة طلبات الويب هوك الواردة من تمارا.</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tamara_webhook_verification_bypass" name="tamara_webhook_verification_bypass" value="1" {{ old('tamara_webhook_verification_bypass', $settings['tamara_webhook_verification_bypass'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="tamara_webhook_verification_bypass">تجاوز التحقق من توقيع الويب هوك لتمارا (لأغراض التطوير)</label>
                            </div>
                            <small class="form-text text-muted">تفعيل هذا الخيار سيتجاوز التحقق من صحة توقيع إشعارات الويب هوك من تمارا. **لا ينصح بتفعيله في البيئة الحية.**</small>
                            @error('tamara_webhook_verification_bypass') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="data-management" role="tabpanel" aria-labelledby="data-management-tab">
                 <div class="card shadow-sm mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>منطقة الخطر</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-danger">
                            <strong>تحذير شديد:</strong> الإجراءات في هذا القسم خطيرة جداً وقد تؤدي إلى فقدان دائم للبيانات. يرجى توخي الحذر الشديد والمتابعة فقط إذا كنت متأكداً تماماً مما تفعله. **يوصى بشدة بأخذ نسخة احتياطية كاملة من قاعدة البيانات قبل تنفيذ أي من هذه الإجراءات.**
                        </p>
                        <hr>
                        <div>
                            <h6>حذف جميع الحجوزات والفواتير والمدفوعات</h6>
                            <p>سيقوم هذا الإجراء بحذف **جميع** سجلات الحجوزات، و**جميع** الفواتير المرتبطة بها، و**جميع** المدفوعات المسجلة بشكل نهائي من قاعدة البيانات. <strong>لا يمكن التراجع عن هذا الإجراء بأي شكل من الأشكال.</strong></p>
                            <button type="button" class="btn btn-danger" id="deleteAllDataBtn">
                                <i class="fas fa-trash-alt me-1"></i> حذف جميع الحجوزات والفواتير والمدفوعات الآن
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-1"></i> حفظ جميع الإعدادات
            </button>
        </div>
    </form>
    <form id="deleteAllDataForm" action="{{ route('admin.data.delete_all_bookings') }}" method="POST" style="display: none;">
        @csrf
    </form>
</div>
@endsection

@push('scripts')
<script>
    let deletedSliderImagesArray = [];
    const deletedSliderImagesInput = document.getElementById('deleted_slider_images_input');

    function deleteSliderImage(imagePath, elementId) {
        if (confirm('هل أنت متأكد من حذف هذه الصورة من السلايدر؟')) {
            const itemToRemove = document.getElementById(elementId);
            if (itemToRemove) {
                itemToRemove.style.display = 'none';
            }
            if (!deletedSliderImagesArray.includes(imagePath)) {
                deletedSliderImagesArray.push(imagePath);
            }
            if(deletedSliderImagesInput) {
                deletedSliderImagesInput.name = 'deleted_slider_images_json'; // تأكد أن هذا الحقل مُرسل
                deletedSliderImagesInput.value = JSON.stringify(deletedSliderImagesArray);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var hash = window.location.hash;
        if (hash) {
            var triggerElTab = document.querySelector('.nav-tabs button[data-bs-target="' + hash + '"]');
            if (!triggerElTab) { 
                var cardElement = document.getElementById(hash.substring(1));
                if (cardElement) {
                    var tabPane = cardElement.closest('.tab-pane');
                    if (tabPane) {
                        triggerElTab = document.querySelector('.nav-tabs button[data-bs-target="#' + tabPane.id + '"]');
                    }
                }
            }
            if (triggerElTab) {
                var tab = new bootstrap.Tab(triggerElTab);
                tab.show();
                setTimeout(function() {
                    var elementToScroll = document.getElementById(hash.substring(1));
                    if (elementToScroll) {
                        elementToScroll.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        elementToScroll.classList.add('flash-highlight');
                        setTimeout(() => elementToScroll.classList.remove('flash-highlight'), 2000);
                    }
                }, 200);
            }
        }

        const deleteAllDataBtn = document.getElementById('deleteAllDataBtn');
        const deleteAllDataForm = document.getElementById('deleteAllDataForm');

        if (deleteAllDataBtn && deleteAllDataForm) {
            deleteAllDataBtn.addEventListener('click', function(event) {
                event.preventDefault(); 
                const firstConfirm = confirm(
                    "تحذير!\n\n" +
                    "هل أنت متأكد أنك تريد حذف جميع الحجوزات، وجميع الفواتير المرتبطة بها، وجميع المدفوعات المسجلة؟\n\n" +
                    "*** هذه العملية لا يمكن التراجع عنها! ***"
                );
                
                if (firstConfirm) {
                    const secondConfirmText = "تأكيد أخير (للمرة الثانية):\n\n" +
                                            "أنت على وشك حذف كل بيانات الحجوزات والفواتير والمدفوعات بشكل نهائي.\n" +
                                            "لن تتمكن من استرجاع هذه البيانات بعد الحذف.\n\n" +
                                            "اكتب كلمة 'تأكيد الحذف' في المربع أدناه للمتابعة:";
                    const userInput = prompt(secondConfirmText);

                    if (userInput !== null && userInput.trim().toLowerCase() === 'تأكيد الحذف'.toLowerCase()) {
                        const thirdConfirm = confirm(
                            "تأكيد نهائي (للمرة الثالثة والأخيرة):\n\n" +
                            "بضغطك على 'OK'، سيتم حذف جميع البيانات المحددة نهائياً.\n" +
                            "هل أنت متأكد بشكل لا رجعة فيه؟"
                        );

                        if (thirdConfirm) {
                            deleteAllDataBtn.disabled = true;
                            deleteAllDataBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الحذف...';
                            deleteAllDataForm.submit();
                        } else {
                            alert('تم إلغاء عملية الحذف.');
                        }
                    } else if (userInput !== null) { 
                        alert('النص المدخل غير مطابق لـ "تأكيد الحذف". تم إلغاء العملية.');
                    } else { 
                        alert('تم إلغاء عملية الحذف.');
                    }
                } else {
                    alert('تم إلغاء عملية الحذف.');
                }
            });
        }
    });
</script>
@endpush
