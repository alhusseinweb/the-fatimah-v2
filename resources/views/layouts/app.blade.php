{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
{{-- تحديد اللغة والاتجاه بناءً على لغة التطبيق الحالية --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF Token for forms --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- العنوان الديناميكي للصفحة، مع قيمة افتراضية --}}
    <title>@yield('title', config('app.name', 'Fatimah Booking'))</title>

    {{-- يمكنك إضافة خطوط مخصصة هنا (مثل Google Fonts) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    {{-- إضافة خط عربي (مثال: Cairo) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">


    {{-- استخدام Bootstrap (يمكن تغييره لاحقاً) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
     {{-- أيقونات Font Awesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    {{-- ملف CSS مخصص (يمكنك إنشاؤه في public/css/frontend.css) --}}
    <link rel="stylesheet" href="{{ asset('css/frontend.css') }}">

    <style>
        /* إضافة الخط العربي كخط أساسي عند استخدام اللغة العربية */
        body {
            font-family: 'Figtree', sans-serif; /* الخط الافتراضي */
        }
        html[lang="ar"] body {
            font-family: 'Cairo', sans-serif; /* تطبيق الخط العربي */
        }
        /* يمكنك إضافة تنسيقات أساسية أخرى هنا أو في frontend.css */
        .service-card { margin-bottom: 1.5rem; }
    </style>

    {{-- مكان لإضافة ستايلات خاصة بكل صفحة --}}
    @yield('styles')

</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            {{-- الشعار - يجب وضع رابط الشعار الصحيح هنا --}}
            <a class="navbar-brand" href="{{ url('/') }}">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" height="40"> {{-- ضع مسار الشعار الصحيح --}}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ url('/') }}">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('services.index') ? 'active' : '' }}" href="{{ route('services.index') }}">الخدمات</a>
                    </li>
                    {{-- روابط تسجيل الدخول / حسابي --}}
                    @guest
                        <li class="nav-item">
                            {{-- سنضيف رابط تسجيل الدخول لاحقاً، حالياً نوجه للوحة تحكم العميل المؤقتة --}}
                            <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">تسجيل الدخول</a>
                        </li>
                    @else
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}">حسابي</a></li> {{-- نفترض وجود مسار customer.dashboard --}}
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                     <form method="POST" action="{{ route('logout') }}">
                                         @csrf
                                         <button type="submit" class="dropdown-item">تسجيل الخروج</button>
                                     </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                     {{-- يمكنك إضافة رابط لتغيير اللغة هنا --}}
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        {{-- هنا سيتم عرض محتوى كل صفحة --}}
        @yield('content')
    </main>

    <footer class="bg-white text-center text-lg-start mt-auto py-3 border-top">
      <div class="container text-center">
        {{-- يمكنك إضافة معلومات الحقوق أو روابط أخرى هنا --}}
        © {{ date('Y') }} جميع الحقوق محفوظة للمصورة فاطمة علي.
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    {{-- مكان لإضافة سكريبتات خاصة بكل صفحة --}}
    @yield('scripts')
</body>
</html>