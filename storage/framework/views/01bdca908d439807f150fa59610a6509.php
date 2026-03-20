<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(app()->getLocale() == 'ar' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <title><?php echo e(config('app.name', 'Fatimah Booking')); ?> - التحقق من رقم الجوال</title>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* --- ( نفس تنسيقات CSS السابقة بدون تغيير ) --- */
        html, body {
            margin: 0; padding: 0; width: 100%; height: 100%; overflow-x: hidden;
            font-family: 'Almarai', sans-serif;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        .verify-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: url('<?php echo e(asset('images/login.PNG')); ?>') no-repeat center center;
            background-size: cover;
            position: relative;
        }
        .verify-container {
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .verify-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .verify-logo img {
            max-width: 150px;
            height: auto;
        }
        .verify-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
            font-weight: 700;
        }
        .phone-number-display {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            background-color: rgba(0, 128, 90, 0.1);
            border-radius: 5px;
            color: #00805a;
            font-weight: 500;
            direction: ltr; /* Ensure phone number displays correctly */
        }
        .form-label {
            font-weight: 400;
            color: #555;
            text-align: right;
            width: 100%;
            display: block;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 5px;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            text-align: center; /* لتوسيط الأرقام */
            letter-spacing: 8px; /* زيادة المسافة بين الأرقام */
            font-size: 1.5rem; /* تكبير الخط قليلاً */
        }
        .form-control:focus {
            border-color: #00805a;
            box-shadow: 0 0 0 0.25rem rgba(0, 128, 90, 0.25);
        }
        .btn-primary {
            background-color: #00805a;
            border-color: #00805a;
            padding: 0.75rem 1.5rem;
            font-weight: 400;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #006a4a;
            border-color: #006a4a;
        }
        .btn-primary:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
            opacity: 0.65;
            cursor: not-allowed;
        }
        .btn-outline-secondary {
            color: #555;
            border-color: #555;
            padding: 0.75rem 1.5rem;
            font-weight: 400;
            transition: all 0.3s;
        }
        .btn-outline-secondary:hover {
            background-color: #555;
            border-color: #555;
            color: #fff;
        }
        .error-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
            text-align: center; /* توسيط رسالة الخطأ */
        }
        .footer {
            padding: 1.5rem 0;
            background-color: #f8f9fa;
            color: #555;
            text-align: center;
        }
        body {
            direction: rtl;
            text-align: right;
        }
        *, *::before, *::after {
            font-family: 'Almarai', sans-serif !important;
        }
        .form-control.otp-input {
            font-family: monospace !important; /* للحفاظ على تباعد الأحرف ثابتًا */
        }
        .form-control.is-invalid {
             border-color: #dc3545;
             background-image: none; /* إزالة أيقونة التحقق الافتراضية */
         }
    </style>
</head>
<body>
    <section class="verify-section">
        <div class="verify-container">
            <div class="verify-logo">
                <img src="<?php echo e(asset('images/logo.png')); ?>" alt="Fatimah Ali Photography Logo">
            </div>

            <h1 class="verify-title">التحقق من رقم الجوال</h1>

            <div class="phone-number-display">
                
                تم إرسال الرمز إلى الرقم: <span dir="ltr"><?php echo e(session('mobile_for_verification') ?? old('mobile_number_hidden', $mobileNumber ?? '')); ?></span>
            </div>

            
            <form method="POST" action="<?php echo e(route('login.otp.verify')); ?>" id="otpVerifyForm">
                <?php echo csrf_field(); ?>
                 
                <input type="hidden" name="mobile_number_hidden" value="<?php echo e(session('mobile_for_verification') ?? old('mobile_number_hidden', $mobileNumber ?? '')); ?>">

                <div class="mb-4">
                    <label for="otp_code" class="form-label">رمز التحقق (OTP)</label>
                    
                    <input type="text" class="form-control otp-input <?php $__errorArgs = ['otp_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php if(session('error')): ?> is-invalid <?php endif; ?>"
                           id="otp_code" name="otp_code"
                           placeholder="• • • •" 
                           maxlength="4" 
                           autocomplete="one-time-code"
                           inputmode="numeric"
                           required autofocus>
                    
                    <div id="otpHelp" class="form-text text-center">الرجاء إدخال الرمز المكون من 4 أرقام</div>
                    
                    
                    <?php $__errorArgs = ['otp_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <span class="error-feedback"><?php echo e($message); ?></span>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    
                    <?php if(session('error')): ?>
                        <span class="error-feedback"><?php echo e(session('error')); ?></span>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        تحقق وتسجيل الدخول
                    </button>

                    <a href="<?php echo e(route('login')); ?>" class="btn btn-outline-secondary mt-2">
                        العودة لصفحة تسجيل الدخول
                    </a>
                </div>
            </form>

            <?php if(session('success')): ?>
                <div class="alert alert-success alert-dismissible fade show text-center py-2 mb-3" role="alert" style="font-size: 0.9rem;">
                    <?php echo e(session('success')); ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem;"></button>
                </div>
            <?php endif; ?>

            <div class="mt-4 text-center">
                <p class="mb-2">يرجى الانتظار حتى استلام الرمز، قد يستغرق ذلك بضع دقائق</p>
                <div id="smsFallbackContainer">
                    <span>لم يصلك الرمز؟</span>
                    <button type="button" id="resendSmsBtn" class="btn btn-link p-0 ms-1" style="color: #00805a; text-decoration: none; font-weight: 700;">
                        أرسل عبر SMS (Twilio)
                    </button>
                </div>
                <div id="resendStatus" class="mt-2 small" style="display: none;"></div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo e(date('Y')); ?> Fatimah Ali Photography. All Rights Reserved.</p>
        </div>
    </footer>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Get the OTP input field
        const otpInput = document.getElementById('otp_code');
        // Get the form and submit button
        const verifyForm = document.getElementById('otpVerifyForm');
        const submitButton = document.getElementById('submitBtn');

        if(otpInput) {
             // Only allow numbers in the OTP field
            otpInput.addEventListener('keypress', function(e) {
                // منع إدخال أي شيء غير الأرقام
                if (isNaN(e.key) || e.key === ' ' || !/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                }
            });

             // تحديد الطول الأقصى بـ 4 أرقام
             otpInput.addEventListener('input', function(e) {
                 this.value = this.value.replace(/[^0-9]/g, ''); // إزالة غير الأرقام
                 // --- تعديل هنا ---
                 if (this.value.length > 4) { // تغيير 6 إلى 4
                     this.value = this.value.slice(0, 4); // تغيير 6 إلى 4
                 }
                 // --- نهاية التعديل ---
             });


            // Clean pasted content - numbers only and max length
            otpInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                 // --- تعديل هنا ---
                const numbersOnly = pastedText.replace(/[^0-9]/g, '').substring(0, 4); // تغيير 6 إلى 4
                 // --- نهاية التعديل ---
                this.value = numbersOnly;
            });

            // التركيز على الحقل عند تحميل الصفحة
            otpInput.focus();
        }

        // Add event listener for form submission
        if (verifyForm && submitButton) {
            verifyForm.addEventListener('submit', function(event) {
                // التحقق من أن الحقل ليس فارغًا ويحتوي على 4 أرقام
                if (otpInput.value.length !== 4 || isNaN(otpInput.value)) {
                     event.preventDefault(); // منع الإرسال
                     // عرض رسالة خطأ أو تغيير نمط الحقل
                     otpInput.classList.add('is-invalid');
                     const errorSpan = verifyForm.querySelector('.error-feedback') || document.createElement('span');
                     if (!verifyForm.querySelector('.error-feedback')) {
                        errorSpan.className = 'error-feedback';
                        otpInput.parentNode.appendChild(errorSpan);
                     }
                      errorSpan.textContent = 'يرجى إدخال 4 أرقام صحيحة.';
                      errorSpan.style.display = 'block';

                     // إعادة تمكين الزر إذا تم منعه
                     submitButton.disabled = false;
                     submitButton.innerHTML = 'تحقق وتسجيل الدخول';
                     return; // الخروج من الدالة
                }

                // إذا كان الإدخال صحيحًا، قم بتعطيل الزر وإظهار حالة التحميل
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    جاري التحقق...
                `;
                // السماح للنموذج بالإرسال بشكل طبيعي
            });
        } else {
            console.error("Could not find form (#otpVerifyForm) or button (#submitBtn)");
        }

        // Resend SMS logic
        const resendSmsBtn = document.getElementById('resendSmsBtn');
        const resendStatus = document.getElementById('resendStatus');

        if (resendSmsBtn) {
            resendSmsBtn.addEventListener('click', function() {
                resendSmsBtn.disabled = true;
                resendStatus.style.display = 'block';
                resendStatus.className = 'mt-2 small text-muted';
                resendStatus.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري إرسال الرسالة النصية...';

                fetch("<?php echo e(route('login.otp.resend.sms')); ?>", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        mobile_number_hidden: "<?php echo e(session('mobile_for_verification') ?? old('mobile_number_hidden', $mobileNumber ?? '')); ?>"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resendStatus.className = 'mt-2 small text-success';
                        resendStatus.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
                    } else {
                        resendStatus.className = 'mt-2 small text-danger';
                        resendStatus.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + data.message;
                        resendSmsBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resendStatus.className = 'mt-2 small text-danger';
                    resendStatus.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> حدث خطأ غير متوقع.';
                    resendSmsBtn.disabled = false;
                });
            });
        }

    });
    </script>
</body>
</html><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/auth/otp-verify.blade.php ENDPATH**/ ?>