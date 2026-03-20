
<!DOCTYPE html>

<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(app()->getLocale() == 'ar' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <title><?php echo $__env->yieldContent('title', config('app.name', 'Fatimah Booking')); ?></title>

    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">


    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
     
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo e(asset('css/frontend.css')); ?>">

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

    
    <?php echo $__env->yieldContent('styles'); ?>

</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            
            <a class="navbar-brand" href="<?php echo e(url('/')); ?>">
                <img src="<?php echo e(asset('images/logo.png')); ?>" alt="Logo" height="40"> 
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('home') ? 'active' : ''); ?>" href="<?php echo e(url('/')); ?>">الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('services.index') ? 'active' : ''); ?>" href="<?php echo e(route('services.index')); ?>">الخدمات</a>
                    </li>
                    
                    <?php if(auth()->guard()->guest()): ?>
                        <li class="nav-item">
                            
                            <a class="nav-link <?php echo e(request()->routeIs('login') ? 'active' : ''); ?>" href="<?php echo e(route('login')); ?>">تسجيل الدخول</a>
                        </li>
                    <?php else: ?>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo e(Auth::user()->name); ?>

                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?php echo e(route('customer.dashboard')); ?>">حسابي</a></li> 
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

    <main class="container py-4">
        
        <?php echo $__env->yieldContent('content'); ?>
    </main>

    <footer class="bg-white text-center text-lg-start mt-auto py-3 border-top">
      <div class="container text-center">
        
        © <?php echo e(date('Y')); ?> جميع الحقوق محفوظة للمصورة فاطمة علي.
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php echo $__env->yieldContent('scripts'); ?>
</body>
</html><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/layouts/app.blade.php ENDPATH**/ ?>