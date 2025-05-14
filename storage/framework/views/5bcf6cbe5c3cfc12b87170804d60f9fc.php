

 

<?php $__env->startSection('content'); ?>
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800">إضافة كود خصم جديد</h1>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="<?php echo e(route('admin.discount-codes.store')); ?>" method="POST">
                    <?php echo csrf_field(); ?>

                    
                    <?php echo $__env->make('admin.discount_codes._form', ['discountCode' => $discountCode, 'types' => $types], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                </form>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/discount_codes/create.blade.php ENDPATH**/ ?>