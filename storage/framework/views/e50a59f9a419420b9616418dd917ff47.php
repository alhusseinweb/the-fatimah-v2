
<!DOCTYPE html>

<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(app()->getLocale() == 'ar' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <title><?php echo e(config('app.name', 'Fatimah Booking')); ?></title>

    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    
    <link rel="stylesheet" href="<?php echo e(asset('css/frontend.css')); ?>">

    <style>
        /* تنسيقات أساسية لـ body و html لإزالة الهوامش وضمان عرض كامل */
        html, body {
            margin: 0; padding: 0; width: 100%; overflow-x: hidden;
        }
        body {
            font-family: 'Tajawal', sans-serif !important;
            background-color: #f8f9fa;
        }

        /* Navbar Styling */
        .navbar-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; width: 100%;
            z-index: 1030;
            background-color: transparent !important;
            box-shadow: none !important;
            padding-top: 1rem; padding-bottom: 1rem;
        }
        .navbar-overlay .navbar-nav {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            margin-top: 0.5rem;
        }
        .navbar-overlay .nav-item { margin-left: 0.25rem; margin-right: 0.25rem; }
        .navbar-overlay .navbar-nav .nav-link {
            color: #ffffff !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            font-weight: 500;
            padding-left: 0.75rem; padding-right: 0.75rem;
            cursor: pointer; /* Add pointer cursor for modal trigger */
        }
        .navbar-overlay .navbar-nav .nav-link.active { font-weight: 700; }
        .navbar-overlay .navbar-toggler { border-color: rgba(255, 255, 255, 0.5); }
        .navbar-overlay .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); }
        .navbar-overlay .dropdown-menu { background-color: rgba(33, 37, 41, 0.9); border: none; font-family: 'Tajawal', sans-serif !important; }
        .navbar-overlay .dropdown-item { color: #f8f9fa; }
        .navbar-overlay .dropdown-item:hover, .navbar-overlay .dropdown-item:focus { color: #ffffff; background-color: rgba(255, 255, 255, 0.1); }
        .navbar-overlay .dropdown-divider { border-color: rgba(255, 255, 255, 0.2); }

        /* Hero Section */
        .hero-section-carousel { position: relative; overflow: hidden; width: 100%; margin: 0; padding: 0; }
        .hero-section-carousel .carousel-inner, .hero-section-carousel .carousel-item, .hero-section-carousel .hero-slide-item { min-height: 80vh; background-size: cover; background-position: center center; background-repeat: no-repeat; width: 100%; margin: 0; padding: 0; }
        .hero-slide-item:nth-child(1) { background-image: url('<?php echo e(asset('images/slider/slider1.jpg')); ?>'); }
        .hero-slide-item:nth-child(2) { background-image: url('<?php echo e(asset('images/slider/slider2.jpg')); ?>'); }
        .hero-slide-item:nth-child(3) { background-image: url('<?php echo e(asset('images/slider/slider3.jpg')); ?>'); }
        .hero-section-carousel .carousel-caption { bottom: 0; left: 0; right: 0; top: 0; padding: 0; display: flex; align-items: center; justify-content: center; z-index: 5; color: #fff; }
        .hero-caption-content { display: flex; flex-direction: column; align-items: center; padding: 20px; border-radius: 10px; }
        .hero-caption-content .hero-logo-in-caption { max-width: 150px; height: auto; margin-bottom: 15px; z-index: 1; }
        .hero-book-now-btn { border-width: 2px; font-weight: 700; text-transform: uppercase; transition: all 0.3s ease; z-index: 1; }
        .hero-book-now-btn:hover { background-color: #ffffff; color: #000000; border-color: #ffffff; }

        /* Services Section */
        .services-section { padding-top: 3rem; padding-bottom: 3rem; background-color: #ffffff; position: relative; z-index: 1; }
        .service-item .service-icon { width: 200px; height: 200px; object-fit: cover; border: 5px solid #e9ecef; }
        .services-section .btn-outline-primary { color: #555; border-color: #555; font-weight: 700; }
        .services-section .btn-outline-primary:hover { background-color: #555; color: #ffffff; border-color: #555; }

        /* Contact Section */
        .contact-background { background: url('<?php echo e(asset('images/contact-background.jpg')); ?>') no-repeat center center; background-size: cover; position: relative; padding-top: 5rem; padding-bottom: 5rem; width: 100%; margin: 0; padding-left: 0; padding-right: 0; z-index: 1; }
        .contact-content-overlay { background-color: rgba(255, 255, 255, 0.85); z-index: 2; position: relative; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); }
        .contact-section .btn-success { font-weight: 700; }

        /* Footer */
        .footer { width: 100%; margin: 0; padding: 20px 0; background-color: #e9ecef; color: #555; position: relative; z-index: 1; border-top: 1px solid #dee2e6; }
        .footer .container { text-align: center; }

        /* RTL Adjustments */
        html[dir="rtl"] body { direction: rtl; text-align: right; }
        html[dir="rtl"] .navbar-nav { margin-right: auto !important; margin-left: 0 !important; }
        html[dir="rtl"] .navbar-overlay .navbar-nav { margin-left: auto !important; margin-right: auto !important; }
        html[dir="rtl"] .dropdown-menu { right: 0; left: auto; }
        html[dir="rtl"] .fa-whatsapp { margin-left: 0.5rem !important; margin-right: 0 !important; }
        /* Adjust close button position for RTL */
        html[dir="rtl"] .modal-header .btn-close {
            margin-left: auto;
            margin-right: -0.5rem; /* Adjust as needed */
        }


        /* Modal Styling */
        #bookingPolicyModal .modal-body {
            text-align: right; /* Ensure text aligns right for Arabic */
            direction: rtl;
        }
        #bookingPolicyModal .modal-body h4 {
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        #bookingPolicyModal .modal-body ul {
            padding-right: 2rem; /* Indent list for better readability */
            margin-bottom: 1rem;
        }
        #bookingPolicyModal .modal-body li {
            margin-bottom: 0.5rem; /* Space between list items */
        }
        /* Modal Header Logo Styling */
        #bookingPolicyModal .modal-header {
            border-bottom: none; /* Remove the default border */
            padding-top: 1.5rem; /* Add more padding */
            padding-bottom: 0.5rem;
        }
         #bookingPolicyModal .modal-header-logo {
            /* *** MODIFIED: Increased max-height further *** */
            max-height: 200px; /* Adjust max height as needed (e.g., 100px, 120px) */
            /* ******************************************* */
            width: auto;
         }

    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark navbar-overlay">
        <div class="container">
            
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('home') ? 'active' : ''); ?>" href="<?php echo e(url('/')); ?>">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('services.index') ? 'active' : ''); ?>" href="<?php echo e(route('services.index')); ?>">الخدمات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="modal" data-bs-target="#bookingPolicyModal">
                            سياسة الحجز
                        </a>
                    </li>
                    
                    <?php if(auth()->guard()->guest()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo e(request()->routeIs('login.otp.form') ? 'active' : ''); ?>" href="<?php echo e(route('login')); ?>">تسجيل الدخول</a>
                        </li>
                    <?php else: ?>
                         <li class="nav-item dropdown">
                             <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                 <?php echo e(Auth::user()->name ?? 'حسابي'); ?>

                             </a>
                             <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                 <li><a class="dropdown-item" href="<?php echo e(route('customer.dashboard')); ?>">حسابي</a></li>
                                 <?php if(Auth::user()->is_admin ?? false): ?>
                                     <li><a class="dropdown-item" href="<?php echo e(route('admin.dashboard')); ?>">لوحة تحكم المدير</a></li>
                                 <?php endif; ?>
                                 <li><hr class="dropdown-divider"></li>
                                 <li>
                                     <form method="POST" action="<?php echo e(route('logout')); ?>">
                                         <?php echo csrf_field(); ?>
                                         <button type="submit" class="dropdown-item">تسجيل الخروج</button>
                                     </form>
                                 </li>
                             </ul>
                         </li>
                    <?php endif; ?>
                     
                </ul>
            </div>
        </div>
    </nav>

    
    
    <header class="hero-section-carousel">
        
         <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active hero-slide-item">
                    <div class="carousel-caption d-flex align-items-center justify-content-center h-100">
                        <div class="text-center text-white hero-caption-content">
                            <img src="<?php echo e(asset('images/logo_w.png')); ?>" alt="Fatimah Ali Photography Logo" class="img-fluid hero-logo-in-caption mb-3">
                            <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-light btn-lg hero-book-now-btn">
                                إحجز الأن
                            </a>
                        </div>
                    </div>
                </div>
                <div class="carousel-item hero-slide-item">
                     <div class="carousel-caption d-flex align-items-center justify-content-center h-100">
                         <div class="text-center text-white hero-caption-content">
                             <img src="<?php echo e(asset('images/logo_w.png')); ?>" alt="Fatimah Ali Photography Logo" class="img-fluid hero-logo-in-caption mb-3">
                             <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-light btn-lg hero-book-now-btn">
                                 إحجز الأن
                             </a>
                         </div>
                     </div>
                </div>
                <div class="carousel-item hero-slide-item">
                     <div class="carousel-caption d-flex align-items-center justify-content-center h-100">
                         <div class="text-center text-white hero-caption-content">
                             <img src="<?php echo e(asset('images/logo_w.png')); ?>" alt="Fatimah Ali Photography Logo" class="img-fluid hero-logo-in-caption mb-3">
                             <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-light btn-lg hero-book-now-btn">
                                 إحجز الأن
                             </a>
                         </div>
                     </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </header>

    <section class="services-section">
        
        <div class="container">
            <h2 class="text-center mb-5">خدماتنا</h2>
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <div class="service-item">
                        <img src="<?php echo e(asset('images/cr1.jpg')); ?>" alt="تصوير الأعراس" class="img-fluid rounded-circle service-icon mb-3">
                        <h3>تصوير الأعراس</h3>
                        <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-primary mt-2">احجز الان</a>
                    </div>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="service-item">
                        <img src="<?php echo e(asset('images/cr2.jpg')); ?>" alt="تصوير المودلز" class="img-fluid rounded-circle service-icon mb-3">
                        <h3>تصوير المودلز</h3>
                        <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-primary mt-2">احجز الان</a>
                    </div>
                </div>
                <div class="col-md-4 text-center mb-4">
                    <div class="service-item">
                        <img src="<?php echo e(asset('images/cr3.jpg')); ?>" alt="تصوير الحفلات" class="img-fluid rounded-circle service-icon mb-3">
                        <h3>تصوير الحفلات</h3>
                        <a href="<?php echo e(route('services.index')); ?>" class="btn btn-outline-primary mt-2">احجز الان</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section contact-background">
        
         <div class="container">
             <div class="contact-content-overlay card p-4 p-md-5 mx-auto" style="max-width: 800px;">
                 <h2 class="text-center mb-4">تواصل معنا</h2>
                 <p class="text-center lead mb-4">للاستفسارات يرجى التواصل معنا على الواتس أب:</p>
                 <div class="text-center">
                     <a href="https://wa.me/966536311315" class="btn btn-success btn-lg" target="_blank">
                         <i class="fab fa-whatsapp me-2"></i> WhatsApp
                     </a>
                     <p class="mt-3 h5">0536311315</p>
                 </div>
             </div>
        </div>
    </section>

    <footer class="footer">
        
        <div class="container">
            <p>&copy; <?php echo e(date('Y')); ?> Fatimah Ali Photography. All Rights Reserved.</p>
        </div>
    </footer>

    
    <div class="modal fade" id="bookingPolicyModal" tabindex="-1" aria-labelledby="bookingPolicyModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          
          <div class="modal-header d-flex justify-content-center">
            <img src="<?php echo e(asset('images/logo.png')); ?>" alt="Logo" class="modal-header-logo">
            <button type="button" class="btn-close position-absolute top-0 start-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <div class="modal-body">
            <h4>سياسة الحجز :</h4>
            <ul>
              <li>يتم تأكيد الحجز بعد دفع العربون ( نصف المبلغ ) .</li>
              <li>العربون غير قابل للأسترجاع .</li>
              <li>يتم دفع باقي المبلغ خلال ٤٨ ساعه قبل التصوير .<br>( لا يتم البدء في عمل الألبوم أو معالجة البيوتي إلا بعد تسليم المبلغ كامل )</li>
              <li>يتم اختيار الصور من قبل العروس ، و عند الرغبة للمصوره بأختيار الصور يتم إضافة ١٠٠ ريال .</li>
              <li>عند حذف جزء من ملحقات الألبوم لا يتغير السعر .</li>
              <li>الساعة الإضافية بـ ٣٠٠ ريال</li>
              <li>يتم تسليم الألبوم خلال ١٢ شهر</li>
              <li>إضافة ٣٠٠ ريال للتصوير خارج الأحساء</li>
            </ul>

            <h4>الإضافات :</h4>
            <ul>
              <li>صفحة ألبوم a5 بـ ١٠٠</li>
              <li>صفحة ألبوم A4 بـ ١٥٠</li>
              <li>صفحة للألبوم المربع بـ ٢٠٠</li>
              <li>حفر الأسماء عالألبوم بـ ١٥٠</li>
            </ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>
      </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/frontend/homepage.blade.php ENDPATH**/ ?>