<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('app.name', 'Laravel')); ?> - <?php echo $__env->yieldContent('title', 'لوحة تحكم المدير'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>"> 
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="admin-body">

    
    <nav class="navbar navbar-expand-lg navbar-light bg-white admin-navbar px-3 shadow-sm sticky-top">
        <button class="navbar-toggler d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="تبديل القائمة الجانبية">
             <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="<?php echo e(route('admin.dashboard')); ?>">
            
            <img src="<?php echo e(asset(optional(App\Models\Setting::where('key', 'logo_path_dark')->first())->value ?? 'images/logo.png')); ?>" alt="<?php echo e(config('app.name', 'Laravel')); ?>" style="height: 30px;">
             - لوحة التحكم
        </a>
        <ul class="navbar-nav ms-auto mb-2 mb-md-0 align-items-center">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo e(url('/')); ?>" target="_blank" title="عرض الموقع">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user me-1"></i> <?php echo e(Auth::user()->name ?? 'المدير'); ?>

                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                    <li>
                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form-nav').submit();">
                            <i class="fas fa-sign-out-alt text-danger me-2"></i> تسجيل الخروج
                        </a>
                        <form id="logout-form-nav" action="<?php echo e(route('logout')); ?>" method="POST" style="display: none;">
                            <?php echo csrf_field(); ?>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <div class="d-flex align-items-stretch admin-wrapper">

        
        <nav id="sidebarMenu" class="sidebar collapse d-lg-block bg-white shadow-sm">
            <div class="sidebar-sticky">
                 <ul class="nav flex-column">
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.dashboard') ? 'active' : ''); ?>" href="<?php echo e(route('admin.dashboard')); ?>">
                             <i class="fas fa-fw fa-tachometer-alt"></i> <span>الرئيسية</span>
                         </a>
                     </li>
                     <li class="nav-item nav-heading mt-3 mb-1 text-muted small text-uppercase">إدارة المحتوى</li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.service-categories.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.service-categories.index')); ?>">
                             <i class="fas fa-fw fa-tags"></i> <span>فئات الخدمات</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.services.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.services.index')); ?>">
                             <i class="fas fa-fw fa-camera-retro"></i> <span>الخدمات</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.discount-codes.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.discount-codes.index')); ?>">
                             <i class="fas fa-fw fa-percent"></i> <span>أكواد الخصم</span>
                         </a>
                     </li>

                     <li class="nav-item nav-heading mt-3 mb-1 text-muted small text-uppercase">إدارة العمليات</li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.bookings.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.bookings.index')); ?>">
                             <i class="fas fa-fw fa-calendar-check"></i> <span>الحجوزات</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.invoices.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.invoices.index')); ?>">
                             <i class="fas fa-fw fa-file-invoice-dollar"></i> <span>الفواتير</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.availability.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.availability.index')); ?>">
                             <i class="fas fa-fw fa-calendar-alt"></i> <span>التوافر</span>
                         </a>
                     </li>
                    
                     <li class="nav-item">
                        <a class="nav-link <?php echo e(request()->routeIs('admin.customers.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.customers.index')); ?>">
                            <i class="fas fa-fw fa-users"></i>
                            <span>إدارة العملاء</span>
                        </a>
                     </li>
                    

                     <li class="nav-item nav-heading mt-3 mb-1 text-muted small text-uppercase">الإعدادات والتكوين</li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.bank-accounts.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.bank-accounts.index')); ?>">
                             <i class="fas fa-fw fa-university"></i> <span>الحسابات البنكية</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.sms-templates.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.sms-templates.index')); ?>">
                             <i class="fas fa-fw fa-sms"></i>
                             <span>قوالب الرسائل النصية</span>
                         </a>
                     </li>
                     <li class="nav-item">
                         <a class="nav-link <?php echo e(request()->routeIs('admin.settings.edit') ? 'active' : ''); ?>" href="<?php echo e(route('admin.settings.edit')); ?>">
                             <i class="fas fa-fw fa-cogs"></i> <span>الإعدادات العامة</span>
                         </a>
                     </li>
                 </ul>
                 <div class="sidebar-footer">
                     <form id="logout-form-sidebar" action="<?php echo e(route('logout')); ?>" method="POST">
                         <?php echo csrf_field(); ?>
                         <button type="submit" class="btn btn-outline-danger w-100">
                             <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                         </button>
                     </form>
                 </div>
            </div>
        </nav>

         
         <div class="sidebar-backdrop d-lg-none"></div>


        
        <main class="admin-content flex-grow-1 p-3 p-md-4">
            <div class="container-fluid">

                <?php if(session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo e(session('success')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endif; ?>
                <?php if(session('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo e(session('error')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endif; ?>
                 <?php if(session('warning')): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo e(session('warning')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endif; ?>
                <?php if($errors->any()): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i> خطأ!</strong> الرجاء مراجعة الأخطاء التالية:
                        <ul class="mb-0 mt-2">
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
                    </div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('content'); ?>

             </div> 

             
             <footer class="px-4 admin-footer">
                 <div class="container-fluid border-top py-3 mt-4">
                     <div class="row">
                         <div class="col-12 text-center text-muted small">
                             &copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name', 'Fatimah Booking')); ?>. جميع الحقوق محفوظة.
                         </div>
                     </div>
                 </div>
             </footer>

        </main>

    </div> 


    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

     <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarBackdrop = document.querySelector('.sidebar-backdrop');
            const sidebarElement = document.getElementById('sidebarMenu');
            const adminWrapper = document.querySelector('.admin-wrapper');
            const sidebarToggler = document.querySelector('.navbar-toggler[data-bs-target="#sidebarMenu"]');

            function toggleSidebarClass() {
                if (sidebarElement && adminWrapper) {
                    if (sidebarElement.classList.contains('show')) {
                        adminWrapper.classList.add('sidebar-open');
                    } else {
                        adminWrapper.classList.remove('sidebar-open');
                    }
                }
            }

            if (sidebarElement && sidebarBackdrop) {
                sidebarElement.addEventListener('show.bs.collapse', function () {
                    sidebarBackdrop.style.display = 'block';
                    if(adminWrapper) adminWrapper.classList.add('sidebar-open');
                });
                sidebarElement.addEventListener('hide.bs.collapse', function () {
                    sidebarBackdrop.style.display = 'none';
                    if(adminWrapper) adminWrapper.classList.remove('sidebar-open');
                });
                 sidebarBackdrop.addEventListener('click', function() {
                     if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                         var sidebarCollapseInstance = bootstrap.Collapse.getInstance(sidebarElement);
                         if (!sidebarCollapseInstance) {
                             sidebarCollapseInstance = new bootstrap.Collapse(sidebarElement, { toggle: false });
                         }
                         sidebarCollapseInstance.hide();
                     }
                 });

                const observer = new MutationObserver(toggleSidebarClass);
                observer.observe(sidebarElement, { attributes: true, attributeFilter: ['class'] });
                toggleSidebarClass();
            }

             if(sidebarToggler && adminWrapper) {
                 sidebarToggler.addEventListener('click', function() {
                     setTimeout(() => {
                         if (sidebarElement.classList.contains('show')) {
                             adminWrapper.classList.add('sidebar-open');
                         } else {
                             adminWrapper.classList.remove('sidebar-open');
                         }
                     }, 50);
                 });
             }
        });
    </script>

    <?php echo $__env->yieldPushContent('scripts'); ?>

</body>
</html><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/layouts/admin.blade.php ENDPATH**/ ?>