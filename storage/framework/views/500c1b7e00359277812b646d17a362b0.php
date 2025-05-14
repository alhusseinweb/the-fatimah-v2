



<?php $__env->startSection('title', 'إدارة التوافر'); ?>

<?php $__env->startSection('content'); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة التوافر</h1>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="far fa-calendar-alt me-2"></i>جدول المواعيد الأسبوعي الافتراضي</h6>
        </div>
        <div class="card-body">
            <p class="text-muted">حدد الأيام والساعات العامة التي تكون فيها متاحًا للحجوزات.</p>
            <form action="<?php echo e(route('admin.availability.schedule.update')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php $__currentLoopData = $daysData ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="day-row mb-3 pb-3 <?php if(!$loop->last): ?> border-bottom <?php endif; ?>">
                        <div class="row align-items-center gy-2">
                            <div class="col-md-2 col-sm-12">
                                <span class="fw-bold d-block mb-2 mb-md-0"><?php echo e($day['name']); ?></span>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input active-checkbox" type="checkbox" role="switch" name="days[<?php echo e($key); ?>][active]" id="active_<?php echo e($key); ?>" value="1" <?php echo e(($day['active'] ?? false) ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="active_<?php echo e($key); ?>">متاح؟</label>
                                </div>
                            </div>
                            <div class="col-md-8 col-sm-8 time-fields" style="<?php echo e(($day['active'] ?? false) ? '' : 'display: none;'); ?>">
                                <div class="row gx-2 align-items-center">
                                    <div class="col-auto">
                                        <label for="start_<?php echo e($key); ?>" class="col-form-label small">من:</label>
                                    </div>
                                    <div class="col">
                                        <input type="time" name="days[<?php echo e($key); ?>][start]" id="start_<?php echo e($key); ?>" value="<?php echo e($day['start'] ?? '09:00'); ?>" class="form-control form-control-sm time-input">
                                    </div>
                                    <div class="col-auto">
                                        <label for="end_<?php echo e($key); ?>" class="col-form-label small">إلى:</label>
                                    </div>
                                    <div class="col">
                                        <input type="time" name="days[<?php echo e($key); ?>][end]" id="end_<?php echo e($key); ?>" value="<?php echo e($day['end'] ?? '17:00'); ?>" class="form-control form-control-sm time-input">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <div class="mt-4 pt-3 border-top">
                    <h6 class="mb-3 fw-bold">فترة الراحة بين المواعيد</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="booking_buffer_time" class="form-label">مدة الراحة (بالدقائق):</label>
                            
                            <input type="number" name="settings[booking_buffer_time]" id="booking_buffer_time" class="form-control <?php $__errorArgs = ['settings.booking_buffer_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('settings.booking_buffer_time', $settings['booking_buffer_time'] ?? 0)); ?>" min="0" step="5">
                            <small class="form-text text-muted">أدخل مدة الراحة المطلوبة بالدقائق بعد كل حجز (مثال: 30). اتركها 0 إذا كنت لا تريد فترة راحة.</small>
                             <?php $__errorArgs = ['settings.booking_buffer_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-success">
                         <i class="fas fa-save me-1"></i> حفظ الجدول الأسبوعي والإعدادات
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="card shadow-sm mb-4 border-0">
         <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-calendar-times me-2"></i>استثناءات التوافر (تواريخ/أوقات محظورة)</h6>
        </div>
         <div class="card-body">
             <p class="text-muted">أضف تواريخ أو فترات زمنية محددة لن تكون فيها متاحًا للحجوزات.</p>

             
             <?php if($errors->any() && !$errors->hasBag('exception_errors')): ?>
                  <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php $generalErrorsDisplayed = false; ?>
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(!Str::startsWith($error, 'settings.') && !Str::startsWith($error, 'days.')): ?>
                                <li><?php echo e($error); ?></li>
                                <?php $generalErrorsDisplayed = true; ?>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php if(!$generalErrorsDisplayed && $errors->any()): ?> 
                           <li>الرجاء مراجعة الأخطاء في الحقول أعلاه.</li>
                        <?php endif; ?>
                    </ul>
                </div>
             <?php endif; ?>


             <form action="<?php echo e(route('admin.availability.exceptions.store')); ?>" method="POST" class="mb-4 pb-4 border-bottom">
                 <?php echo csrf_field(); ?>
                 <div class="row g-3 align-items-end">
                     <div class="col-md-3 col-6">
                         <label for="exception_date" class="form-label">التاريخ:<span class="text-danger">*</span></label>
                         <input type="date" name="exception_date" id="exception_date" required class="form-control <?php $__errorArgs = ['exception_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('exception_date')); ?>">
                         <?php $__errorArgs = ['exception_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                     </div>
                     <div class="col-md-2 col-6">
                         <label for="start_time" class="form-label">من الوقت <small>(اختياري)</small>:</label>
                         <input type="time" name="start_time" id="start_time" class="form-control time-input <?php $__errorArgs = ['start_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('start_time')); ?>">
                         <?php $__errorArgs = ['start_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                     </div>
                     <div class="col-md-2 col-6">
                         <label for="end_time" class="form-label">إلى الوقت <small>(اختياري)</small>:</label>
                         <input type="time" name="end_time" id="end_time" class="form-control time-input <?php $__errorArgs = ['end_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('end_time')); ?>">
                         <?php $__errorArgs = ['end_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                     </div>
                      <div class="col-md-3 col-6">
                         <label for="notes" class="form-label">ملاحظات <small>(اختياري)</small>:</label>
                         <input type="text" name="notes" id="notes" class="form-control <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('notes')); ?>">
                         <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                     </div>
                     <div class="col-md-2 col-12">
                         <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>إضافة استثناء</button>
                     </div>
                 </div>
                  <small class="form-text text-muted d-block mt-2">اترك حقول الوقت فارغة لحظر اليوم بأكمله.</small>
             </form>

             <h5 class="mt-4 mb-3">الاستثناءات الحالية:</h5>
             <?php if(isset($exceptions) && $exceptions->count() > 0): ?>
                 <div class="table-responsive">
                     <table class="table table-sm table-bordered table-striped table-hover" style="width: 100%;">
                         <thead class="table-light">
                             <tr>
                                 <th>التاريخ</th>
                                 <th>من الوقت</th>
                                 <th>إلى الوقت</th>
                                 <th>ملاحظات</th>
                                 <th class="text-center">الإجراءات</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php $__currentLoopData = $exceptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $exception): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                 <tr>
                                     <td><?php echo e($exception->date->format('Y-m-d')); ?></td>
                                     <td><?php echo e($exception->start_time ? \Carbon\Carbon::parse($exception->start_time)->translatedFormat('h:i A') : '---'); ?></td>
                                     <td><?php echo e($exception->end_time ? \Carbon\Carbon::parse($exception->end_time)->translatedFormat('h:i A') : '---'); ?></td>
                                     <td><?php echo e($exception->notes ?? '-'); ?></td>
                                     <td class="text-center">
                                         <form action="<?php echo e(route('admin.availability.exceptions.destroy', $exception->id)); ?>" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الاستثناء؟');">
                                             <?php echo csrf_field(); ?>
                                             <?php echo method_field('DELETE'); ?>
                                             <button type="submit" class="btn btn-danger btn-sm" title="حذف">
                                                  <i class="fas fa-trash-alt"></i>
                                             </button>
                                         </form>
                                     </td>
                                 </tr>
                             <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                         </tbody>
                     </table>
                 </div>
                  <?php if($exceptions instanceof \Illuminate\Pagination\LengthAwarePaginator): ?>
                    <div class="mt-3 d-flex justify-content-center">
                         <?php echo e($exceptions->links()); ?>

                    </div>
                 <?php endif; ?>
             <?php else: ?>
                 <p class="text-muted">لا توجد استثناءات مضافة حالياً.</p>
             <?php endif; ?>
         </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.active-checkbox').forEach(checkbox => {
            const dayRow = checkbox.closest('.day-row');
            if (!dayRow) return;
            const timeFields = dayRow.querySelector('.time-fields');
            if (!timeFields) return;

            const toggleTimeFields = (isChecked) => {
                timeFields.style.display = isChecked ? '' : 'none';
                 timeFields.querySelectorAll('input.time-input').forEach(input => {
                    input.disabled = !isChecked;
                });
            };

            toggleTimeFields(checkbox.checked);
            checkbox.addEventListener('change', function() {
                toggleTimeFields(this.checked);
            });
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .day-row .time-input {
        font-size: 0.875rem;
    }
     .day-row .col-form-label.small {
        padding-top: calc(0.25rem + 1px);
        padding-bottom: calc(0.25rem + 1px);
        font-size: 0.8em;
        margin-bottom: 0;
        line-height: 1.5;
        white-space: nowrap;
    }
     .form-check-input.active-checkbox {
        transform: scale(1.2);
        cursor: pointer;
        float: none;
        margin-left: 0.5rem;
    }
    .form-check.form-switch {
        padding-right: 3rem;
        padding-left: 0;
    }
    .invalid-feedback {
        margin-top: 0.25rem;
        font-size: 0.875em;
    }
    /* تحسين بسيط لكيفية عرض الأخطاء العامة */
    .alert ul {
        padding-right: 1.5rem; /* تعديل الحشو ليتناسب مع RTL */
    }
</style>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\fatimah-booking-system\resources\views/admin/availability/index.blade.php ENDPATH**/ ?>