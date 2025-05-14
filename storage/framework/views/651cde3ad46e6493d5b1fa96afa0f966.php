

<?php $__env->startSection('title', 'لوحة تحكم العميل'); ?>

<?php $__env->startSection('styles'); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* --- الأساسيات --- */
    body {
        font-family: 'Tajawal', sans-serif !important;
        background-color: #f8f9fa;
        direction: rtl;
        text-align: right;
    }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div, th, td, a {
        font-family: 'Tajawal', sans-serif !important;
    }
    .dashboard-wrapper { padding: 40px 0; min-height: calc(100vh - 150px); }
    .page-header { margin-bottom: 30px; }
    .page-title { font-size: 28px; font-weight: 700; color: #333; margin-bottom: 10px; position: relative; display: inline-block; padding-bottom: 10px; }
    .page-title::after { content: ''; position: absolute; bottom: 0; right: 0; width: 50px; height: 3px; background-color: #555; }

    /* --- بطاقة الترحيب ومعلومات الحساب (كما هي) --- */
    .welcome-card { background-color: #f4f9ff; border-radius: 12px; padding: 20px; margin-bottom: 25px; border-right: 4px solid #555; display: flex; align-items: center; }
    html[dir="ltr"] .welcome-card { border-right: none; border-left: 4px solid #555;}
    .welcome-icon { background-color: #555; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-left: 15px; margin-right: 0; flex-shrink: 0; }
    html[dir="ltr"] .welcome-icon { margin-left: 0; margin-right: 15px; }
    .welcome-text { flex: 1; }
    .welcome-title { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 5px; }
    .welcome-description { color: #666; margin-bottom: 0; }
    .customer-info { margin-bottom: 30px; }
    .info-section-title { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 15px; display: flex; align-items: center; }
    .info-list { background-color: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05); }
    .info-item { display: flex; margin-bottom: 15px; flex-wrap: wrap; }
    .info-item:last-child { margin-bottom: 0; }
    .info-label { font-weight: 600; color: #555; width: 120px; flex-shrink: 0; margin-left: 10px; margin-right: 0; }
    html[dir="ltr"] .info-label { margin-left: 0; margin-right: 10px; }
    .info-value { color: #333; word-break: break-all; }
    .info-value[dir="ltr"] { text-align: right; display: inline-block; }
    html[dir="ltr"] .info-value[dir="ltr"] { text-align: left; }

    /* --- تنسيقات بطاقة البيانات الرئيسية (للعناوين) --- */
    .data-card { margin-bottom: 25px; } /* سيتم استخدامها كحاوية للعنوان والصناديق */
    .data-card-header { background-color: transparent; padding: 0 0 15px 0; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
    .data-card-title { font-size: 18px; font-weight: 700; color: #333; margin: 0; display: flex; align-items: center; }
    .data-card-title i { margin-left: 8px; color: #555; }
    html[dir="ltr"] .data-card-title i { margin-left: 0; margin-right: 8px; }

    /* --- **جديد: تنسيقات الصندوق لكل عنصر** --- */
    .item-card {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 15px;
        padding: 15px;
        border: 1px solid #eee;
        display: flex;
        flex-direction: column;
        gap: 10px; /* مسافة بين العناصر داخل البطاقة */
    }
    .item-card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; /* للسماح بالالتفاف في الشاشات الصغيرة */
        gap: 10px;
    }
    .item-card .item-details {
        flex-grow: 1; /* لجعل التفاصيل تأخذ المساحة المتبقية */
        display: flex;
        flex-direction: column; /* تفاصيل فوق بعضها */
        gap: 5px;
    }
    .item-card .item-details .item-title {
        font-weight: 600;
        color: #333;
        font-size: 1rem; /* أو 1.1rem */
    }
    .item-card .item-details .item-subtitle,
    .item-card .item-details .item-info {
        font-size: 0.9rem;
        color: #666;
    }
    .item-card .item-status {
        flex-shrink: 0; /* لمنع تقلص قسم الحالة */
        text-align: left;
    }
     html[dir="ltr"] .item-card .item-status { text-align: right; }

    .item-card .item-actions {
        flex-shrink: 0;
        display: flex;
        gap: 8px;
        align-items: center; /* محاذاة الأزرار عمودياً */
        justify-content: flex-end; /* محاذاة الأزرار لليسار */
    }
     html[dir="ltr"] .item-card .item-actions { justify-content: flex-start; }

    .item-card .item-actions a,
    .item-card .item-actions button {
        padding: 6px 12px; /* تكبير الأزرار قليلاً */
        border-radius: 6px;
        font-size: 0.85rem; /* حجم خط الأزرار */
        font-weight: 500;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        cursor: pointer;
        line-height: 1.2;
        border: none;
    }
    /* نهاية تنسيقات الصندوق */

    .empty-message { padding: 25px; text-align: center; color: #777; background-color: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .empty-icon { font-size: 35px; color: #ddd; margin-bottom: 10px; }
    .badge { font-size: 0.85em; padding: 0.4em 0.7em; font-weight: 600; }
    .badge-unpaid { background-color: #ffc107; color: #664d03; }
    .badge-paid { background-color: #198754; color: white; }
    .badge-partially-paid { background-color: #0dcaf0; color: #055160; }
    .badge-cancelled { background-color: #dc3545; color: white; }
    .badge-failed { background-color: #dc3545; color: white; }
    .badge-pending { background-color: #6c757d; color: white; }
    .badge-expired { background-color: #adb5bd; color: #495057; }
    .badge-secondary { background-color: #6c757d; color: white; }
    .btn-pay { background-color: #28a745; color: white; }
    .btn-pay:hover { background-color: #218838; color: white;}
    .btn-details { background-color: #0d6efd; color: white; }
    .btn-details:hover { background-color: #0b5ed7; color: white; }
    .btn-invoice { background-color: #6c757d; color: white; }
    .btn-invoice:hover { background-color: #5c636a; color: white; }
    .page-actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-page-action { padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
    .btn-primary-action { background-color: #555; color: white; border: none; }
    .btn-primary-action:hover { background-color: #444; color: white; }
    .btn-secondary-action { background-color: transparent; color: #555; border: 1px solid #555; }
    .btn-secondary-action:hover { background-color: #f0f0f0; color: #333; }

    @media (max-width: 767px) {
        .page-title { font-size: 24px; }
        .welcome-card { flex-direction: column; text-align: center; }
        .welcome-icon { margin-left: 0; margin-right: 0; margin-bottom: 15px; }
        .info-item { flex-direction: column; align-items: flex-start; }
        .info-label { width: auto; margin-bottom: 5px; margin-left: 0; margin-right: 0; }
        .page-actions { flex-direction: column; }
        /* تعديلات الصندوق للشاشات الصغيرة */
        .item-card-row { flex-direction: column; align-items: flex-start; }
        .item-card .item-status, .item-card .item-actions { width: 100%; text-align: right; justify-content: flex-start; margin-top: 10px; }
         html[dir="ltr"] .item-card .item-status { text-align: left; }
         html[dir="ltr"] .item-card .item-actions { justify-content: flex-start; }
        .item-card .item-actions a, .item-card .item-actions button { flex-grow: 1; text-align: center; }
    }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="dashboard-wrapper">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">لوحة تحكم العميل</h1>
        </div>

        
        <div class="mb-4">
            <?php if(session('success')): ?> <div class="alert alert-success alert-dismissible fade show" role="alert"> <?php echo e(session('success')); ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> </div> <?php endif; ?>
            <?php if(session('error')): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"> <?php echo e(session('error')); ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> </div> <?php endif; ?>
            <?php if(session('info')): ?> <div class="alert alert-info alert-dismissible fade show" role="alert"> <?php echo e(session('info')); ?> <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> </div> <?php endif; ?>
        </div>


        <div class="row">
            <div class="col-md-12">
                
                <div class="welcome-card">
                    
                    <div class="welcome-text">
                        <h2 class="welcome-title">مرحباً بك، <?php echo e(auth()->user()->name ?? 'عميلنا العزيز'); ?>!</h2>
                        <p class="welcome-description">من هنا يمكنك إدارة حسابك ومتابعة حجوزاتك وفواتيرك بكل سهولة.</p>
                    </div>
                </div>

                <div class="customer-info">
                    <h3 class="info-section-title"> معلومات الحساب </h3>
                    <div class="info-list">
                        <div class="info-item"> <div class="info-label">الاسم:</div> <div class="info-value"><?php echo e(auth()->user()->name ?? 'غير محدد'); ?></div> </div>
                        <div class="info-item"> <div class="info-label">البريد الإلكتروني:</div> <div class="info-value"><?php echo e(auth()->user()->email ?? 'غير محدد'); ?></div> </div>
                        <div class="info-item"> <div class="info-label">رقم الجوال:</div> <div class="info-value" dir="ltr"><?php echo e(toArabicDigits(auth()->user()->mobile_number ?? 'غير محدد')); ?></div> </div>
                        <div class="info-item"> <div class="info-label">تاريخ التسجيل:</div> <div class="info-value"><?php echo e(auth()->user()->created_at ? toArabicDigits(\Carbon\Carbon::parse(auth()->user()->created_at)->translatedFormat('d F Y')) : 'غير محدد'); ?></div> </div>
                    </div>
                </div>

                <div class="row">
                    
                    
                    
                    <div class="col-lg-6 mb-4">
                        <div class="data-card"> 
                            <div class="data-card-header">
                                <h3 class="data-card-title"> المواعيد القادمة </h3>
                            </div>
                            <div class="data-card-content p-0"> 
                                <?php if(isset($upcomingBookings) && $upcomingBookings->count() > 0): ?>
                                    <div class="item-cards-list p-3"> 
                                        <?php $__currentLoopData = $upcomingBookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="card item-card mb-3">
                                                <div class="card-body">
                                                    <div class="item-card-row">
                                                        <div class="item-details">
                                                            <div class="item-title"><?php echo e($booking->service?->name_ar ?? 'N/A'); ?></div>
                                                            <div class="item-subtitle">
                                                                <?php if($booking->booking_datetime): ?>
                                                                    <?php echo e(toArabicDigits(\Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l, d F Y - H:i'))); ?>

                                                                    <small class="text-muted d-block">(<?php echo e(\Carbon\Carbon::parse($booking->booking_datetime)->diffForHumans()); ?>)</small>
                                                                <?php else: ?>
                                                                    N/A
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="item-status">
                                                            <span class="badge <?php echo e($booking->status_badge_class ?? 'badge-secondary'); ?>">
                                                                <?php echo e($booking->status_label ?? Str::ucfirst($booking->status)); ?>

                                                            </span>
                                                        </div>
                                                    </div>
                                                     <hr class="my-2"> 
                                                    <div class="item-actions justify-content-end"> 
                                                        <a href="<?php echo e(route('booking.pending', $booking->id)); ?>" class="btn-action btn-details" title="عرض التفاصيل">
                                                             عرض التفاصيل
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-message m-3"> 
                                        <p>لا توجد مواعيد قادمة.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    
                    
                    
                    <div class="col-lg-6 mb-4">
                         <div class="data-card">
                            <div class="data-card-header">
                                <h3 class="data-card-title"> الفواتير غير المدفوعة/المدفوعة جزئياً </h3>
                            </div>
                             <div class="data-card-content p-0">
                                <?php if(isset($unpaidInvoices) && $unpaidInvoices->count() > 0): ?>
                                     <div class="item-cards-list p-3">
                                        <?php $__currentLoopData = $unpaidInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="card item-card mb-3">
                                                <div class="card-body">
                                                    <div class="item-card-row">
                                                        <div class="item-details">
                                                            <div class="item-title">
                                                                فاتورة #<span class="invoice-number-cell" dir="ltr"><?php echo e($invoice->invoice_number); ?></span>
                                                                 <span class="text-muted small">(<?php echo e($invoice->booking?->service?->name_ar ?? 'N/A'); ?>)</span>
                                                            </div>
                                                            <div class="item-info">
                                                                المبلغ المتبقي: <?php echo e(toArabicDigits(number_format($invoice->remaining_amount, 0))); ?> <?php echo e($invoice->currency); ?>

                                                            </div>
                                                        </div>
                                                        <div class="item-status">
                                                             <span class="badge <?php echo e($invoice->status_badge_class ?? 'badge-secondary'); ?>">
                                                                <?php echo e($invoice->status_label ?? Str::ucfirst($invoice->status)); ?>

                                                            </span>
                                                        </div>
                                                    </div>
                                                     <hr class="my-2">
                                                    <div class="item-actions justify-content-end">
                                                         <a href="<?php echo e(route('customer.invoices.show', $invoice->id)); ?>" class="btn-action btn-invoice" title="عرض الفاتورة">
                                                             عرض الفاتورة
                                                         </a>
                                                         <?php if($invoice->payment_method == 'tamara' && in_array($invoice->status, [\App\Models\Invoice::STATUS_UNPAID, \App\Models\Invoice::STATUS_PARTIALLY_PAID, \App\Models\Invoice::STATUS_FAILED, \App\Models\Invoice::STATUS_CANCELLED, \App\Models\Invoice::STATUS_EXPIRED]) && $invoice->remaining_amount > 0): ?>
                                                             <form method="POST" action="<?php echo e(route('payment_retry_tamara', $invoice)); ?>" class="d-inline m-0">
                                                                 <?php echo csrf_field(); ?>
                                                                 <button type="submit" class="btn-action btn-pay" title="دفع الآن عبر تمارا"> دفع الآن </button>
                                                             </form>
                                                         <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                     </div>
                                <?php else: ?>
                                    <div class="empty-message m-3">
                                        <p>لا توجد فواتير تتطلب الدفع حالياً.</p>
                                    </div>
                                <?php endif; ?>
                             </div>
                        </div>
                    </div>
                </div>

                
                
                
                <div class="data-card"> 
                    <div class="data-card-header">
                        <h3 class="data-card-title"> سجل الحجوزات </h3>
                    </div>
                    <div class="data-card-content p-0">
                        <?php if(isset($bookingHistory) && $bookingHistory->count() > 0): ?>
                             <div class="item-cards-list p-3">
                                <?php $__currentLoopData = $bookingHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="card item-card mb-3">
                                        <div class="card-body">
                                            <div class="item-card-row">
                                                <div class="item-details">
                                                    <div class="item-title"><?php echo e($booking->service?->name_ar ?? 'N/A'); ?></div>
                                                    <div class="item-subtitle">
                                                        <?php if($booking->booking_datetime): ?>
                                                            <?php echo e(toArabicDigits(\Carbon\Carbon::parse($booking->booking_datetime)->translatedFormat('l, d F Y - H:i'))); ?>

                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="item-status">
                                                     <span class="badge <?php echo e($booking->status_badge_class ?? 'badge-secondary'); ?> me-1">
                                                        حالة الحجز: <?php echo e($booking->status_label ?? Str::ucfirst($booking->status)); ?>

                                                     </span>
                                                     <?php if($booking->invoice): ?>
                                                        <span class="badge <?php echo e($booking->invoice->status_badge_class ?? 'badge-secondary'); ?>">
                                                            الفاتورة: <?php echo e($booking->invoice->status_label ?? Str::ucfirst($booking->invoice->status)); ?>

                                                        </span>
                                                     <?php else: ?>
                                                        
                                                     <?php endif; ?>
                                                </div>
                                            </div>
                                             <hr class="my-2">
                                             <div class="item-actions justify-content-end">
                                                 <?php if($booking->invoice): ?>
                                                    <a href="<?php echo e(route('customer.invoices.show', $booking->invoice->id)); ?>" class="btn-action btn-invoice" title="عرض الفاتورة">
                                                         عرض الفاتورة
                                                    </a>
                                                 <?php endif; ?>
                                                 <a href="<?php echo e(route('booking.pending', $booking->id)); ?>" class="btn-action btn-details" title="عرض التفاصيل">
                                                      عرض التفاصيل
                                                 </a>
                                             </div>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                             </div>
                             
                             
                        <?php else: ?>
                            <div class="empty-message m-3">
                                <p>لا توجد حجوزات سابقة.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="page-actions">
                    <a href="<?php echo e(route('services.index')); ?>" class="btn-page-action btn-primary-action"> تصفح خدمات التصوير </a>
                    <a href="#" class="btn-page-action btn-secondary-action" onclick="event.preventDefault(); document.getElementById('logout-form-main').submit();"> تسجيل الخروج </a>
                    <form id="logout-form-main" action="<?php echo e(route('logout')); ?>" method="POST" class="d-none"> <?php echo csrf_field(); ?> </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
 
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/frontend/customer/dashboard.blade.php ENDPATH**/ ?>