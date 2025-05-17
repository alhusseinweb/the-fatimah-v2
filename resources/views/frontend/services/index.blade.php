{{-- resources/views/frontend/services/index.blade.php --}}
@extends('layouts.app')

@section('title', 'خدمات التصوير')

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
{{-- قد تحتاج إلى تضمين FontAwesome هنا إذا لم يكن موجودًا في layouts.app --}}
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
        min-height: calc(100vh - 150px);
    }

    /* عنوان الصفحة */
    .services-header {
        text-align: center;
        margin-bottom: 40px;
        position: relative;
    }

    .services-title-main { /* تم تغيير اسم الكلاس هنا ليتناسب مع CSS */
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
        position: relative;
        display: inline-block;
        padding-bottom: 15px;
    }

    .services-title-main::after { /* تم تغيير اسم الكلاس هنا ليتناسب مع CSS */
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
        background-color: #555;
    }

    /* بطاقة الخدمة */
    .service-card {
        background-color: #fff;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        transition: all 0.3s ease;
        height: 100%;
        border: none;
        display: flex;
        flex-direction: column;
    }

    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .service-card-header {
        padding: 18px 20px;
        background-color: #f8f9fa;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .service-title { /* كلاس لاسم الخدمة داخل الهيدر */
        font-size: 18px;
        font-weight: 700;
        color: #333;
        margin: 0;
    }

    /* .service-price (القديم) - سيتم استبداله بـ .service-price-box-new داخل .service-content */
    /* .service-price {
        background-color: #555;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    } */

    .service-content {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .service-description {
        margin-bottom: 20px;
        color: #666;
        font-size: 15px;
        line-height: 1.6;
         /* تمت إضافة flex-grow للسماح للوصف بأخذ المساحة المتاحة ودفع العناصر السفلية لأسفل */
        flex-grow: 1;
    }

    /* --- بداية: الأنماط الجديدة للسعر والمدة --- */
    .service-price-box-new { /* اسم كلاس جديد لتجنب التعارض */
        padding: 0.6rem 0.85rem;
        border-radius: 0.3rem;
        font-size: 0.95rem; /* حجم خط أكبر قليلاً للسعر */
        font-weight: 700; /* خط أثقل للسعر */
        text-align: center;
        width: 100%;
        box-sizing: border-box;
        background-color: rgba(135, 206, 250, 0.2); /* LightSkyBlue مع شفافية 20% */
        color: #005cbf; /* لون أزرق أغمق قليلاً للنص */
        border: 1px solid rgba(135, 206, 250, 0.35);
        margin-bottom: 0.75rem; /* هامش أسفل مستطيل السعر */
    }
    .service-price-box-new i {
        margin-left: 0.4rem; /* هامش يسار الأيقونة */
    }

    .service-duration-original { /* كلاس جديد للاحتفاظ بنمط المدة الأصلي إذا أردت */
        margin-top: auto; /* هذا يدفعها لأسفل إذا كان الوصف قصيرًا، لكننا نريده تحت السعر الآن */
        margin-bottom: 15px;
        text-align: center; /* تم تغييره إلى توسيط */
        width: 100%; /* جعلها تأخذ كامل عرض البطاقة */
        padding: 0.6rem 0.85rem; /* نفس حشو السعر */
        border-radius: 0.3rem; /* نفس حواف السعر */
        background-color: rgba(108, 117, 125, 0.15); /* لون رمادي ثانوي شفاف */
        color: #495057; /* لون نص أغمق */
        box-sizing: border-box;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .service-duration-original i {
        margin-left: 0.4rem; /* هامش يسار الأيقونة */
    }
    /* --- نهاية: الأنماط الجديدة للسعر والمدة --- */


    .duration-badge { /* هذا الكلاس إذا كان لا يزال مستخدماً في مكان ما */
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
        padding: 12px;
        background-color: #555;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        text-decoration: none;
        margin-top: auto; /* يدفع الزر لأسفل البطاقة */
    }

    .service-button:hover {
        background-color: #444;
        transform: translateY(-2px);
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        color: white;
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
    .service-description a { color: #555; text-decoration: underline; }
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
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .no-services-icon {
        font-size: 3rem;
        color: #aaa;
        margin-bottom: 15px;
    }

    .no-services-text {
        font-size: 1.2rem;
        color: #666;
    }

    /* تنسيقات التباعد بين الفئات */
    .category-section {
        margin-bottom: 40px;
    }

    .service-card-wrapper {
        margin-bottom: 30px;
    }

    /* تأثيرات الظهور */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: fadeIn 0.5s ease forwards;
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

        .service-card-header { /* لم يعد مستخدمًا في الهيكل الجديد */
            padding: 15px;
        }

        .service-title { /* اسم الخدمة داخل البطاقة */
            font-size: 1rem; /* تعديل حجم الخط ليناسب الهاتف */
        }

        .service-content {
            padding: 15px;
        }

        .service-card-wrapper {
            margin-bottom: 20px;
        }

        .service-price-box-new,
        .service-duration-original {
            font-size: 0.85rem;
        }
    }
</style>
@endsection

@section('content')
<div class="services-page-wrapper">
    <div class="container">
        <div class="services-header">
            {{-- تم تغيير اسم الكلاس هنا ليتناسب مع CSS --}}
            <h1 class="services-title-main">باقات التصوير المميزة</h1>
        </div>

        @if($categories->isEmpty() || $categories->flatMap->services->isEmpty())
            <div class="no-services-message fade-in">
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
                                        {{-- الهيكل القديم لرأس البطاقة، سنقوم بدمج السعر والاسم بشكل مختلف --}}
                                        {{-- <div class="service-card-header">
                                            <h3 class="service-title">{{ $service->name_ar }}</h3>
                                            <span class="service-price">{{ toArabicDigits(number_format($service->price_sar, 0)) }} ريال</span>
                                        </div> --}}

                                        {{-- يمكنك إضافة صورة الخدمة هنا إذا أردت، على سبيل المثال: --}}
                                        {{-- @if($service->image_path)
                                            <img src="{{ asset('storage/' . $service->image_path) }}" class="card-img-top" alt="{{ $service->name_ar }}" style="height: 200px; object-fit: cover;">
                                        @else
                                            <div style="height: 200px; background-color: #e9ecef; display:flex; align-items:center; justify-content:center; color:#6c757d;">
                                                <i class="fas fa-camera" style="font-size: 3rem;"></i>
                                            </div>
                                        @endif --}}


                                        <div class="service-content">
                                            {{-- اسم الخدمة أولاً --}}
                                            <h3 class="service-title" style="text-align: center; margin-bottom: 1rem;">{{ $service->name_ar }}</h3>

                                            @if($service->description_ar)
                                                <div class="service-description">
                                                    {!! Str::limit(strip_tags($service->description_ar), 100) !!}
                                                </div>
                                            @endif

                                            {{-- بداية: الجزء المعدل للسعر والمدة --}}
                                            <div class="service-price-box-new">
                                                <i class="fas fa-tags"></i> {{-- أيقونة للسعر، تأكد من تضمين FontAwesome --}}
                                                {{ toArabicDigits(number_format($service->price_sar, 0)) }} ريال
                                            </div>

                                            <div class="service-duration-original">
                                                <i class="far fa-clock"></i> {{-- أيقونة للساعة --}}
                                                {{ toArabicDigits($service->duration_hours ?? '0') }} ساعات تصوير
                                            </div>
                                            {{-- نهاية: الجزء المعدل للسعر والمدة --}}

                                            <a href="{{ route('booking.calendar', $service->id) }}" class="service-button">
                                                 <i class="fas fa-calendar-alt"></i> احجز الآن
                                            </a>
                                        </div>
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
        // تفعيل تأثيرات الظهور
        const fadeElements = document.querySelectorAll('.fade-in');

        if ("IntersectionObserver" in window) {
            const observer = new IntersectionObserver((entries, observerInstance) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                        observerInstance.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            fadeElements.forEach(element => {
                element.style.animationPlayState = 'paused'; // ابدأ متوقفاً
                observer.observe(element);
            });
        } else {
            fadeElements.forEach(element => {
                element.style.opacity = 1;
                element.style.transform = 'translateY(0)';
            });
        }
    });
</script>
@endsection
