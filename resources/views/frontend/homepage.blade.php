{{-- resources/views/frontend/homepage.blade.php --}}
@php
    // جلب الإعدادات. من الأفضل تمرير هذه من المتحكم الخاص بالصفحة الرئيسية.
    // لأغراض العرض هنا، سنجلبها مباشرة.
    $settingsHomepage = \App\Models\Setting::pluck('value', 'key')->all();

    $contactWhatsappNumber = $settingsHomepage['contact_whatsapp'] ?? '';
    $contactInstagramUrl = $settingsHomepage['contact_instagram_url'] ?? '';
    $displayWhatsapp = filter_var($settingsHomepage['display_whatsapp_contact'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $displayInstagram = filter_var($settingsHomepage['display_instagram_contact'] ?? true, FILTER_VALIDATE_BOOLEAN);
    
    // شعار الرأس من الإعدادات
    // بما أننا سنزيل الشعار من النافبار، قد لا نحتاج هذا المتغير هنا إلا إذا كان يستخدم في مكان آخر
    $headerLogoPath = $settingsHomepage['logo_path_dark'] ?? asset('images/logo_w.png'); 
    $modalLogoPath = $settingsHomepage['logo_path_light'] ?? asset('images/logo.png'); // شعار فاتح للمودال

    // صور السلايدر من الإعدادات
    $sliderImages = !empty($settingsHomepage['homepage_slider_images']) ? json_decode($settingsHomepage['homepage_slider_images'], true) : [];
    $sliderImages = is_array($sliderImages) ? array_filter($sliderImages) : []; // تأكد أنها مصفوفة وصالحة

    // Fallback default slider images if none are set in admin
    $defaultSliderImages = [
        asset('images/slider/slider1.jpg'),
        asset('images/slider/slider2.jpg'),
        asset('images/slider/slider3.jpg'),
    ];
    if (empty($sliderImages)) {
        $sliderImages = $defaultSliderImages;
    }

    // سياسات الحجز
    $bookingPolicyAr = $settingsHomepage['policy_ar'] ?? '';
    $bookingPolicyEn = $settingsHomepage['policy_en'] ?? '';


    // دالة لتنسيق رقم الواتساب للرابط والعرض
    if (!function_exists('formatWhatsappNumberHomepage')) { // اسم فريد للدالة
        function formatWhatsappNumberHomepage($number, $forUrl = false) {
            if (empty($number)) return '';
            $cleanedNumber = preg_replace('/[^0-9+]/', '', $number); // السماح بـ + في البداية
            if ($forUrl) {
                $cleanedNumberForUrl = preg_replace('/[^0-9]/', '', $cleanedNumber); // إزالة كل شيء ما عدا الأرقام للـ URL
                if (strpos($cleanedNumberForUrl, '00') === 0) { 
                    return substr($cleanedNumberForUrl, 2);
                }
                if (strpos($cleanedNumberForUrl, '0') === 0 && strlen($cleanedNumberForUrl) > 9) { 
                     return '966' . substr($cleanedNumberForUrl, 1);
                }
                return ltrim($cleanedNumberForUrl, '+'); 
            }
            return function_exists('toArabicDigits') ? toArabicDigits($number) : $number;
        }
    }
    $whatsappUrlNumber = formatWhatsappNumberHomepage($contactWhatsappNumber, true);
    $whatsappDisplayNumber = formatWhatsappNumberHomepage($contactWhatsappNumber, false);

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $settingsHomepage['site_name_' . app()->getLocale()] ?? ($settingsHomepage['site_name_ar'] ?? config('app.name', 'Fatimah Booking')) }}</title>

    @if(isset($settingsHomepage['favicon_path']) && $settingsHomepage['favicon_path'])
    <link rel="icon" href="{{ asset($settingsHomepage['favicon_path']) }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset($settingsHomepage['favicon_path']) }}" type="image/x-icon">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/frontend.css') }}">

    <style>
        html, body { margin: 0; padding: 0; width: 100%; overflow-x: hidden; }
        body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; }

        .navbar-overlay { position: absolute; top: 0; left: 0; right: 0; width: 100%; z-index: 1030; background-color: transparent !important; box-shadow: none !important; padding-top: 1rem; padding-bottom: 1rem; transition: background-color 0.3s ease-in-out, padding-top 0.3s ease-in-out, padding-bottom 0.3s ease-in-out; }
        .navbar-overlay.scrolled { background-color: rgba(0, 0, 0, 0.7) !important; padding-top: 0.5rem; padding-bottom: 0.5rem; }
        /* .navbar-overlay .navbar-brand img { max-height: 40px; transition: max-height 0.3s ease-in-out; filter: brightness(0) invert(1); } */ /* تم التعليق على هذا السطر الخاص بالشعار */
        /* .navbar-overlay.scrolled .navbar-brand img { max-height: 35px; } */ /* تم التعليق على هذا السطر الخاص بالشعار */
        .navbar-overlay .navbar-nav { background-color: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 50px; padding: 0.3rem 1rem; margin-top: 0.5rem; }
        .navbar-overlay.scrolled .navbar-nav { background-color: rgba(255, 255, 255, 0.1); }
        .navbar-overlay .nav-item { margin-left: 0.2rem; margin-right: 0.2rem; }
        .navbar-overlay .navbar-nav .nav-link { color: #ffffff !important; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.6); font-weight: 500; padding: 0.5rem 0.8rem; cursor: pointer; border-radius: 30px; }
        .navbar-overlay .navbar-nav .nav-link:hover, .navbar-overlay .navbar-nav .nav-link.active { background-color: rgba(255,255,255,0.2); }
        .navbar-overlay .navbar-toggler { border-color: rgba(255, 255, 255, 0.5); }
        .navbar-overlay .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); }
        .navbar-overlay .dropdown-menu { background-color: rgba(33, 37, 41, 0.95); border: none; font-family: 'Tajawal', sans-serif !important; border-radius: 0.5rem; }
        .navbar-overlay .dropdown-item { color: #f8f9fa; }
        .navbar-overlay .dropdown-item:hover, .navbar-overlay .dropdown-item:focus { color: #ffffff; background-color: rgba(255, 255, 255, 0.15); }
        .navbar-overlay .dropdown-divider { border-color: rgba(255, 255, 255, 0.2); }

        .hero-section-carousel { position: relative; overflow: hidden; width: 100%; margin: 0; padding: 0; }
        .hero-section-carousel .carousel-inner, .hero-section-carousel .carousel-item, .hero-section-carousel .hero-slide-item { min-height: 90vh; background-size: cover; background-position: center center; background-repeat: no-repeat; width: 100%; margin: 0; padding: 0; }
        .hero-section-carousel .carousel-caption { bottom: 0; left: 0; right: 0; top: 0; padding: 0; display: flex; align-items: center; justify-content: center; z-index: 5; color: #fff; background-color: transparent; }
        .hero-caption-content { display: flex; flex-direction: column; align-items: center; padding: 20px; border-radius: 10px; text-align: center;}
        .hero-caption-content .hero-logo-in-caption { max-width: 200px; height: auto; margin-bottom: 25px; z-index: 1; filter: drop-shadow(2px 2px 5px rgba(0,0,0,0.5)); }
        .hero-book-now-btn { border-width: 2px; font-weight: 700; text-transform: uppercase; transition: all 0.3s ease; z-index: 1; padding: 12px 30px; font-size: 1.1rem; border-radius: 50px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .hero-book-now-btn:hover { background-color: #ffffff; color: #333; border-color: #ffffff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.3); }
        .carousel-indicators button { width: 12px; height: 12px; border-radius: 50%; background-color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.7); margin: 0 5px; }
        .carousel-indicators .active { background-color: #fff; }
        .carousel-control-prev-icon, .carousel-control-next-icon { background-color: rgba(0,0,0,0.3); border-radius: 50%; padding: 1.5rem; background-size: 50% 50%; }

        .services-section { padding-top: 4rem; padding-bottom: 4rem; background-color: #ffffff; position: relative; z-index: 1; }
        .section-title { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; position: relative; padding-bottom: 1rem; }
        .section-title::after { content: ''; display: block; width: 80px; height: 4px; background-color: #555; margin: 0.5rem auto 0; border-radius: 2px; }
        .service-item { padding: 20px; border-radius: 10px; transition: all 0.3s ease; margin-bottom: 2rem;}
        .service-item:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .service-item .service-icon { width: 180px; height: 180px; object-fit: cover; border-radius: 50%; border: 6px solid #f0f0f0; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .service-item h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: #333; }
        .services-section .btn-outline-primary { color: #555; border-color: #555; font-weight: 600; padding: 10px 25px; border-radius: 50px; }
        .services-section .btn-outline-primary:hover { background-color: #555; color: #ffffff; }

        .contact-background { background: url('{{ asset($settingsHomepage['contact_bg_image_path'] ?? 'images/contact-background.jpg') }}') no-repeat center center; background-size: cover; position: relative; padding-top: 5rem; padding-bottom: 5rem; width: 100%; margin: 0; padding-left: 0; padding-right: 0; z-index: 1; }
        .contact-content-overlay { background-color: rgba(255, 255, 255, 0.9); z-index: 2; position: relative; border-radius: 0.75rem; box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15); }
        .contact-section .btn-success, .contact-section .btn-light { font-weight: 600; padding: 12px 25px; font-size: 1.1rem; border-radius: 50px; display: inline-flex; align-items: center; justify-content: center; }
        .contact-section .btn-success i, .contact-section .btn-light img { transition: transform 0.2s ease-in-out; }
        .contact-section .btn-success:hover i, .contact-section .btn-light:hover img { transform: scale(1.1); }

        .footer { width: 100%; margin: 0; padding: 25px 0; background-color: #343a40; color: #adb5bd; position: relative; z-index: 1; border-top: 1px solid #495057; }
        .footer .container { text-align: center; }
        .footer p { margin-bottom: 0; font-size: 0.9rem; }

        html[dir="rtl"] body { direction: rtl; text-align: right; }
        html[dir="rtl"] .navbar-nav { margin-right: auto !important; margin-left: 0 !important; }
        html[dir="rtl"] .navbar-overlay .navbar-nav { margin-left: auto !important; margin-right: 0 !important; } 
        @media (min-width: 992px) { 
            html[dir="rtl"] .navbar-overlay .navbar-nav { margin-right: auto !important; margin-left: auto !important; }
        }
        html[dir="rtl"] .dropdown-menu { right: 0; left: auto; text-align: right; }
        html[dir="rtl"] .contact-section .btn-success i, html[dir="rtl"] .contact-section .btn-light img { margin-left: 0.5rem !important; margin-right: 0 !important; }
        html[dir="ltr"] .contact-section .btn-success i, html[dir="ltr"] .contact-section .btn-light img { margin-right: 0.5rem !important; margin-left: 0 !important; }
        html[dir="rtl"] .modal-header .btn-close { margin-left: auto; margin-right: -0.5rem; }

        #bookingPolicyModal .modal-body { text-align: right; direction: rtl; }
        #bookingPolicyModal .modal-body h4 { margin-top: 1rem; margin-bottom: 0.5rem; font-weight: 700; }
        #bookingPolicyModal .modal-body ul { padding-right: 2rem; margin-bottom: 1rem; }
        #bookingPolicyModal .modal-body li { margin-bottom: 0.5rem; }
        #bookingPolicyModal .modal-header { border-bottom: none; padding-top: 1.5rem; padding-bottom: 0.5rem; display: flex; justify-content: center; position:relative; }
         #bookingPolicyModal .modal-header-logo { max-height: 80px; width: auto; }
         #bookingPolicyModal .btn-close { position: absolute; top: 1.5rem; right: 1.5rem; }
         html[dir="ltr"] #bookingPolicyModal .btn-close { right: auto; left: 1.5rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-overlay fixed-top">
        <div class="container">
            {{-- الشعار في النافبار - تم تحويله لتعليق لإزالته --}}
            {{--
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset($headerLogoPath) }}" alt="{{ $settingsHomepage['site_name_' . app()->getLocale()] ?? 'Logo' }}">
            </a>
            --}}
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') || request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('services.index') ? 'active' : '' }}" href="{{ route('services.index') }}">الخدمات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#bookingPolicyModal">
                            سياسة الحجز
                        </a>
                    </li>
                    @guest
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">تسجيل الدخول</a>
                        </li>
                        @if (Route::has('register.form'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('register.form') ? 'active' : '' }}" href="{{ route('register.form') }}">تسجيل جديد</a>
                            </li>
                        @endif
                    @else
                         <li class="nav-item dropdown">
                             <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <i class="fas fa-user-circle me-1"></i> {{ Auth::user()->name ?? 'حسابي' }}
                             </a>
                             <ul class="dropdown-menu {{ app()->getLocale() == 'ar' ? 'dropdown-menu-end' : '' }}" aria-labelledby="navbarDropdownUser">
                                 <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}"> <i class="fas fa-tachometer-alt fa-fw me-2"></i> لوحة تحكمي</a></li>
                                 @if(Auth::user()->is_admin ?? false)
                                     <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"> <i class="fas fa-user-shield fa-fw me-2"></i> لوحة تحكم المدير</a></li>
                                 @endif
                                 <li><hr class="dropdown-divider"></li>
                                 <li>
                                     <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form-nav').submit();">
                                        <i class="fas fa-sign-out-alt fa-fw me-2"></i> تسجيل الخروج
                                     </a>
                                     <form id="logout-form-nav" action="{{ route('logout') }}" method="POST" style="display: none;">
                                         @csrf
                                     </form>
                                 </li>
                             </ul>
                         </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section-carousel">
         <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
            @if(count($sliderImages) > 1)
            <div class="carousel-indicators">
                @foreach($sliderImages as $index => $image)
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $index }}" class="{{ $loop->first ? 'active' : '' }}" aria-current="{{ $loop->first ? 'true' : 'false' }}" aria-label="Slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
            @endif
            <div class="carousel-inner">
                @forelse($sliderImages as $index => $imagePath)
                <div class="carousel-item {{ $loop->first ? 'active' : '' }} hero-slide-item" style="background-image: url('{{ asset($imagePath) }}');">
                    <div class="carousel-caption">
                        <div class="hero-caption-content">
                            {{-- الشعار في السلايدر سيبقى إذا كان هذا هو المطلوب --}}
                            <img src="{{ asset($settingsHomepage['logo_path_dark'] ?? asset('images/logo_w.png')) }}" alt="{{ $settingsHomepage['site_name_' . app()->getLocale()] ?? 'Logo' }}" class="img-fluid hero-logo-in-caption">
                            <a href="{{ route('services.index') }}" class="btn btn-outline-light btn-lg hero-book-now-btn">
                                إحجز الأن
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="carousel-item active hero-slide-item" style="background-image: url('{{ asset('images/slider/slider_default.jpg') }}');">
                    <div class="carousel-caption">
                        <div class="hero-caption-content">
                             <img src="{{ asset($settingsHomepage['logo_path_dark'] ?? asset('images/logo_w.png')) }}" alt="{{ $settingsHomepage['site_name_' . app()->getLocale()] ?? 'Logo' }}" class="img-fluid hero-logo-in-caption">
                            <a href="{{ route('services.index') }}" class="btn btn-outline-light btn-lg hero-book-now-btn">
                                إحجز الأن
                            </a>
                        </div>
                    </div>
                </div>
                @endforelse
            </div>
            @if(count($sliderImages) > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            @endif
        </div>
    </header>

    <section class="services-section text-center">
        <div class="container">
            <h2 class="section-title mb-5">خدماتنا</h2>
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="service-item">
                        <img src="{{ asset('images/placeholder/service-1.jpg') }}" alt="تصوير الأعراس" class="img-fluid service-icon">
                        <h3>تصوير الأعراس</h3>
                        <p class="text-muted px-3">نوثق أسعد لحظاتكم بتفاصيل فنية تبقى ذكرى خالدة.</p>
                        <a href="{{ route('services.index') }}" class="btn btn-outline-primary mt-2">عرض التفاصيل والحجز</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item">
                        <img src="{{ asset('images/placeholder/service-2.jpg') }}" alt="تصوير المودلز" class="img-fluid service-icon">
                        <h3>تصوير المنتجات والمودلز</h3>
                        <p class="text-muted px-3">نبرز جمال منتجاتكم وجاذبية موديلاتكم بصور احترافية.</p>
                        <a href="{{ route('services.index') }}" class="btn btn-outline-primary mt-2">عرض التفاصيل والحجز</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-item">
                        <img src="{{ asset('images/placeholder/service-3.jpg') }}" alt="تصوير الحفلات والمناسبات" class="img-fluid service-icon">
                        <h3>تصوير الحفلات والمناسبات</h3>
                        <p class="text-muted px-3">نلتقط روعة مناسباتكم الخاصة بحرفية عالية لتوثيق كل لحظة.</p>
                        <a href="{{ route('services.index') }}" class="btn btn-outline-primary mt-2">عرض التفاصيل والحجز</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section contact-background">
        <div class="container">
            <div class="contact-content-overlay card p-4 p-md-5 mx-auto" style="max-width: 800px;">
                <h2 class="section-title text-center mb-4">تواصل معنا</h2>
                <p class="text-center lead mb-4">للاستفسارات أو لمناقشة حجوزاتكم، يمكنكم التواصل معنا عبر:</p>
                <div class="text-center d-flex flex-column flex-sm-row justify-content-center align-items-center flex-wrap gap-3">
                    @if($displayWhatsapp && !empty($contactWhatsappNumber) && !empty($whatsappUrlNumber))
                        <a href="https://wa.me/{{ $whatsappUrlNumber }}" class="btn btn-success btn-lg" target="_blank" style="min-width: 220px;">
                            <i class="fab fa-whatsapp"></i> واتساب
                        </a>
                    @endif

                    @if($displayInstagram && !empty($contactInstagramUrl))
                        <a href="{{ $contactInstagramUrl }}" class="btn btn-light btn-lg" target="_blank" style="min-width: 220px; border: 1px solid #DAA520; color: #333; background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); color:white;">
                            <img src="{{ asset('images/instagram.png') }}" alt="Instagram" style="height: 24px; width: auto; filter: brightness(0) invert(1);"> إنستقرام
                        </a>
                    @endif
                </div>

                @if($displayWhatsapp && !empty($contactWhatsappNumber))
                    <p class="mt-4 h5 text-center">
                        <span class="d-block">رقم الواتساب: <strong dir="ltr">{{ $whatsappDisplayNumber }}</strong></span>
                    </p>
                @endif

                @if(!$displayWhatsapp && !$displayInstagram)
                    <p class="text-center text-muted mt-3">وسائل التواصل غير محددة حالياً. يرجى المحاولة لاحقاً.</p>
                @endif
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} {{ $settingsHomepage['site_name_' . app()->getLocale()] ?? ($settingsHomepage['site_name_ar'] ?? 'Fatimah Ali Photography') }}. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <div class="modal fade" id="bookingPolicyModal" tabindex="-1" aria-labelledby="bookingPolicyModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <img src="{{ asset($modalLogoPath) }}" alt="{{ $settingsHomepage['site_name_' . app()->getLocale()] ?? 'Logo' }}" class="modal-header-logo">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @php
                $policyToDisplay = app()->getLocale() == 'ar' ? $bookingPolicyAr : $bookingPolicyEn;
                if(empty(trim($policyToDisplay))) $policyToDisplay = (app()->getLocale() == 'ar' ? $bookingPolicyEn : $bookingPolicyAr); 
                if(empty(trim($policyToDisplay))) $policyToDisplay = 'لم يتم تحديد سياسة الحجز بعد.';
            @endphp
            {!! nl2br(e($policyToDisplay)) !!}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-overlay');
            if (navbar) { // التأكد من أن العنصر موجود
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });
    </script>
</body>
</html>
