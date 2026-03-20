<?php $__env->startSection('title', 'إدارة الخدمات'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة الخدمات</h1>
        <a href="<?php echo e(route('admin.services.create')); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة خدمة جديدة
        </a>
    </div>

    
    <?php if($services->count() > 0): ?>
        
        
        
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col">
                    
                    <div class="card service-card h-100 shadow-sm border-0"> 
                        
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <h6 class="mb-0 fw-bold text-primary"><?php echo e($service->name_ar); ?></h6>
                            <span class="badge <?php echo e($service->is_active ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'); ?>">
                                <?php echo e($service->is_active ? 'فعال' : 'غير فعال'); ?>

                            </span>
                        </div>
                        
                        <div class="card-body d-flex flex-column"> 
                            <p class="mb-2 text-muted small">
                                <i class="fas fa-tag fa-fw me-1"></i>
                                <?php echo e($service->serviceCategory?->name_ar ?? '-'); ?>

                            </p>
                            <p class="mb-2">
                                <i class="fas fa-clock fa-fw me-1 text-muted"></i>
                                <strong>المدة:</strong> <?php echo e($service->duration_hours); ?> ساعات
                            </p>
                            <p class="mb-3"> 
                                <i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>
                                <strong>السعر:</strong> <?php echo e(number_format($service->price_sar, 2)); ?> ريال
                            </p>

                            
                            <div class="mt-auto text-end"> 
                                
                                <a href="<?php echo e(route('admin.services.edit', $service->id)); ?>" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                
                                <form action="<?php echo e(route('admin.services.destroy', $service->id)); ?>" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الخدمة؟');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                        <i class="fas fa-trash-alt"></i> حذف
                                    </button>
                                </form>
                            </div>
                        </div> 
                    </div> 
                </div> 
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div> 

        
         <?php if($services instanceof \Illuminate\Pagination\LengthAwarePaginator): ?>
            <div class="mt-4 pt-2 d-flex justify-content-center"> 
                 <?php echo e($services->links()); ?>

            </div>
         <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-warning text-center">لم يتم العثور على أي خدمات.</div>
    <?php endif; ?>

<?php $__env->stopSection(); ?>



<?php $__env->startPush('styles'); ?>
<style>
    .badge.bg-success-soft { background-color: rgba(25, 135, 84, 0.15); }
    .badge.bg-danger-soft { background-color: rgba(220, 53, 69, 0.15); }
    .text-success { color: #198754 !important; }
    .text-danger { color: #dc3545 !important; }

    /* تحسين بسيط لبطاقة الخدمة */
    .service-card .card-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 0.75rem 1rem;
    }
    .service-card .card-body {
        padding: 1rem;
        font-size: 0.9em;
    }
    .service-card .card-body p i {
        width: 1.4em; /* محاذاة الأيقونات */
    }
    .service-card .card-body .btn {
        font-size: 0.85em; /* تصغير الأزرار قليلاً */
    }

</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/admin/services/index.blade.php ENDPATH**/ ?>