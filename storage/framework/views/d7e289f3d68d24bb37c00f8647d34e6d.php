<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(app()->getLocale() == 'ar' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <title><?php echo e(config('app.name', 'Fatimah Booking')); ?> - تسجيل الدخول</title>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* تنسيقات أساسية لـ body و html لإزالة الهوامش وضمان عرض كامل */
        html, body {
            margin: 0; padding: 0; width: 100%; height: 100%; overflow-x: hidden;
            font-family: 'Almarai', sans-serif;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
        }

        /* تنسيقات صفحة تسجيل الدخول */
        .login-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: url('<?php echo e(asset('images/login.PNG')); ?>') no-repeat center center;
            background-size: cover;
            position: relative;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo img {
            max-width: 150px;
            height: auto;
        }

        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-weight: 700;
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
        /* Add style for disabled button */
        .btn-primary:disabled {
            background-color: #6c757d; /* Bootstrap secondary color */
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
        }

        /* تنسيقات التذييل */
        .footer {
            padding: 1.5rem 0;
            background-color: #f8f9fa;
            color: #555;
            text-align: center;
        }

        /* تنسيقات عامة لـ RTL */
        body {
            direction: rtl;
            text-align: right;
        }

        /* Fix to ensure font is applied correctly */
        *, *::before, *::after {
            font-family: 'Almarai', sans-serif !important;
        }

        /* Custom validation for phone number */
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: none;
        }
    </style>
</head>
<body>
    <section class="login-section">
        <div class="login-container">
            <div class="login-logo">
                <img src="<?php echo e(asset('images/logo.png')); ?>" alt="Fatimah Ali Photography Logo">
            </div>

            <h1 class="login-title">تسجيل الدخول</h1>

            
            <form method="POST" action="<?php echo e(route('login.otp.request')); ?>" id="otpLoginForm">
                <?php echo csrf_field(); ?>
                <div class="mb-4">
                    <label for="mobile_number" class="form-label">رقم الجوال</label>
                    <input type="text" class="form-control <?php $__errorArgs = ['mobile_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                           id="mobile_number" name="mobile_number"
                           placeholder="05xxxxxxxx"
                           pattern="^05\d{8}$" 
                           required autofocus>
                    <div id="phoneHelp" class="form-text">يجب أن يبدأ الرقم بـ 05 ويتكون من 10 أرقام</div>
                    
                    <?php $__errorArgs = ['mobile_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <span class="error-feedback"><?php echo e($message); ?></span>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    
                    <span id="phoneError" class="error-feedback" style="display: none;">يجب أن يبدأ رقم الجوال بـ 05 ويتكون من 10 أرقام</span>
                </div>

                
                <?php if(session('error')): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo e(session('error')); ?>

                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-2">
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        تسجيل الدخول
                    </button>

                    
                    <a href="<?php echo e(url('/')); ?>" class="btn btn-outline-secondary mt-2">
                        العودة للصفحة الرئيسية
                    </a>
                </div>
            </form>

            <div class="mt-4 text-center">
                <p>سيتم إرسال رمز تحقق إلى رقم جوالك لتسجيل الدخول</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo e(date('Y')); ?> Fatimah Ali Photography. All Rights Reserved.</p>
        </div>
    </footer>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    
    
    

    <div class="modal fade" id="noAccountModal" tabindex="-1" aria-labelledby="noAccountModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="noAccountModalLabel">لا يوجد حساب</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <p>لا يوجد حساب مسجل برقم الجوال هذا.</p>
            <p>هل تود إنشاء حساب جديد؟</p>
            
            <input type="hidden" id="modalMobileNumber">
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لا</button>
            
            <button type="button" class="btn btn-primary" id="confirmCreateAccount">نعم، إنشاء حساب</button>
          </div>
        </div>
      </div>
    </div>

    
    
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // الحصول على العناصر الضرورية
        const loginForm = document.getElementById('otpLoginForm'); // نموذج تسجيل الدخول
        const mobileNumberInput = document.getElementById('mobile_number'); // حقل رقم الجوال
        const submitButton = document.getElementById('submitBtn'); // زر الإرسال
        const noAccountModalElement = document.getElementById('noAccountModal'); // عنصر المودال HTML
        const noAccountModal = new bootstrap.Modal(noAccountModalElement); // كائن المودال لـ Bootstrap
        const modalMobileNumberInput = document.getElementById('modalMobileNumber'); // حقل رقم الجوال المخفي داخل المودال
        const confirmCreateAccountButton = document.getElementById('confirmCreateAccount'); // زر "نعم" داخل المودال

        // التأكد من وجود جميع العناصر قبل إضافة المستمعين للأحداث
        if (loginForm && mobileNumberInput && submitButton && noAccountModalElement && noAccountModal && modalMobileNumberInput && confirmCreateAccountButton) {

            // إضافة مستمع لحدث إرسال النموذج
            loginForm.addEventListener('submit', function (event) {
                event.preventDefault(); // منع الإرسال الافتراضي للنموذج

                // --- تعطيل الزر وتغيير النص/إضافة مؤشر تحميل ---
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    جاري الإرسال...
                `;
                // --- نهاية التعطيل ---

                // إزالة أي رسائل خطأ سابقة من الخادم (إن وجدت)
                const serverErrorSpan = loginForm.querySelector('.error-feedback');
                if(serverErrorSpan && !serverErrorSpan.id) { // تأكد أنه خطأ الخادم وليس خطأ الهاتف
                     serverErrorSpan.style.display = 'none';
                     serverErrorSpan.textContent = '';
                }
                 mobileNumberInput.classList.remove('is-invalid');


                const mobileNumber = mobileNumberInput.value; // الحصول على رقم الجوال المدخل
                const formAction = loginForm.getAttribute('action'); // الحصول على مسار إرسال النموذج
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content'); // الحصول على CSRF token

                // إرسال طلب AJAX (Fetch API)
                fetch(formAction, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json' // ضمان قبول JSON
                    },
                    body: JSON.stringify({ mobile_number: mobileNumber })
                })
                .then(response => {
                    // إذا كانت الاستجابة تشير إلى عدم وجود حساب (404 مع رسالة محددة)
                    if (response.status === 404) {
                        return response.json().then(data => {
                            if (data.message === 'no_account_found') {
                                modalMobileNumberInput.value = data.mobile_number;
                                noAccountModal.show();
                                // لا نعيد تمكين الزر هنا لأن المودال سيظهر
                                return Promise.reject('No account found, modal shown');
                            } else {
                                console.error('Unexpected 404 Response:', data);
                                alert('حدث خطأ غير متوقع: ' + (data.message || 'خطأ في الخادم'));
                                // --- إعادة تمكين الزر عند الخطأ 404 غير المتوقع ---
                                submitButton.disabled = false;
                                submitButton.innerHTML = 'تسجيل الدخول';
                                // --- نهاية إعادة التمكين ---
                                return Promise.reject('Unexpected 404');
                            }
                        });
                     }

                    // إذا كانت الاستجابة 422 (فشل التحقق من جانب الخادم)
                     if (response.status === 422) {
                         return response.json().then(data => {
                             console.error('Validation Errors:', data.errors);
                             // عرض أخطاء التحقق
                              if(data.errors && data.errors.mobile_number){
                                const serverErrorSpan = loginForm.querySelector('.error-feedback'); // أو استخدم ID محدد إذا كان لديك
                                if(serverErrorSpan){
                                      serverErrorSpan.textContent = data.errors.mobile_number[0]; // عرض أول رسالة خطأ
                                      serverErrorSpan.style.display = 'block';
                                      mobileNumberInput.classList.add('is-invalid');
                                }
                              } else {
                                alert('فشل التحقق من البيانات المدخلة.'); // رسالة عامة
                              }

                             // --- إعادة تمكين الزر عند فشل التحقق ---
                             submitButton.disabled = false;
                             submitButton.innerHTML = 'تسجيل الدخول';
                             // --- نهاية إعادة التمكين ---
                             return Promise.reject('Validation failed');
                         });
                     }

                    // إذا كانت الاستجابة ناجحة (2xx) أو إعادة توجيه (302)
                    if(response.ok || response.redirected) {
                        // إذا كانت 200، أعد التوجيه يدوياً كما في الكود الأصلي
                        if (response.status === 200) {
                            return response.json().then(data => {
                                console.log('OTP request successful, redirecting...');
                                // **لا نعيد تمكين الزر هنا لأنه يفترض إعادة التوجيه**
                                window.location.href = "<?php echo e(route('login.otp.verify.form')); ?>?mobile_number=" + encodeURIComponent(mobileNumber);
                                return Promise.reject('Redirecting manually'); // لمنع الوصول لـ catch
                            });
                        } else if (response.redirected) {
                             console.log('Redirecting automatically...');
                             // **لا نعيد تمكين الزر هنا، المتصفح سيقوم بالعمل**
                             return Promise.reject('Redirecting automatically'); // لمنع الوصول لـ catch
                        } else {
                             // حالة نجاح أخرى غير متوقعة (مثل 201 Created)
                             console.log('Request successful with status:', response.status);
                             // **لا نعيد تمكين الزر هنا، نفترض نجاح العملية**
                              return Promise.reject('Successful non-redirect'); // لمنع الوصول لـ catch
                        }
                    } else {
                        // التعامل مع أخطاء HTTP الأخرى (مثل 500)
                        return response.text().then(text => {
                             console.error('HTTP Error:', response.status, text);
                             alert('حدث خطأ غير متوقع من الخادم: رمز الحالة ' + response.status);
                              // --- إعادة تمكين الزر عند خطأ HTTP عام ---
                             submitButton.disabled = false;
                             submitButton.innerHTML = 'تسجيل الدخول';
                             // --- نهاية إعادة التمكين ---
                             return Promise.reject('HTTP Error: ' + response.status);
                         });
                    }
                })
                .catch(error => {
                    console.error('Fetch Error or Handled Case:', error);
                    // التأكد من عدم إعادة تمكين الزر إذا كانت الحالة قد تمت معالجتها
                    const handledErrors = [
                        'No account found, modal shown',
                        'Validation failed',
                        'Unexpected 404',
                        'Redirecting manually',
                        'Redirecting automatically',
                        'Successful non-redirect'
                        // لا نضع 'HTTP Error: ...' هنا لأنها تُعالج بإعادة التمكين أعلاه
                    ];
                    if (!handledErrors.includes(error) && !(error instanceof DOMException)) { // DOMException لأخطاء الشبكة
                        // --- إعادة تمكين الزر عند خطأ fetch عام غير معالج ---
                        alert('حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقاً.');
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'تسجيل الدخول';
                         // --- نهاية إعادة التمكين ---
                    } else if (error instanceof DOMException) {
                        alert('خطأ في الشبكة. يرجى التحقق من اتصالك بالإنترنت.');
                        submitButton.disabled = false;
                        submitButton.innerHTML = 'تسجيل الدخول';
                    }
                    // لا نحتاج لإضافة مستمع hidden.bs.modal هنا لأنه موجود بالأسفل بالفعل
                });
            });

            // إضافة مستمع لحدث النقر على زر "نعم، إنشاء حساب" في مربع الحوار
            confirmCreateAccountButton.addEventListener('click', function () {
                 const mobileNumber = modalMobileNumberInput.value;
                 // لا نعيد تمكين الزر هنا لأننا ننتقل لصفحة أخرى
                 window.location.href = "<?php echo e(route('register')); ?>?mobile_number=" + encodeURIComponent(mobileNumber);
             });

            // عند إغلاق المودال (بدون الضغط على "نعم")، أعد تمكين الزر الرئيسي
             noAccountModalElement.addEventListener('hidden.bs.modal', function () {
                 // التأكد من إعادة تمكين الزر إذا أغلق المستخدم المودال
                submitButton.disabled = false;
                submitButton.innerHTML = 'تسجيل الدخول';
                modalMobileNumberInput.value = ''; // مسح القيمة
             });


        } else {
            console.error('عناصر HTML المطلوبة لمنطق مربع حوار تسجيل الدخول غير موجودة. تأكد من وجود الـ IDs الصحيحة (#otpLoginForm, #mobile_number, #submitBtn, #noAccountModal, #modalMobileNumber, #confirmCreateAccount).');
        }
    });
    </script>

</body>
</html><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/auth/otp-login.blade.php ENDPATH**/ ?>