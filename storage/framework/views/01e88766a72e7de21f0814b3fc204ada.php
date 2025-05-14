
<?php $__env->startSection('title', 'إدارة العملاء'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">إدارة العملاء</h1>
    
    
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">قائمة العملاء المسجلين</h6>
        <form action="<?php echo e(route('admin.customers.index')); ?>" method="GET" class="mt-2">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم, البريد, أو الجوال..." value="<?php echo e(request('search')); ?>">
                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <?php if(session('success')): ?>
            <div class="alert alert-success"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if(session('error')): ?>
            <div class="alert alert-danger"><?php echo e(session('error')); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>رقم الجوال</th>
                        <th>تاريخ التسجيل</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($customer->id); ?></td>
                            <td><?php echo e($customer->name); ?></td>
                            <td><?php echo e($customer->email); ?></td>
                            <td dir="ltr"><?php echo e($customer->mobile_number); ?></td>
                            <td><?php echo e($customer->created_at->translatedFormat('d M Y, h:i A')); ?></td>
                            <td>
                                <a href="<?php echo e(route('admin.customers.show', $customer->id)); ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo e(route('admin.customers.edit', $customer->id)); ?>" class="btn btn-sm btn-primary" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="text-center">لا يوجد عملاء مسجلون حالياً.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($customers->hasPages()): ?>
            <div class="mt-3">
                <?php echo e($customers->appends(request()->query())->links()); ?>

            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/customers/index.blade.php ENDPATH**/ ?>