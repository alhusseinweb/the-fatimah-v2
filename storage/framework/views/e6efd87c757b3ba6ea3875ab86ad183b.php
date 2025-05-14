


<?php $__env->startSection('title', 'إدارة أكواد الخصم'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة أكواد الخصم</h1>
        <a href="<?php echo e(route('admin.discount-codes.create')); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة كود خصم جديد
        </a>
    </div>

    

    <?php
        // جلب مصفوفة الأنواع لترجمتها (يمكن نقل هذا للمتحكم إذا كان أفضل)
        $types = \App\Models\DiscountCode::types();
    ?>

    
    <?php if($discountCodes->count() > 0): ?>
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">قائمة أكواد الخصم</h6>
            </div>
            <div class="card-body">
                
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    <?php $__empty_1 = true; $__currentLoopData = $discountCodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="col">
                            
                            <div class="card discount-code-card h-100 shadow-sm border-0">
                                
                                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                                    <h6 class="mb-0 fw-bold text-primary" style="font-family: monospace, sans-serif; direction: ltr; text-align:left;"> 
                                        <i class="fas fa-barcode me-2"></i><?php echo e($code->code); ?>

                                    </h6>
                                    
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-2 <?php echo e($code->is_active ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'); ?>">
                                            <?php echo e($code->is_active ? 'مفعل' : 'معطل'); ?>

                                        </span>
                                        <form action="<?php echo e(route('admin.discount-codes.toggleActive', $code)); ?>" method="POST" class="d-inline-block">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <button type="submit" class="btn btn-xs <?php echo e($code->is_active ? 'btn-outline-secondary' : 'btn-outline-success'); ?>" title="<?php echo e($code->is_active ? 'تعطيل' : 'تفعيل'); ?>">
                                                <i class="fas fa-power-off fa-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="card-body pb-2">
                                    <p class="mb-2 small">
                                        <i class="fas fa-tag fa-fw me-1 text-muted"></i>
                                        <strong>النوع:</strong> <?php echo e($types[$code->type] ?? $code->type); ?>

                                    </p>
                                    <p class="mb-2 small">
                                        <i class="fas fa-money-bill-wave fa-fw me-1 text-muted"></i>
                                        <strong>القيمة:</strong> <?php echo e($code->value); ?><?php echo e($code->type == \App\Models\DiscountCode::TYPE_PERCENTAGE ? '%' : ' ريال'); ?>

                                    </p>
                                    <p class="mb-2 small">
                                        <i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>
                                        <strong>الصلاحية:</strong>
                                        <?php echo e($code->start_date ? $code->start_date->format('Y-m-d') : '---'); ?>

                                        <i class="fas fa-long-arrow-alt-left mx-1"></i>
                                        <?php echo e($code->end_date ? $code->end_date->format('Y-m-d') : 'مفتوح'); ?>

                                    </p>
                                    <p class="mb-0 small">
                                        <i class="fas fa-users fa-fw me-1 text-muted"></i>
                                        <strong>الاستخدام:</strong> <?php echo e($code->current_uses); ?> / <?php echo e($code->max_uses ?? '∞'); ?>

                                    </p>
                                </div>
                                
                                <div class="card-footer bg-transparent text-end border-top-dashed pt-2">
                                    <a href="<?php echo e(route('admin.discount-codes.edit', $code)); ?>" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                        <i class="fas fa-edit"></i> <span class="d-none d-md-inline">تعديل</span>
                                    </a>
                                    <form action="<?php echo e(route('admin.discount-codes.destroy', $code)); ?>" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف كود الخصم هذا؟');">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                            <i class="fas fa-trash-alt"></i> <span class="d-none d-md-inline">حذف</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        
                        <div class="col-12">
                            <div class="alert alert-warning text-center">لا توجد أكواد خصم مضافة حالياً.</div>
                        </div>
                    <?php endif; ?>
                </div> 
            </div> 

             
             <?php if($discountCodes->hasPages()): ?>
                 <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                     <?php echo e($discountCodes->links()); ?>

                 </div>
             <?php endif; ?>

        </div> 
    <?php else: ?>
         
         <div class="alert alert-warning text-center">لا توجد أكواد خصم مضافة حالياً.</div>
    <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>

<style>
/* يمكنك إعادة استخدام تنسيقات البطاقات السابقة أو تعريف .discount-code-card */
.discount-code-card .card-header { font-size: 0.9em; padding: 0.6rem 1rem; align-items: center; }
.discount-code-card .card-header .badge { font-size: 0.75em; padding: 0.3em 0.5em; }
.discount-code-card .card-header .btn-xs { padding: 0.1rem 0.3rem; font-size: 0.7rem; line-height: 1; }
.discount-code-card .card-header .fa-xs { font-size: 0.7em; }

.discount-code-card .card-body { padding: 1rem; }
.discount-code-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.discount-code-card .card-body p.small { font-size: 0.85em; line-height: 1.5; }
.discount-code-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef !important; }
.discount-code-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; }
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/discount_codes/index.blade.php ENDPATH**/ ?>