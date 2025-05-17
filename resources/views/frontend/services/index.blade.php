{{-- resources/views/frontend/services/index.blade.php --}}
@extends('layouts.app')

@section('title', 'خدمات التصوير')

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
{{-- يمكنك إضافة رابط FontAwesome هنا إذا لم يكن مُضمناً في layouts.app --}}
{{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> --}}
<style>
    /* تعريف الخط الأساسي */
    body {
        font-family: 'Tajawal', sans-serif !important;
        background-color: #f8f9fa;
        direction: rtl;
        text-align: right;
    }

    /* جعل جميع العناصر تستخدم خط Tajawal */
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div {
        font-family: 'Tajawal', sans-serif !important;
    }

    /* تصميم حاوية الصفحة */
    .services-page-wrapper {
        padding: 40px 0;
        min-height: calc(100vh - 150px); /* افترض أن ارتفاع الهيدر والفوتر حوالي 150px */
    }

    /* عنوان الصفحة */
    .services-header {
        text-align: center;
        margin-bottom: 40px;
        position: relative;
    }

    .services-title-main { /* تم تغيير اسم الكلاس لتمييزه */
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
        position: relative;
        display: inline-block;
        padding-bottom: 15px;
    }

    .services-title-main::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background-color: #555;
    }

    /* عنوان الفئة */
    .category-title-wrapper {
        margin-bottom: 25px;
        text-align: right;
    }

    .category-title {
        font-size: 22px;
        font-weight: 700;
        color: #333;
        position: relative;
        display: inline-block;
        padding-bottom: 8px;
        margin-bottom: 0;
    }

    .category-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 40px;
        height: 3px;
        background-color: #555; /* يمكنك استخدام لون أساسي من تصميمك هنا */
    }

    /* بطاقة الخدمة */
    .service-card {
        background-color: #fff;
        border-radius: 10px; /* تعديل الحواف قليلاً */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07); /* ظل أخف وأنعم */
        overflow: hidden;
        transition: transform 0.25s ease-in-out, box-shadow 0.25s ease-in-out;
        height: 100%;
        border: none;
        display: flex;
        flex-direction: column;
    }

    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    /* --- بداية: تعديلات وتعاريف جديدة للـ CSS --- */
    .service-card .card-img-top-placeholder { /* كلاس جديد لصورة الخدمة إذا لم تكن موجودة */
        height: 200px;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-size: 1.5rem; /* حجم أيقونة الصورة */
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }
    .service-card .card-img-top-placeholder i {
        font-size: 3rem; /* حجم الأيقونة البديلة للصورة */
    }

    .service-card .service-image { /* كلاس لصورة الخدمة الفعلية */
        height: 200px;
        width: 100%;
        object-fit: cover;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }
    /* --- نهاية: تعديلات وتعاريف جديدة للـ CSS --- */


    .service-card-header { /* هذا الكلاس لم يعد مستخدمًا في الهيكل الجديد المقترح للبطاقة */
        padding: 18px 20px;
        /* background-color: #f8f9fa; */ /* تم إزالته/دمجه في card-body */
        /* border-bottom: 1px solid #f0f0f0; */ /* تم إزالته */
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .service-title { /* كلاس لاسم الخدمة داخل البطاقة */
        font-size: 1.15rem; /* حجم الخط لاسم الخدمة */
        font-weight: 700;
        color: #343a40; /* لون أغمق قليلاً */
        margin-bottom: 0.5rem; /* تعديل الهامش السفلي */
        line-height: 1.4;
        /* يمكنك إضافة حد لعدد الأسطر إذا أردت */
        /* display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: calc(1.4em * 2); */ /* لضمان ارتفاع ثابت لسطرين */
    }

    /* تم إزالة .service-price من الهيدر، واستبداله بـ .service-price-box */

    .service-content { /* هذا سيكون جسم البطاقة الرئيسي الآن */
        padding: 1rem; /* تعديل الحشو ليكون متناسقًا */
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .service-description {
        margin-bottom: 1rem; /* تعديل الهامش */
        color: #555; /* تعديل لون النص */
        font-size: 0.9rem; /* تعديل حجم الخط */
        line-height: 1.6;
        flex-grow: 1; /* السماح للوصف بأخذ المساحة المتاحة */
    }

    /* --- بداية: الأنماط الجديدة للسعر والمدة --- */
    .service-price-box,
    .service-duration-box {
        padding: 0.6rem 0.85rem;
        border-radius: 0.3rem;
        font-size: 0.9rem;
        font-weight: 600; /* خط أثقل قليلاً */
        text-align: center;
        width: 100%; /* جعلها تأخذ كامل عرض البطاقة */
        box-sizing: border-box; /* لضمان أن الحشو والحدود لا تزيد العرض */
    }

    .service-price-box {
        background-color: rgba(135, 206, 250, 0.25); /* LightSkyBlue مع شفافية 25% */
        /* أو يمكنك استخدام: background-color: rgba(0, 123, 255, 0.1); لون أزرق Bootstrap مع شفافية */
        color: #005cbf; /* لون أزرق أغمق قليلاً للنص */
        border: 1px solid rgba(135, 206, 250, 0.4);
        margin-bottom: 0.75rem; /* هامش أسفل مستطيل السعر */
    }
    .service-price-box i {
        margin-left: 0.3rem; /* هامش يسار الأيقونة */
    }


    .service-duration-box { /* تم تعديل اسم الكلاس من .service-duration */
        background-color: rgba(108, 117, 125, 0.15); /* لون رمادي ثانوي من Bootstrap مع شفافية */
        color: #495057; /* لون نص أغمق */
        border: 1px solid rgba(108, 117, 125, 0.3);
        margin-bottom: 1rem; /* هامش أسفل مستطيل المدة */
    }
    .service-duration-box i {
        margin-left: 0.3rem; /* هامش يسار الأيقونة */
    }
    /* --- نهاية: الأنماط الجديدة للسعر والمدة --- */

    .duration-badge { /* هذا الكلاس لم يعد مستخدمًا في الهيكل الجديد للمدة */
        display: inline-block;
        background-color: #f0f0f0;
        color: #555;
        padding: 6px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }

    .service-button {
        width: 100%;
        padding: 10px 15px; /* تعديل الحشو */
        background-color: #007bff; /* لون Bootstrap الأساسي */
        color: white;
        border: none;
        border-radius: 0.3rem; /* نفس حواف المستطيلات الأخرى */
        font-weight: 600;
        font-size: 0.95rem; /* تعديل حجم الخط */
        text-align: center;
        transition: background-color 0.2s ease, transform 0.2s ease;
        text-decoration: none;
        display: inline-block; /* لضمان تطبيق الـ width بشكل صحيح */
    }

    .service-button:hover {
        background-color: #0056b3; /* لون أغمق عند المرور */
        transform: translateY(-1px);
        color: white; /* تأكيد لون النص عند المرور */
    }
    .service-button i {
        margin-left: 0.5rem;
    }


    /* تنسيقات عناصر HTML داخل الوصف */
    .service-description p { margin-bottom: 10px; }
    .service-description ul, .service-description ol { padding-right: 20px; margin-bottom: 10px; }
    .service-description li { margin-bottom: 5px; }
    .service-description strong, .service-description b { font-weight: 700; color: #444; }
    .service-description em, .service-description i { font-style: italic; }
    .service-description a { color: #007bff; text-decoration: none; }
    .service-description a:hover { text-decoration: underline; }
    .service-description h1, .service-description h2, .service-description h3,
    .service-description h4, .service-description h5, .service-description h6 {
        margin-top: 15px;
        margin-bottom: 10px;
        font-weight: 700;
        color: #333;
    }

    /* رسائل عدم وجود خدمات */
    .no-services-message {
        text-align: center;
        padding: 40px 20px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
    }

    .no-services-icon {
        font-size: 3rem;
        color: #adb5bd; /* لون أيقونة أفتح */
        margin-bottom: 15px;
    }

    .no-services-text {
        font-size: 1.1rem; /* تعديل حجم الخط */
        color: #6c757d;
    }

    /* تنسيقات التباعد بين الفئات */
    .category-section {
        margin-bottom: 45px; /* زيادة التباعد قليلاً */
    }

    .service-card-wrapper {
        margin-bottom: 30px;
    }

    /* تأثيرات الظهور */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); } /* تعديل قيمة translateY */
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: fadeIn 0.4s ease-out forwards; /* تعديل مدة وسرعة التأثير */
        opacity: 0;
    }

    /* تعديلات التوافق مع الجوال */
    @media (max-width: 767px) {
        .services-title-main {
            font-size: 24px;
        }
        .category-title {
            font-size: 20px;
        }
        .service-content {
            padding: 0.9rem;
        }
        .service-card-wrapper {
            margin-bottom: 20px;
        }
        .service-price-box,
        .service-duration-box {
            font-size: 0.85rem;
            padding: 0.5rem 0.7rem;
        }
        .service-button {
            font-size: 0.9rem;
            padding: 9px 12px;
        }
    }
</style>
@endsection

@section('content')
<div class="services-page-wrapper">
    <div class="container">
        <div class="services-header">
            {{-- تم تغيير اسم الكلاس هنا ليطابق الـ CSS --}}
            <h1 class="services-title-main">باقات التصوير المميزة</h1>
        </div>

        @if($categories->isEmpty() || $categories->flatMap->services->isEmpty())
            <div class="no-services-message fade-in">
                {{-- يمكنك إضافة أيقونة هنا إذا أردت --}}
                {{-- <div class="no-services-icon"><i class="fas fa-camera-retro"></i></div> --}}
                <p class="no-services-text">لا توجد خدمات متاحة للعرض حالياً. يرجى المحاولة لاحقاً.</p>
            </div>
        @else
            @foreach($categories as $category)
                @if($category->services->isNotEmpty())
                    <div class="category-section fade-in" style="animation-delay: {{ $loop->index * 0.1 }}s;">
                        <div class="category-title-wrapper">
                            <h2 class="category-title">{{ $category->name_ar }}</h2>
                        </div>

                        <div class="row">
                            @foreach($category->services as $service)
                                <div class="col-md-6 col-lg-4 service-card-wrapper fade-in" style="animation-delay: {{ ($loop->parent->index * ($categories->count()) + $loop->index) * 0.07 + 0.1 }}s;">
                                    <div class="service-card">
                                        {{-- بداية: هيكل البطاقة المعدل --}}
                                        @if($service->image_path)
                                            {{-- تأكد من أن لديك طريقة لجلب الـ URL الصحيح للصورة من storage --}}
                                            {{-- إذا كنت تستخدم storage link، سيكون Storage::url($service->image_path) --}}
                                            <img src="{{ asset('storage/' . $service->image_path) }}" class="service-image" alt="{{ $service->name_ar }}">
                                        @else
                                            <div class="card-img-top-placeholder">
                                                <i class="fas fa-camera"></i> {{-- أيقونة بديلة --}}
                                            </div>
                                        @endif

                                        <div class="service-content">
                                            <h3 class="service-title">{{ $service->name_ar }}</h3>

                                            @if($service->description_ar)
                                                <div class="service-description">
                                                    {!! Str::limit(strip_tags($service->description_ar), 120) !!} {{-- عرض جزء من الوصف --}}
                                                </div>
                                            @endif

                                            {{-- مستطيل السعر الجديد --}}
                                            <div class="service-price-box">
                                                <i class="fas fa-tags"></i> {{-- أيقونة للسعر --}}
                                                {{ toArabicDigits(number_format($service->price_sar, 0)) }} ريال
                                            </div>

                                            {{-- مستطيل ساعات التصوير الجديد --}}
                                            <div class="service-duration-box">
                                                <i class="far fa-clock"></i>
                                                {{ toArabicDigits($service->duration_hours ?? '0') }} ساعات تصوير
                                            </div>

                                            <a href="{{ route('booking.calendar', $service->id) }}" class="service-button mt-auto">
                                                <i class="fas fa-calendar-alt"></i> احجز الآن
                                            </a>
                                        </div>
                                        {{-- نهاية: هيكل البطاقة المعدل --}}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if(!$loop->last)
                        <div class="category-spacer" style="height: 30px;"></div>
                    @endif
                @endif
            @endforeach
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // تفعيل تأثيرات الظهور عند التمرير
        const fadeElements = document.querySelectorAll('.fade-in');

        if ("IntersectionObserver" in window) {
            const observer = new IntersectionObserver((entries, observerInstance) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                        observerInstance.unobserve(entry.target); // توقف عن مراقبة العنصر بعد ظهوره
                    }
                });
            }, { threshold: 0.1 }); // يظهر العنصر عندما يكون 10% منه مرئيًا

            fadeElements.forEach(element => {
                element.style.animationPlayState = 'paused'; // ابدأ والتأثير متوقف
                observer.observe(element);
            });
        } else {
            // إذا كان المتصفح لا يدعم IntersectionObserver، اظهر جميع العناصر مباشرة
            fadeElements.forEach(element => {
                element.style.opacity = 1;
                element.style.transform = 'translateY(0)';
            });
        }
    });
</script>
@endsection
