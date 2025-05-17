{{-- resources/views/frontend/services/index.blade.php --}}
@extends('layouts.app')

@section('title', 'خدمات التصوير')

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
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

    .services-title-main {
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
        background-color: #555;
    }

    /* بطاقة الخدمة */
    .service-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        overflow: hidden; /* مهم إذا كانت الصورة لها حواف دائرية مختلفة */
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
    
    /* تم إزالة أنماط الصورة إذا تم حذف عنصر الصورة */
    /* .service-card .card-img-top-placeholder { ... } */
    /* .service-card .service-image { ... } */
    
    .service-content {
        padding: 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .service-title { /* اسم الخدمة داخل البطاقة */
        font-size: 1.15rem;
        font-weight: 700;
        color: #343a40;
        margin-bottom: 0.75rem;
        line-height: 1.4;
        text-align: center; /* تمت إضافة توسيط لاسم الخدمة */
    }

    .service-description {
        margin-bottom: 1rem;
        color: #555;
        font-size: 0.9rem;
        line-height: 1.6;
        flex-grow: 1;
    }
    .service-description p { margin-bottom: 0.5em; }
    .service-description ul, .service-description ol { padding-right: 20px; margin-bottom: 0.5em; }
    .service-description li { margin-bottom: 0.25em; }
    .service-description strong { font-weight: bold; }

    .service-price-box-new {
        padding: 0.6rem 0.85rem;
        border-radius: 0.3rem;
        font-size: 0.95rem;
        font-weight: 700;
        text-align: center;
        width: 100%;
        box-sizing: border-box;
        background-color: rgba(135, 206, 250, 0.2);
        color: #005cbf;
        border: 1px solid rgba(135, 206, 250, 0.35);
        margin-bottom: 0.75rem;
    }
    .service-price-box-new i {
        margin-left: 0.4rem;
    }

    .service-duration-original {
        margin-bottom: 1rem;
        text-align: center;
        width: 100%;
        padding: 0.6rem 0.85rem;
        border-radius: 0.3rem;
        background-color: rgba(108, 117, 125, 0.15);
        color: #495057;
        box-sizing: border-box;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .service-duration-original i {
        margin-left: 0.4rem;
    }

    .service-button {
        width: 100%;
        padding: 10px 15px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 0.3rem;
        font-weight: 600;
        font-size: 0.95rem;
        text-align: center;
        transition: background-color 0.2s ease, transform 0.2s ease;
        text-decoration: none;
        display: block;
        margin-top: auto;
    }
    .service-button:hover {
        background-color: #0056b3;
        transform: translateY(-1px);
        color: white;
    }
     .service-button i {
        margin-left: 0.5rem;
    }

    .no-services-message {
        text-align: center;
        padding: 40px 20px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
    }
    .no-services-icon {
        font-size: 3rem;
        color: #adb5bd;
        margin-bottom: 15px;
    }
    .no-services-text {
        font-size: 1.1rem;
        color: #6c757d;
    }

    .category-section {
        margin-bottom: 45px;
    }

    .service-card-wrapper {
        margin-bottom: 30px;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-in {
        animation: fadeIn 0.4s ease-out forwards;
        opacity: 0;
    }

    @media (max-width: 767px) {
        .services-title-main { font-size: 24px; }
        .category-title { font-size: 20px; }
        .service-content { padding: 0.9rem; }
        .service-card-wrapper { margin-bottom: 20px; }
        .service-title { font-size: 1rem; }
        .service-price-box-new, .service-duration-original { font-size: 0.85rem; }
        .service-button { font-size: 0.9rem; padding: 9px 12px; }
    }
</style>
@endsection

@section('content')
<div class="services-page-wrapper">
    <div class="container">
        <div class="services-header">
            <h1 class="services-title-main">باقات التصوير المميزة</h1>
        </div>

        @if($categories->isEmpty() || $categories->flatMap(fn($category) => $category->services)->isEmpty())
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
                                        {{-- بداية: تم حذف/التعليق على جزء الصورة --}}
                                        {{-- @if($service->image_path)
                                            <img src="{{ asset('storage/' . $service->image_path) }}" class="service-image" alt="{{ $service->name_ar }}">
                                        @else
                                            <div class="card-img-top-placeholder">
                                                <i class="fas fa-camera"></i>
                                            </div>
                                        @endif --}}
                                        {{-- نهاية: تم حذف/التعليق على جزء الصورة --}}

                                        <div class="service-content">
                                            <h3 class="service-title">{{ $service->name_ar }}</h3>

                                            @if($service->description_ar)
                                                <div class="service-description">
                                                    {!! $service->description_ar !!}
                                                </div>
                                            @endif

                                            <div class="service-price-box-new">
                                                <i class="fas fa-tags"></i>
                                                {{ toArabicDigits(number_format($service->price_sar, 0)) }} ريال
                                            </div>

                                            <div class="service-duration-original">
                                                <i class="far fa-clock"></i>
                                                {{ toArabicDigits($service->duration_hours ?? '0') }} ساعات تصوير
                                            </div>

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
                element.style.animationPlayState = 'paused';
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
