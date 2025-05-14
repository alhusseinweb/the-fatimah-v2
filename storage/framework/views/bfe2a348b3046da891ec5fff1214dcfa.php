


<?php $__env->startSection('title', 'إدارة فئات الخدمات'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة فئات الخدمات</h1>
        <a href="<?php echo e(route('admin.service-categories.create')); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة فئة جديدة
        </a>
    </div>

    
    <?php if($categories->count() > 0): ?>
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col">
                    
                    <div class="card category-card h-100 shadow-sm border-0"> 
                        
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-bold text-primary"><?php echo e($category->name_ar); ?></h6>
                            
                            
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            
                            <?php if($category->description_ar): ?>
                                <p class="mb-3 text-muted small flex-grow-1">
                                    <i class="fas fa-info-circle fa-fw me-1"></i>
                                    <?php echo e(Str::limit($category->description_ar, 120)); ?> 
                                </p>
                            <?php else: ?>
                                
                                <div class="flex-grow-1"></div>
                            <?php endif; ?>

                            
                            <div class="mt-auto text-end pt-2 border-top-dashed">
                                <a href="<?php echo e(route('admin.service-categories.edit', $category->id)); ?>" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                    <i class="fas fa-edit"></i> <span class="d-none d-md-inline">تعديل</span>
                                </a>
                                <form action="<?php echo e(route('admin.service-categories.destroy', $category->id)); ?>" method="POST" class="d-inline" onsubmit="return confirm('تحذير! حذف هذه الفئة سيؤدي أيضاً إلى حذف جميع الخدمات المرتبطة بها. هل أنت متأكد من المتابعة؟');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                        <i class="fas fa-trash-alt"></i> <span class="d-none d-md-inline">حذف</span>
                                    </button>
                                </form>
                            </div>
                        </div> 
                    </div> 
                </div> 
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div> 

        
         <?php if($categories instanceof \Illuminate\Pagination\LengthAwarePaginator): ?>
            <div class="mt-4 pt-2 d-flex justify-content-center">
                 <?php echo e($categories->links()); ?>

            </div>
         <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-warning text-center">لم يتم العثور على فئات خدمات.</div>
    <?php endif; ?>

<?php $__env->stopSection(); ?>


<?php $__env->startPush('styles'); ?>
<style>
    /* تحسين بسيط لبطاقة الفئة */
    .category-card .card-header {
        border-bottom: 1px solid #f0f0f0;
        padding: 0.75rem 1rem;
    }
    .category-card .card-body {
        padding: 1rem;
    }
    .category-card .card-body p.small {
        font-size: 0.85em; /* تصغير خط الوصف */
        line-height: 1.6;
    }
     .category-card .card-body p i.fa-fw {
        width: 1.4em;
        text-align: center;
        color: #a0aec0;
    }
    .category-card .card-body .btn {
        font-size: 0.85em; /* تصغير الأزرار قليلاً */
    }
    /* خط منقط لفاصل الأزرار */
    .border-top-dashed {
        border-top: 1px dashed #e9ecef;
    }
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/categories/index.blade.php ENDPATH**/ ?>