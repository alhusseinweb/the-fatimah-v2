



<?php $__env->startSection('title', 'إدارة الحجوزات'); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="card shadow-sm mb-4 border-0"> 
        <div class="card-body">
            <form method="GET" action="<?php echo e(route('admin.bookings.index')); ?>" class="row g-3 align-items-center">
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
                    <a href="<?php echo e(route('admin.bookings.index')); ?>" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i> إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary">قائمة الحجوزات الواردة <?php if(request('status')): ?> - (<?php echo e($statuses[request('status')] ?? request('status')); ?>) <?php endif; ?></h6>
        </div>
        <div class="card-body">
            
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php $__empty_1 = true; $__currentLoopData = $bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="col">
                        <div class="card booking-card h-100 shadow-sm border-0"> 
                            <div class="card-header d-flex justify-content-between align-items-center bg-light-subtle">
                                <h6 class="mb-0 fw-bold small">
                                    <i class="fas fa-user me-1"></i> <?php echo e($booking->user?->name ?? 'N/A'); ?>

                                </h6>
                                <span class="badge bg-secondary rounded-pill small">#<?php echo e($booking->id); ?></span>
                            </div>
                            <div class="card-body pb-2"> 
                                <p class="mb-2 text-dark">
                                    <i class="fas fa-concierge-bell fa-fw me-1 text-muted"></i>
                                    <?php echo e($booking->service?->name_ar ?? 'N/A'); ?>

                                </p>
                                <p class="mb-2 small">
                                    <i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i>
                                    <?php echo e($booking->booking_datetime ? $booking->booking_datetime->translatedFormat('d M Y') : '-'); ?>

                                </p>
                                <p class="mb-0 small">
                                    <i class="fas fa-clock fa-fw me-1 text-muted"></i>
                                    <?php echo e($booking->booking_datetime ? $booking->booking_datetime->translatedFormat('h:i A') : '-'); ?>

                                </p>
                            </div>
                            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center border-top-dashed pt-2">
                                
                                <span class="status-pill <?php echo e($booking->status_badge_class ?? 'bg-secondary'); ?>"> 
                                    <?php echo e($booking->status_label ?? $booking->status); ?> 
                                </span>
                                
                                

                                <a href="<?php echo e(route('admin.bookings.show', $booking)); ?>" class="btn btn-outline-primary btn-sm" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i> <span class="d-none d-md-inline">التفاصيل</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    
                    <div class="col-12">
                        <div class="alert alert-warning text-center">لا توجد حجوزات تطابق الفلترة الحالية.</div>
                    </div>
                <?php endif; ?>
            </div> 
        </div> 

         
         <?php if($bookings->hasPages()): ?> 
             <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                 <?php echo e($bookings->appends(request()->query())->links()); ?> 
             </div>
         <?php endif; ?>

    </div> 

<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>

<style>
/* يمكنك إعادة استخدام تنسيقات .booking-card من admin.css أو تعريفها هنا */
.booking-card .card-header { font-size: 0.85em; padding: 0.6rem 1rem; }
.booking-card .card-header .badge { font-size: 0.8em;}
.booking-card .card-body { padding: 1rem; font-size: 0.9em;}
.booking-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.booking-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef; }
.booking-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; } /* تأكيد ظهور الخط المنقط */
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/bookings/index.blade.php ENDPATH**/ ?>