


<?php $__env->startSection('title', 'إضافة فئة خدمة جديدة'); ?>

<?php $__env->startSection('content'); ?>

<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 fw-bold"><i class="fas fa-plus me-2"></i>إضافة فئة خدمة جديدة</h6>
    </div>
    <div class="card-body">
        
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">

                <?php if($errors->any()): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?php echo e(route('admin.service-categories.store')); ?>" method="POST">
                    <?php echo csrf_field(); ?>

                    
                    <div class="mb-3">
                        <label for="name_ar" class="form-label">اسم الفئة (بالعربية):<span class="text-danger">*</span></label>
                        <input type="text" id="name_ar" name="name_ar" class="form-control <?php $__errorArgs = ['name_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('name_ar')); ?>" required>
                        <?php $__errorArgs = ['name_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback d-block"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    
                    <div class="mb-3">
                        <label for="description_ar" class="form-label">الوصف (بالعربية):</label>
                        <textarea id="description_ar" name="description_ar" class="form-control <?php $__errorArgs = ['description_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" rows="5"><?php echo e(old('description_ar')); ?></textarea>
                        <?php $__errorArgs = ['description_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback d-block"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    
                    <div class="mt-4 d-flex justify-content-end">
                         <a href="<?php echo e(route('admin.service-categories.index')); ?>" class="btn btn-secondary me-2 px-4">إلغاء</a>
                         <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> حفظ الفئة</button>
                    </div>

                </form>

            </div> 
        </div> 
    </div> 
</div> 

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/categories/create.blade.php ENDPATH**/ ?>