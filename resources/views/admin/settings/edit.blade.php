@extends('layouts.admin')
@section('title', 'إدارة الإعدادات العامة')

@push('styles')
<style>
    .slider-image-preview {
        position: relative;
        display: inline-block;
        margin: 5px;
        border: 1px solid #ddd;
        padding: 5px;
        border-radius: 4px;
    }
    .slider-image-preview img {
        max-width: 150px;
        max-height: 100px;
        display: block;
    }
    .slider-image-preview .delete-image {
        position: absolute;
        top: -10px;
        right: -10px;
        background-color: rgba(220, 53, 69, 0.8); /* Red with transparency */
        color: white;
        border: none;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        font-size: 12px;
        line-height: 25px;
        text-align: center;
        cursor: pointer;
        box-shadow: 0 0 5px rgba(0,0,0,0.2);
    }
     .slider-image-preview .delete-image:hover {
        background-color: #dc3545; /* Solid red */
    }
</style>
@endpush


@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة الإعدادات العامة</h1>
    </div>

    {{-- استخدام enctype="multipart/form-data" ضروري لرفع الملفات --}}
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PATCH') {{-- أو PUT --}}

        {{-- رسائل النجاح والخطأ العامة للنموذج --}}
        {{-- (يمكنك إبقاؤها أو إزالتها إذا كنت تعرض الأخطاء المخصصة فقط) --}}
        @if(session('success') && !session('google_calendar_success')) {{-- لا تعرض رسالة الحفظ العامة إذا كانت هناك رسالة خاصة بـ GCal --}}
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any() && !session('google_calendar_error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>حدث خطأ!</strong> يرجى مراجعة الحقول التالية:
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
            </div>
        @endif

        {{-- قسم سياسة الحجز --}}
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-file-contract me-2"></i>سياسة الحجز</h6>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10 col-lg-8">
                        <div class="mb-3">
                            <label for="policy_ar" class="form-label">سياسة الحجز (بالعربية):</label>
                            <textarea name="policy_ar" id="policy_ar" rows="10" class="form-control @error('policy_ar') is-invalid @enderror">{{ old('policy_ar', $settings['policy_ar'] ?? '') }}</textarea>
                            @error('policy_ar') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- قسم إعدادات الصفحة الرئيسية المرئية --}}
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-desktop me-2"></i>إعدادات الصفحة الرئيسية</h6>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10 col-lg-8">
                        <div class="mb-3">
                            <label for="homepage_logo_path" class="form-label">شعار الموقع (يظهر في الهيدر):</label>
                            <input type="file" name="homepage_logo_path" id="homepage_logo_path" class="form-control @error('homepage_logo_path') is-invalid @enderror">
                            <small class="form-text text-muted">اتركه فارغاً إذا لم ترغب في تغييره. يفضل أن يكون بخلفية شفافة (PNG).</small>
                            @if(isset($settings['homepage_logo_path']) && $settings['homepage_logo_path'])
                                <div class="mt-2">
                                    <img src="{{ asset($settings['homepage_logo_path']) }}" alt="الشعار الحالي" style="max-height: 50px; background-color: #f0f0f0; padding: 5px;">
                                </div>
                            @endif
                            @error('homepage_logo_path') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <hr>
                         <div class="mb-3">
                            <label for="logo_path_dark" class="form-label">شعار الموقع (للوحة التحكم - يظهر في الأعلى):</label>
                            <input type="file" name="logo_path_dark" id="logo_path_dark" class="form-control @error('logo_path_dark') is-invalid @enderror">
                            <small class="form-text text-muted">اتركه فارغاً إذا لم ترغب في تغييره. يفضل أن يكون بخلفية شفافة (PNG).</small>
                            @if(isset($settings['logo_path_dark']) && $settings['logo_path_dark'])
                                <div class="mt-2">
                                    <img src="{{ asset($settings['logo_path_dark']) }}" alt="الشعار الداكن الحالي" style="max-height: 50px; background-color: #f0f0f0; padding: 5px;">
                                </div>
                            @endif
                            @error('logo_path_dark') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="homepage_slider_images" class="form-label">صور السلايدر في الصفحة الرئيسية:</label>
                            <input type="file" name="homepage_slider_images[]" id="homepage_slider_images" class="form-control @error('homepage_slider_images.*') is-invalid @enderror" multiple>
                            <small class="form-text text-muted">يمكنك اختيار عدة صور. الصور الجديدة ستُضاف إلى الصور الحالية.</small>
                            @error('homepage_slider_images.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

                            @if(isset($settings['homepage_slider_images']) && is_array($settings['homepage_slider_images']) && !empty($settings['homepage_slider_images']))
                                <div class="mt-3">
                                    <p class="mb-1">الصور الحالية في السلايدر:</p>
                                    <div class="d-flex flex-wrap">
                                        @foreach($settings['homepage_slider_images'] as $imagePath)
                                            @if($imagePath && is_string($imagePath))
                                            <div class="slider-image-preview">
                                                <img src="{{ asset($imagePath) }}" alt="صورة سلايدر">
                                                <label class="delete-image" title="حذف هذه الصورة">
                                                    <input type="checkbox" name="delete_slider_images[]" value="{{ $imagePath }}" style="display:none;">
                                                    <i class="fas fa-times"></i>
                                                </label>
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <small class="form-text text-muted">لتغيير ترتيب الصور أو حذفها نهائياً، يمكنك حذف جميع الصور الحالية وإعادة رفعها بالترتيب الجديد. (الصور المحددة للحذف أعلاه سيتم إزالتها عند الحفظ).</small>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0" id="sms_settings_card">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-sms me-2"></i>إعدادات حدود الرسائل النصية (SMS)</h6>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10 col-lg-8">
                        <div class="mb-3 row">
                            <label for="sms_monthly_limit" class="col-sm-5 col-form-label">الحد الأعلى للرسائل شهرياً:</label>
                            <div class="col-sm-7">
                                <input type="number" class="form-control @error('sms_monthly_limit') is-invalid @enderror"
                                       id="sms_monthly_limit" name="sms_monthly_limit"
                                       value="{{ old('sms_monthly_limit', $settings['sms_monthly_limit'] ?? 0) }}" min="0">
                                <small class="form-text text-muted">أدخل 0 لعدم وضع حد.</small>
                                @error('sms_monthly_limit') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <div class="col-sm-7 offset-sm-5">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="sms_stop_sending_on_limit" name="sms_stop_sending_on_limit"
                                           value="1" {{ old('sms_stop_sending_on_limit', ($settings['sms_stop_sending_on_limit'] ?? false) == true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sms_stop_sending_on_limit">
                                        إيقاف إرسال رسائل جديدة بعد تجاوز الحد الشهري
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-calendar-check me-2"></i>إعدادات الحجز والتواصل</h6>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10 col-lg-8">
                        <div class="mb-3 row">
                            <label for="months_available_for_booking" class="col-sm-5 col-form-label">عدد الأشهر المستقبلية المتاحة للحجز:<span class="text-danger">*</span></label>
                            <div class="col-sm-7">
                                <input type="number" name="months_available_for_booking" id="months_available_for_booking" class="form-control @error('months_available_for_booking') is-invalid @enderror" value="{{ old('months_available_for_booking', $settings['months_available_for_booking'] ?? 3) }}" min="1" max="12" required>
                                <small class="form-text text-muted">كم شهراً للأمام يمكن للعميل أن يرى ويحجز؟ (مثال: 3 أشهر).</small>
                                @error('months_available_for_booking') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <hr>
                         <div class="mb-3 row">
                             <label for="contact_whatsapp" class="col-sm-5 col-form-label">رقم الواتساب للتواصل:</label>
                             <div class="col-sm-7">
                                 <input type="text" name="contact_whatsapp" id="contact_whatsapp" dir="ltr" class="form-control @error('contact_whatsapp') is-invalid @enderror" value="{{ old('contact_whatsapp', $settings['contact_whatsapp'] ?? '') }}" placeholder="+966XXXXXXXXX">
                                 <small class="form-text text-muted">اختياري. يظهر للعملاء للتواصل عبر الواتساب.</small>
                                 @error('contact_whatsapp') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                             </div>
                         </div>
                         <hr>
                        <div class="mb-3 row">
                             <div class="col-sm-7 offset-sm-5">
                                 <div class="form-check form-switch">
                                     <input class="form-check-input" type="checkbox" name="reminder_notifications_enabled" id="reminder_notifications_enabled" value="1" {{ old('reminder_notifications_enabled', ($settings['reminder_notifications_enabled'] ?? false) == true) ? 'checked' : '' }}>
                                     <label class="form-check-label" for="reminder_notifications_enabled">
                                         تفعيل إشعارات التذكير بالمواعيد (للعملاء)
                                     </label>
                                 </div>
                                 <small class="form-text text-muted">إذا تم تفعيلها، سيرسل النظام تذكيراً للعميل قبل موعده بيوم.</small>
                             </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- زر الحفظ العام يجب أن يكون هنا، قبل نهاية النموذج --}}
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-success px-4">
                <i class="fas fa-save me-1"></i> حفظ جميع الإعدادات
            </button>
        </div>
    </form> {{-- نهاية النموذج العام للإعدادات --}}


    {{-- *** بداية: قسم ربط Google Calendar (خارج النموذج السابق) *** --}}
    <div class="card shadow-sm mt-4 mb-4 border-0" id="google_calendar_settings_card">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fab fa-google me-2"></i>ربط تقويم جوجل (Google Calendar)</h6>
        </div>
        <div class="card-body">
            @php
                $adminUser = Auth::user(); // المستخدم المدير الحالي
                $isGoogleCalendarLinked = $adminUser && method_exists($adminUser, 'hasGoogleCalendarAccess') && $adminUser->hasGoogleCalendarAccess();
            @endphp

            {{-- عرض رسائل الخطأ أو النجاح الخاصة بـ Google Calendar --}}
            @if (session('google_calendar_error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('google_calendar_error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('google_calendar_success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('google_calendar_success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($isGoogleCalendarLinked)
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">تم ربط حساب Google Calendar بنجاح!</h5>
                        <p class="mb-1">الحجوزات المؤكدة الجديدة سيتم مزامنتها تلقائيًا مع تقويمك.</p>
                        @if ($adminUser->google_calendar_id)
                            <p class="mb-0 small text-muted">التقويم المستخدم: <strong>{{ $adminUser->google_calendar_id === 'primary' ? 'التقويم الأساسي' : $adminUser->google_calendar_id }}</strong></p>
                        @endif
                    </div>
                </div>
                {{-- النموذج الخاص بإلغاء الربط --}}
                <form action="{{ route('admin.settings.google-calendar.disconnect') }}" method="POST" onsubmit="return confirm('هل أنت متأكد أنك تريد إلغاء ربط حساب Google Calendar؟ سيؤدي هذا إلى إيقاف مزامنة الحجوزات.');">
                    @csrf
                    {{-- لا حاجة لـ @method('DELETE') إذا كان المسار POST --}}
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-unlink me-1"></i> إلغاء ربط حساب Google Calendar
                    </button>
                </form>
            @else
                <p class="text-muted">
                    قم بربط حساب Google Calendar الخاص بك لمزامنة الحجوزات المؤكدة تلقائيًا. هذا يساعدك على تجنب التعارضات وتنظيم جدولك بشكل أفضل.
                </p>
                <a href="{{ route('admin.settings.google-calendar.connect') }}" class="btn btn-primary">
                    <i class="fab fa-google me-1"></i> ربط حساب Google Calendar
                </a>
                <p class="small text-muted mt-2">
                    <i class="fas fa-info-circle me-1"></i> سيتم توجيهك إلى Google لمنح الإذن اللازم للتطبيق للوصول إلى تقويمك وإدارة الأحداث.
                </p>
            @endif
        </div>
    </div>
    {{-- *** نهاية: قسم ربط Google Calendar *** --}}

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteCheckboxes = document.querySelectorAll('input[name="delete_slider_images[]"]');
    deleteCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const previewDiv = this.closest('.slider-image-preview');
            if (this.checked) {
                previewDiv.style.opacity = '0.5';
                previewDiv.style.border = '2px dashed red';
            } else {
                previewDiv.style.opacity = '1';
                previewDiv.style.border = '1px solid #ddd';
            }
        });
    });
});
</script>
@endpush