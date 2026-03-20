<?php $__env->startSection('title', 'الإعدادات العامة والتكوين'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .card-header h5 { margin-bottom: 0; display: flex; align-items: center; }
    .card-header i.fas { margin-left: 0.5rem; }
    html[dir="ltr"] .card-header i.fas { margin-left: 0; margin-right: 0.5rem; }
    .nav-tabs .nav-link.active { background-color: #f8f9fa; border-bottom-color: #dee2e6; }
    .form-text { font-size: 0.8rem; }
    .image-preview-container { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
    .image-preview-item { position: relative; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
    .image-preview-item img { max-width: 100px; max-height: 100px; object-fit: cover; }
    .delete-image-btn { position: absolute; top: -5px; right: -5px; background-color: rgba(255, 0, 0, 0.7); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; line-height: 20px; text-align: center; cursor: pointer; padding: 0; }
     html[dir="ltr"] .delete-image-btn { right: auto; left: -5px; }
    .flash-highlight { animation: flash-animation 1s 2; }
    @keyframes flash-animation { 0% { background-color: transparent; } 50% { background-color: rgba(255, 255, 0, 0.3); } 100% { background-color: transparent; } }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">الإعدادات العامة والتكوين</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">الرئيسية</a></li>
                        <li class="breadcrumb-item active">الإعدادات العامة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if(session('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo e(session('error')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="<?php echo e(route('admin.settings.update')); ?>" method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>

        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-settings-tab" data-bs-toggle="tab" data-bs-target="#general-settings" type="button" role="tab" aria-controls="general-settings" aria-selected="true">
                    <i class="fas fa-cog me-1"></i>إعدادات الموقع الأساسية
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="contact-settings-tab" data-bs-toggle="tab" data-bs-target="#contact-settings" type="button" role="tab" aria-controls="contact-settings" aria-selected="false">
                    <i class="fas fa-address-book me-1"></i>معلومات التواصل
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="booking-settings-tab" data-bs-toggle="tab" data-bs-target="#booking-settings" type="button" role="tab" aria-controls="booking-settings" aria-selected="false">
                    <i class="fas fa-calendar-check me-1"></i>إعدادات الحجز
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payment-settings-tab" data-bs-toggle="tab" data-bs-target="#payment-settings" type="button" role="tab" aria-controls="payment-settings" aria-selected="false">
                    <i class="fas fa-credit-card me-1"></i>إعدادات الدفع
                </button>
            </li>
             <li class="nav-item" role="presentation">
                <button class="nav-link" id="data-management-tab" data-bs-toggle="tab" data-bs-target="#data-management" type="button" role="tab" aria-controls="data-management" aria-selected="false">
                    <i class="fas fa-database me-1"></i>إدارة البيانات
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabsContent">
            <div class="tab-pane fade show active" id="general-settings" role="tabpanel" aria-labelledby="general-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> معلومات الموقع الأساسية</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="site_name_ar" class="form-label">اسم الموقع (عربي)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['site_name_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="site_name_ar" name="site_name_ar" value="<?php echo e(old('site_name_ar', $settings['site_name_ar'] ?? config('app.name', 'Fatimah Booking'))); ?>">
                            <?php $__errorArgs = ['site_name_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <label for="site_name_en" class="form-label">اسم الموقع (إنجليزي)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['site_name_en'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="site_name_en" name="site_name_en" value="<?php echo e(old('site_name_en', $settings['site_name_en'] ?? '')); ?>">
                            <?php $__errorArgs = ['site_name_en'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="logo_light_file" class="form-label">الشعار (النسخة الفاتحة/الرئيسية)</label>
                                <input type="file" class="form-control <?php $__errorArgs = ['logo_light_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="logo_light_file" name="logo_light_file">
                                <?php if(isset($settings['logo_path_light']) && $settings['logo_path_light']): ?> <img src="<?php echo e(asset($settings['logo_path_light'])); ?>" alt="الشعار الحالي" style="max-height: 50px; margin-top: 10px;"> <?php endif; ?>
                                <?php $__errorArgs = ['logo_light_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="logo_dark_file" class="form-label">الشعار (النسخة الداكنة - للقوائم)</label>
                                <input type="file" class="form-control <?php $__errorArgs = ['logo_dark_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="logo_dark_file" name="logo_dark_file">
                                <?php if(isset($settings['logo_path_dark']) && $settings['logo_path_dark']): ?> <img src="<?php echo e(asset($settings['logo_path_dark'])); ?>" alt="الشعار الداكن الحالي" style="max-height: 50px; margin-top: 10px; background-color: #333; padding: 5px;"> <?php endif; ?>
                                <?php $__errorArgs = ['logo_dark_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="favicon_file" class="form-label">أيقونة الموقع (Favicon)</label>
                                <input type="file" class="form-control <?php $__errorArgs = ['favicon_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="favicon_file" name="favicon_file">
                                <?php if(isset($settings['favicon_path']) && $settings['favicon_path']): ?> <img src="<?php echo e(asset($settings['favicon_path'])); ?>" alt="Favicon الحالي" style="max-height: 32px; margin-top: 10px;"> <?php endif; ?>
                                <?php $__errorArgs = ['favicon_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="slider_images" class="form-label">صور السلايدر في الصفحة الرئيسية</label>
                            <input type="file" class="form-control <?php $__errorArgs = ['slider_images.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="slider_images" name="slider_images[]" multiple>
                            <small class="form-text text-muted">يمكنك اختيار عدة صور. الصور الجديدة ستُضاف إلى الصور الحالية.</small>
                            <?php $__errorArgs = ['slider_images.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <?php if(!empty($settings['homepage_slider_images'])): ?>
                                <div class="mt-2"><strong>الصور الحالية:</strong></div>
                                <div class="image-preview-container">
                                    <?php $__currentLoopData = $settings['homepage_slider_images']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $imagePath): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($imagePath): ?>
                                        <div class="image-preview-item" id="slider-item-<?php echo e(md5($imagePath)); ?>">
                                            <img src="<?php echo e(asset($imagePath)); ?>" alt="صورة سلايدر">
                                            <button type="button" class="delete-image-btn" onclick="deleteSliderImage('<?php echo e($imagePath); ?>', 'slider-item-<?php echo e(md5($imagePath)); ?>')">&times;</button>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <input type="hidden" name="deleted_slider_images_json" id="deleted_slider_images_input" value="[]">
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo e(old('maintenance_mode', $settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="maintenance_mode">تفعيل وضع الصيانة</label>
                            </div>
                            <?php $__errorArgs = ['maintenance_mode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="contact-settings" role="tabpanel" aria-labelledby="contact-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-phone-alt"></i> معلومات التواصل الأساسية</h5>
                    </div>
                    <div class="card-body">
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_email" class="form-label">البريد الإلكتروني للتواصل</label>
                                <input type="email" class="form-control <?php $__errorArgs = ['contact_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="contact_email" name="contact_email" value="<?php echo e(old('contact_email', $settings['contact_email'] ?? '')); ?>">
                                <?php $__errorArgs = ['contact_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_phone" class="form-label">رقم الهاتف للتواصل (اختياري)</label>
                                <input type="text" class="form-control <?php $__errorArgs = ['contact_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="contact_phone" name="contact_phone" value="<?php echo e(old('contact_phone', $settings['contact_phone'] ?? '')); ?>">
                                <?php $__errorArgs = ['contact_phone'];
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
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fab fa-whatsapp"></i> إعدادات الواتساب</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_whatsapp" class="form-label">رقم الواتساب (مع رمز الدولة)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['contact_whatsapp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="contact_whatsapp" name="contact_whatsapp" value="<?php echo e(old('contact_whatsapp', $settings['contact_whatsapp'] ?? '')); ?>" placeholder="+9665XXXXXXXX">
                            <?php $__errorArgs = ['contact_whatsapp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="display_whatsapp_contact" name="display_whatsapp_contact" value="1" <?php echo e(old('display_whatsapp_contact', $settings['display_whatsapp_contact'] ?? '1') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="display_whatsapp_contact">تفعيل عرض رابط الواتساب في الصفحة الرئيسية</label>
                            </div>
                            <?php $__errorArgs = ['display_whatsapp_contact'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fab fa-instagram"></i> إعدادات الإنستقرام</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_instagram_url" class="form-label">رابط حساب الإنستقرام</label>
                            <input type="url" class="form-control <?php $__errorArgs = ['contact_instagram_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="contact_instagram_url" name="contact_instagram_url" value="<?php echo e(old('contact_instagram_url', $settings['contact_instagram_url'] ?? '')); ?>" placeholder="https://www.instagram.com/username">
                            <?php $__errorArgs = ['contact_instagram_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="display_instagram_contact" name="display_instagram_contact" value="1" <?php echo e(old('display_instagram_contact', $settings['display_instagram_contact'] ?? '1') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="display_instagram_contact">تفعيل عرض رابط الإنستقرام في الصفحة الرئيسية</label>
                            </div>
                            <?php $__errorArgs = ['display_instagram_contact'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="booking-settings" role="tabpanel" aria-labelledby="booking-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-clock"></i> إعدادات الحجوزات والتوافر</h5>
                    </div>
                    <div class="card-body">
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="booking_availability_months" class="form-label">عدد الأشهر المتاحة للحجز مقدماً</label>
                                <input type="number" class="form-control <?php $__errorArgs = ['booking_availability_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="booking_availability_months" name="booking_availability_months" value="<?php echo e(old('booking_availability_months', $settings['booking_availability_months'] ?? '3')); ?>" min="1" max="24">
                                <?php $__errorArgs = ['booking_availability_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="booking_buffer_time" class="form-label">فترة الراحة بين المواعيد (بالدقائق)</label>
                                <input type="number" class="form-control <?php $__errorArgs = ['booking_buffer_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="booking_buffer_time" name="booking_buffer_time" value="<?php echo e(old('booking_buffer_time', $settings['booking_buffer_time'] ?? '0')); ?>" min="0">
                                <?php $__errorArgs = ['booking_buffer_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="outside_ahsa_fee" class="form-label">رسوم التصوير خارج الأحساء (ريال سعودي)</label>
                                <input type="number" step="0.01" class="form-control <?php $__errorArgs = ['outside_ahsa_fee'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="outside_ahsa_fee" name="outside_ahsa_fee" value="<?php echo e(old('outside_ahsa_fee', $settings['outside_ahsa_fee'] ?? '300.00')); ?>" min="0">
                                <small class="form-text text-muted">القيمة التي ستتم إضافتها على الفاتورة عند اختيار التصوير خارج الأحساء.</small>
                                <?php $__errorArgs = ['outside_ahsa_fee'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="policy_ar" class="form-label">سياسة الحجز (عربي)</label>
                            <textarea class="form-control <?php $__errorArgs = ['policy_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="policy_ar" name="policy_ar" rows="5"><?php echo e(old('policy_ar', $settings['policy_ar'] ?? '')); ?></textarea>
                            <?php $__errorArgs = ['policy_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <label for="policy_en" class="form-label">سياسة الحجز (إنجليزي - اختياري)</label>
                            <textarea class="form-control <?php $__errorArgs = ['policy_en'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="policy_en" name="policy_en" rows="5"><?php echo e(old('policy_en', $settings['policy_en'] ?? '')); ?></textarea>
                            <?php $__errorArgs = ['policy_en'];
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
            </div>

            <div class="tab-pane fade" id="payment-settings" role="tabpanel" aria-labelledby="payment-settings-tab">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-money-check-alt"></i> خيارات الدفع العامة</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                             <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enable_bank_transfer" name="enable_bank_transfer" value="1" <?php echo e(old('enable_bank_transfer', $settings['enable_bank_transfer'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="enable_bank_transfer">تفعيل خيار التحويل البنكي</label>
                                </div>
                                <?php $__errorArgs = ['enable_bank_transfer'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><img src="<?php echo e(asset('images/paylink.png')); ?>" onerror="this.src='https://paylink.sa/wp-content/uploads/2021/08/logo.png'" alt="Paylink" style="height: 20px; margin-left: 8px; vertical-align: middle;">إعدادات بوابة الدفع (Paylink.sa)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="paylink_enabled" name="paylink_enabled" value="1" <?php echo e(old('paylink_enabled', $settings['paylink_enabled'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="paylink_enabled">تفعيل الدفع عبر Paylink (مدى، فيزا، ماستركارد)</label>
                                </div>
                                <?php $__errorArgs = ['paylink_enabled'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="paylink_api_id" class="form-label">معرف الـ API (Paylink API ID)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['paylink_api_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="paylink_api_id" name="paylink_api_id" value="<?php echo e(old('paylink_api_id', $settings['paylink_api_id'] ?? '')); ?>">
                            <?php $__errorArgs = ['paylink_api_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <label for="paylink_secret_key" class="form-label">المفتاح السري (Paylink Secret Key)</label>
                            <input type="password" class="form-control <?php $__errorArgs = ['paylink_secret_key'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="paylink_secret_key" name="paylink_secret_key" value="<?php echo e(old('paylink_secret_key', $settings['paylink_secret_key'] ?? '')); ?>">
                            <?php $__errorArgs = ['paylink_secret_key'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="paylink_test_mode" name="paylink_test_mode" value="1" <?php echo e(old('paylink_test_mode', $settings['paylink_test_mode'] ?? '1') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="paylink_test_mode">وضع الاختبار (Test Mode)</label>
                            </div>
                            <small class="form-text text-muted">أوقف هذا الخيار في بيئة الإنتاج الحقيقية.</small>
                            <?php $__errorArgs = ['paylink_test_mode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>

                
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-piggy-bank me-1"></i> إعدادات نافذة خصم التحويل البنكي</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="enable_bank_transfer_discount_popup" name="enable_bank_transfer_discount_popup" value="1" <?php echo e(old('enable_bank_transfer_discount_popup', $settings['enable_bank_transfer_discount_popup'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="enable_bank_transfer_discount_popup">تفعيل ظهور نافذة خصم التحويل البنكي للعميل</label>
                            </div>
                            <small class="form-text text-muted">إذا تم تفعيل هذا الخيار، ستظهر نافذة منبثقة للعميل عند اختيار التحويل البنكي إذا كانت الرسالة وكود الخصم مُدخلين أدناه.</small>
                            <?php $__errorArgs = ['enable_bank_transfer_discount_popup'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_code" class="form-label">كود الخصم الخاص بالتحويل البنكي</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['bank_transfer_discount_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="bank_transfer_discount_code" name="bank_transfer_discount_code" value="<?php echo e(old('bank_transfer_discount_code', $settings['bank_transfer_discount_code'] ?? '')); ?>" placeholder="مثال: BANKD15">
                            <small class="form-text text-muted">أدخل كود الخصم الذي سيتم تطبيقه تلقائيًا. تأكد أن هذا الكود مُعرَّف وصالح في قسم "أكواد الخصم" وأن شروطه (مثل طريقة الدفع) تتوافق مع "تحويل بنكي".</small>
                            <?php $__errorArgs = ['bank_transfer_discount_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_popup_message_ar" class="form-label">رسالة نافذة خصم التحويل البنكي (عربي)</label>
                            <textarea class="form-control <?php $__errorArgs = ['bank_transfer_discount_popup_message_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="bank_transfer_discount_popup_message_ar" name="bank_transfer_discount_popup_message_ar" rows="3"><?php echo e(old('bank_transfer_discount_popup_message_ar', $settings['bank_transfer_discount_popup_message_ar'] ?? 'لا تفوت الفرصة! استخدم كود الخصم الخاص بالتحويل البنكي.')); ?></textarea>
                            <small class="form-text text-muted">هذه الرسالة ستظهر للعميل في النافذة المنبثقة.</small>
                            <?php $__errorArgs = ['bank_transfer_discount_popup_message_ar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div class="mb-3">
                            <label for="bank_transfer_discount_popup_message_en" class="form-label">رسالة نافذة خصم التحويل البنكي (إنجليزي - اختياري)</label>
                            <textarea class="form-control <?php $__errorArgs = ['bank_transfer_discount_popup_message_en'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="bank_transfer_discount_popup_message_en" name="bank_transfer_discount_popup_message_en" rows="3"><?php echo e(old('bank_transfer_discount_popup_message_en', $settings['bank_transfer_discount_popup_message_en'] ?? '')); ?></textarea>
                            <?php $__errorArgs = ['bank_transfer_discount_popup_message_en'];
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
                

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><img src="<?php echo e(asset('images/tamara.png')); ?>" alt="Tamara" style="height: 20px; margin-left: 8px; vertical-align: middle;">إعدادات بوابة الدفع تمارا (Tamara)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="tamara_enabled" name="tamara_enabled" value="1" <?php echo e(old('tamara_enabled', $settings['tamara_enabled'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="tamara_enabled">تفعيل الدفع عبر تمارا</label>
                                </div>
                                <?php $__errorArgs = ['tamara_enabled'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="tamara_api_url" class="form-label">رابط API الخاص بتمارا (Tamara API URL)</label>
                            <input type="url" class="form-control <?php $__errorArgs = ['tamara_api_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="tamara_api_url" name="tamara_api_url" value="<?php echo e(old('tamara_api_url', $settings['tamara_api_url'] ?? '')); ?>" placeholder="https://api.tamara.co أو https://api-sandbox.tamara.co">
                            <?php $__errorArgs = ['tamara_api_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <small class="form-text text-muted">عادة ما يكون للبيئة التجريبية (sandbox) والبيئة الحية (production).</small>
                        </div>
                        <div class="mb-3">
                            <label for="tamara_api_token" class="form-label">توكن API الخاص بتمارا (Tamara API Token)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['tamara_api_token'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="tamara_api_token" name="tamara_api_token" value="<?php echo e(old('tamara_api_token', $settings['tamara_api_token'] ?? '')); ?>">
                            <?php $__errorArgs = ['tamara_api_token'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="mb-3">
                            <label for="tamara_notification_token" class="form-label">توكن إشعارات الويب هوك (Tamara Notification Token)</label>
                            <input type="text" class="form-control <?php $__errorArgs = ['tamara_notification_token'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="tamara_notification_token" name="tamara_notification_token" value="<?php echo e(old('tamara_notification_token', $settings['tamara_notification_token'] ?? '')); ?>">
                            <?php $__errorArgs = ['tamara_notification_token'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <small class="form-text text-muted">يستخدم للتحقق من صحة طلبات الويب هوك الواردة من تمارا.</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tamara_webhook_verification_bypass" name="tamara_webhook_verification_bypass" value="1" <?php echo e(old('tamara_webhook_verification_bypass', $settings['tamara_webhook_verification_bypass'] ?? '0') == '1' ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="tamara_webhook_verification_bypass">تجاوز التحقق من توقيع الويب هوك لتمارا (لأغراض التطوير)</label>
                            </div>
                            <small class="form-text text-muted">تفعيل هذا الخيار سيتجاوز التحقق من صحة توقيع إشعارات الويب هوك من تمارا. **لا ينصح بتفعيله في البيئة الحية.**</small>
                            <?php $__errorArgs = ['tamara_webhook_verification_bypass'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback d-block"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="data-management" role="tabpanel" aria-labelledby="data-management-tab">
                 <div class="card shadow-sm mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>منطقة الخطر</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-danger">
                            <strong>تحذير شديد:</strong> الإجراءات في هذا القسم خطيرة جداً وقد تؤدي إلى فقدان دائم للبيانات. يرجى توخي الحذر الشديد والمتابعة فقط إذا كنت متأكداً تماماً مما تفعله. **يوصى بشدة بأخذ نسخة احتياطية كاملة من قاعدة البيانات قبل تنفيذ أي من هذه الإجراءات.**
                        </p>
                        <hr>
                        <div>
                            <h6>حذف جميع الحجوزات والفواتير والمدفوعات</h6>
                            <p>سيقوم هذا الإجراء بحذف **جميع** سجلات الحجوزات، و**جميع** الفواتير المرتبطة بها، و**جميع** المدفوعات المسجلة بشكل نهائي من قاعدة البيانات. <strong>لا يمكن التراجع عن هذا الإجراء بأي شكل من الأشكال.</strong></p>
                            <button type="button" class="btn btn-danger" id="deleteAllDataBtn">
                                <i class="fas fa-trash-alt me-1"></i> حذف جميع الحجوزات والفواتير والمدفوعات الآن
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-1"></i> حفظ جميع الإعدادات
            </button>
        </div>
    </form>
    <form id="deleteAllDataForm" action="<?php echo e(route('admin.data.delete_all_bookings')); ?>" method="POST" style="display: none;">
        <?php echo csrf_field(); ?>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    let deletedSliderImagesArray = [];
    const deletedSliderImagesInput = document.getElementById('deleted_slider_images_input');

    function deleteSliderImage(imagePath, elementId) {
        if (confirm('هل أنت متأكد من حذف هذه الصورة من السلايدر؟')) {
            const itemToRemove = document.getElementById(elementId);
            if (itemToRemove) {
                itemToRemove.style.display = 'none';
            }
            if (!deletedSliderImagesArray.includes(imagePath)) {
                deletedSliderImagesArray.push(imagePath);
            }
            if(deletedSliderImagesInput) {
                deletedSliderImagesInput.name = 'deleted_slider_images_json'; // تأكد أن هذا الحقل مُرسل
                deletedSliderImagesInput.value = JSON.stringify(deletedSliderImagesArray);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var hash = window.location.hash;
        if (hash) {
            var triggerElTab = document.querySelector('.nav-tabs button[data-bs-target="' + hash + '"]');
            if (!triggerElTab) { 
                var cardElement = document.getElementById(hash.substring(1));
                if (cardElement) {
                    var tabPane = cardElement.closest('.tab-pane');
                    if (tabPane) {
                        triggerElTab = document.querySelector('.nav-tabs button[data-bs-target="#' + tabPane.id + '"]');
                    }
                }
            }
            if (triggerElTab) {
                var tab = new bootstrap.Tab(triggerElTab);
                tab.show();
                setTimeout(function() {
                    var elementToScroll = document.getElementById(hash.substring(1));
                    if (elementToScroll) {
                        elementToScroll.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        elementToScroll.classList.add('flash-highlight');
                        setTimeout(() => elementToScroll.classList.remove('flash-highlight'), 2000);
                    }
                }, 200);
            }
        }

        const deleteAllDataBtn = document.getElementById('deleteAllDataBtn');
        const deleteAllDataForm = document.getElementById('deleteAllDataForm');

        if (deleteAllDataBtn && deleteAllDataForm) {
            deleteAllDataBtn.addEventListener('click', function(event) {
                event.preventDefault(); 
                const firstConfirm = confirm(
                    "تحذير!\n\n" +
                    "هل أنت متأكد أنك تريد حذف جميع الحجوزات، وجميع الفواتير المرتبطة بها، وجميع المدفوعات المسجلة؟\n\n" +
                    "*** هذه العملية لا يمكن التراجع عنها! ***"
                );
                
                if (firstConfirm) {
                    const secondConfirmText = "تأكيد أخير (للمرة الثانية):\n\n" +
                                            "أنت على وشك حذف كل بيانات الحجوزات والفواتير والمدفوعات بشكل نهائي.\n" +
                                            "لن تتمكن من استرجاع هذه البيانات بعد الحذف.\n\n" +
                                            "اكتب كلمة 'تأكيد الحذف' في المربع أدناه للمتابعة:";
                    const userInput = prompt(secondConfirmText);

                    if (userInput !== null && userInput.trim().toLowerCase() === 'تأكيد الحذف'.toLowerCase()) {
                        const thirdConfirm = confirm(
                            "تأكيد نهائي (للمرة الثالثة والأخيرة):\n\n" +
                            "بضغطك على 'OK'، سيتم حذف جميع البيانات المحددة نهائياً.\n" +
                            "هل أنت متأكد بشكل لا رجعة فيه؟"
                        );

                        if (thirdConfirm) {
                            deleteAllDataBtn.disabled = true;
                            deleteAllDataBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري الحذف...';
                            deleteAllDataForm.submit();
                        } else {
                            alert('تم إلغاء عملية الحذف.');
                        }
                    } else if (userInput !== null) { 
                        alert('النص المدخل غير مطابق لـ "تأكيد الحذف". تم إلغاء العملية.');
                    } else { 
                        alert('تم إلغاء عملية الحذف.');
                    }
                } else {
                    alert('تم إلغاء عملية الحذف.');
                }
            });
        }
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/admin/settings/edit.blade.php ENDPATH**/ ?>