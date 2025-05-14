


<?php $__env->startSection('title', 'خدمات التصوير'); ?>

<?php $__env->startSection('styles'); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
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

    .services-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
        position: relative;
        display: inline-block;
        padding-bottom: 15px;
    }

    .services-title::after {
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

    .service-title {
        font-size: 18px;
        font-weight: 700;
        color: #333;
        margin: 0;
    }

    .service-price {
        background-color: #555;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }

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
    }

    .service-duration {
        margin-top: auto;
        margin-bottom: 15px;
        text-align: left;
    }

    .duration-badge {
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
    }

    .service-button:hover {
        background-color: #444;
        transform: translateY(-2px);
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        color: white;
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

    /* تنسيقات الدرج */
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
        .services-title {
            font-size: 24px;
        }

        .category-title {
            font-size: 20px;
        }

        .service-card-header {
            padding: 15px;
        }

        .service-title {
            font-size: 16px;
        }

        .service-content {
            padding: 15px;
        }

        .service-card-wrapper {
            margin-bottom: 20px;
        }
    }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="services-page-wrapper">
    <div class="container">
        <div class="services-header">
            <h1 class="services-title">باقات التصوير المميزة</h1>
        </div>

        <?php if($categories->isEmpty() || $categories->flatMap->services->isEmpty()): ?>
            <div class="no-services-message">
                <p class="no-services-text">لا توجد خدمات متاحة للعرض حالياً. يرجى المحاولة لاحقاً.</p>
            </div>
        <?php else: ?>
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($category->services->isNotEmpty()): ?>
                    <div class="category-section fade-in" style="animation-delay: <?php echo e($loop->index * 0.1); ?>s;">
                        <div class="category-title-wrapper">
                            <h2 class="category-title"><?php echo e($category->name_ar); ?></h2>
                        </div>

                        <div class="row">
                            <?php $__currentLoopData = $category->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="col-md-6 col-lg-4 service-card-wrapper fade-in" style="animation-delay: <?php echo e(($loop->parent->index * count($categories) + $loop->index + 1) * 0.05); ?>s;">
                                    <div class="service-card">
                                        <div class="service-card-header">
                                            <h3 class="service-title"><?php echo e($service->name_ar); ?></h3>
                                            
                                            <span class="service-price"><?php echo e(toArabicDigits(number_format($service->price_sar, 0))); ?> ريال</span>
                                        </div>

                                        <div class="service-content">
                                            <?php if($service->description_ar): ?>
                                                <div class="service-description">
                                                    <?php echo $service->description_ar; ?>

                                                </div>
                                            <?php endif; ?>

                                            <div class="service-duration">
                                                <span class="duration-badge">
                                                    
                                                    <?php echo e(toArabicDigits($service->duration_hours ?? '0')); ?> ساعات تصوير
                                                </span>
                                            </div>

                                            <a href="<?php echo e(route('booking.calendar', $service->id)); ?>" class="service-button">
                                                احجز الآن
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>

                    <?php if(!$loop->last): ?>
                        <div class="category-spacer" style="height: 30px;"></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // تفعيل تأثيرات الظهور
        const fadeElements = document.querySelectorAll('.fade-in');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        fadeElements.forEach(element => {
            element.style.animationPlayState = 'paused'; // ابدأ متوقفاً
            observer.observe(element);
        });
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/frontend/services/index.blade.php ENDPATH**/ ?>