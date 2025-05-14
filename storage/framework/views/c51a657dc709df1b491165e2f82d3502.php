



<?php $__env->startSection('title', 'إدارة الفواتير'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="<?php echo e(route('admin.invoices.index')); ?>" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="status" class="form-label">تصفية حسب الحالة:</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">-- كل الحالات --</option>
                        <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($key); ?>" <?php echo e(request('status') == $key ? 'selected' : ''); ?>>
                                <?php echo e($label); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                
                <div class="col-md-auto align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i> تصفية</button>
                    <a href="<?php echo e(route('admin.invoices.index')); ?>" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i> إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary">قائمة الفواتير <?php if(request('status')): ?> - (<?php echo e($statuses[request('status')] ?? request('status')); ?>) <?php endif; ?></h6>
        </div>
        <div class="card-body">
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="col">
                        <div class="card invoice-card h-100 shadow-sm border-0"> 
                            
                            <div class="card-header d-flex justify-content-between align-items-center bg-light-subtle">
                                <h6 class="mb-0 fw-bold small">
                                    <i class="fas fa-file-invoice me-1"></i> فاتورة #<?php echo e($invoice->invoice_number); ?>

                                </h6>
                                <span class="text-muted small" title="العميل">
                                    <i class="fas fa-user me-1"></i><?php echo e($invoice->booking?->user?->name ?? 'N/A'); ?>

                                </span>
                            </div>
                             
                            <div class="card-body pb-2">
                                <p class="mb-2 fw-bold fs-6"> 
                                    <i class="fas fa-dollar-sign fa-fw me-1 text-muted"></i>
                                    <?php echo e(number_format($invoice->amount, 2)); ?> <?php echo e($invoice->currency ?: 'ريال'); ?>

                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-info-circle fa-fw me-1 text-muted"></i>
                                    الحالة:
                                    
                                    <span class="status-pill <?php echo e($invoice->status_badge_class ?? 'bg-secondary'); ?>">
                                        <?php echo e($invoice->status_label ?? $invoice->status); ?>

                                    </span>
                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-calendar-plus fa-fw me-1 text-muted"></i>
                                    تاريخ الإنشاء: <?php echo e($invoice->created_at ? $invoice->created_at->format('Y-m-d') : '-'); ?>

                                </p>
                                <?php if($invoice->due_date): ?>
                                    <p class="mb-2 small <?php echo e($invoice->due_date->isPast() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled' ? 'text-danger fw-bold' : ''); ?>">
                                        <i class="fas fa-calendar-times fa-fw me-1 text-muted"></i>
                                        تاريخ الاستحقاق: <?php echo e($invoice->due_date->format('Y-m-d')); ?>

                                         
                                         <?php if($invoice->due_date->isPast() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled'): ?> (متأخر) <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <p class="mb-0 small">
                                    <i class="fas fa-credit-card fa-fw me-1 text-muted"></i>
                                    طريقة الدفع: <?php echo e($invoice->payment_method ?: '-'); ?>

                                </p>
                            </div>
                             
                            <div class="card-footer bg-transparent text-end border-top-dashed pt-2">
                                <a href="<?php echo e(route('admin.invoices.show', $invoice)); ?>" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                </a>
                                
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    
                    <div class="col-12">
                        <div class="alert alert-warning text-center">لا توجد فواتير تطابق الفلترة الحالية.</div>
                    </div>
                <?php endif; ?>
            </div> 
        </div> 

         
         <?php if($invoices->hasPages()): ?>
             <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                 <?php echo e($invoices->appends(request()->query())->links()); ?>

             </div>
         <?php endif; ?>

    </div> 

<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>

<style>
/* يمكنك إعادة استخدام تنسيقات .booking-card أو .service-card أو تعريف .invoice-card */
.invoice-card .card-header { font-size: 0.85em; padding: 0.6rem 1rem; }
.invoice-card .card-body { padding: 1rem; }
.invoice-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.invoice-card .card-body p.small { font-size: 0.85em; }
.invoice-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef; }
.invoice-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; }
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/invoices/index.blade.php ENDPATH**/ ?>