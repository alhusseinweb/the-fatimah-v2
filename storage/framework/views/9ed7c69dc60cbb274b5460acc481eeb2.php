

<?php $__env->startSection('title', 'إدارة قوالب الرسائل النصية'); ?>

<?php $__env->startSection('content'); ?>
    <h1 class="h3 mb-4 text-gray-800">إدارة قوالب الرسائل النصية (SMS)</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">قائمة القوالب المتاحة</h6>
        </div>
        <div class="card-body">
            <?php if(session('success')): ?>
                <div class="alert alert-success"><?php echo e(session('success')); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>الوصف</th>
                            <th>نوع المستلم</th>
                            <th>آخر تحديث</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($template->description ?? $template->notification_type); ?></td>
                                <td>
                                    <?php if($template->recipient_type == 'customer'): ?>
                                        <span class="badge bg-success">للعميل</span>
                                    <?php elseif($template->recipient_type == 'admin'): ?>
                                        <span class="badge bg-info text-dark">للمدير</span>
                                    <?php else: ?>
                                        <?php echo e($template->recipient_type); ?>

                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($template->updated_at->diffForHumans()); ?></td>
                                <td>
                                    <a href="<?php echo e(route('admin.sms-templates.edit', $template->id)); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="text-center">لا توجد قوالب SMS معرفة حالياً.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             <p class="mt-3 text-muted small">
                 ملاحظة: يتم استخدام هذه القوالب لإنشاء محتوى رسائل SMS المرسلة تلقائياً من النظام.
                 تعديل القالب سيؤثر على جميع الرسائل المستقبلية من نفس النوع.
             </p>
        </div>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/sms_templates/index.blade.php ENDPATH**/ ?>