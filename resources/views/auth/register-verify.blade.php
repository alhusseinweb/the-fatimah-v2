<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF Token for forms --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- العنوان الديناميكي للصفحة --}}
    <title>{{ config('app.name', 'Fatimah Booking') }} - التحقق من رقم الجوال</title>

    {{-- تضمين الخطوط المخصصة --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    {{-- تضمين Bootstrap CSS وأيقونات Font Awesome --}}
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
            background: url('{{ asset('images/login.PNG') }}') no-repeat center center;
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
        .verify-subtitle {
            text-align: center;
            margin-bottom: 2rem;
            color: #555;
            font-size: 1rem;
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
        }
        .form-control.is-invalid {
             border-color: #dc3545;
             background-image: none;
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
        .otp-field {
            letter-spacing: 0.5em; /* زيادة التباعد لـ 4 أرقام */
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            font-family: monospace !important; /* Ensure monospace font */
        }
        .countdown {
            text-align: center;
            margin-top: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #00805a;
            padding: 0;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: underline;
        }
        .resend-btn:disabled {
            color: #6c757d;
            cursor: not-allowed;
            text-decoration: none;
        }
        .resend-container { /* حاوية لزر إعادة الإرسال والعداد */
             text-align: center;
             margin-top: 1rem;
             margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <section class="verify-section">
        <div class="verify-container">
            <div class="verify-logo">
                <img src="{{ asset('images/logo.png') }}" alt="Fatimah Ali Photography Logo">
            </div>

            <h1 class="verify-title">التحقق من رقم الجوال</h1>
            <p class="verify-subtitle">تم إرسال رمز التحقق إلى الرقم <strong dir="ltr">{{ $mobile_number }}</strong></p>

            {{-- نموذج التحقق من OTP --}}
            <form method="POST" action="{{ route('register.verify') }}" id="otpForm">
                @csrf
                 {{-- تمرير رقم الجوال المخفي (مهم لإعادة الإرسال والتحقق) --}}
                 <input type="hidden" name="mobile_number" value="{{ $mobile_number }}">

                {{-- حقل رمز التحقق --}}
                <div class="form-group mb-4">
                    <label for="otp" class="form-label">رمز التحقق</label>
                    {{-- --- تعديل هنا --- --}}
                    <input type="text" class="form-control otp-field @error('otp') is-invalid @enderror @if(session('error') && (!isset($errors) || (isset($errors) && !$errors->has('otp')))) is-invalid @endif"
                           id="otp" name="otp"
                           pattern="[0-9]{4}" {{-- تعديل pattern --}}
                           maxlength="4" {{-- تعديل maxlength --}}
                           placeholder="• • • •" {{-- تعديل placeholder --}}
                           inputmode="numeric" required autofocus>
                     {{-- تعديل النص المساعد --}}
                    <div class="form-text text-center">أدخل رمز التحقق المكون من 4 أرقام</div>
                    {{-- --- نهاية التعديل --- --}}

                    {{-- عرض أخطاء التحقق من الخادم أو خطأ الجلسة --}}
                    @error('otp')
                        <span class="error-feedback">{{ $message }}</span>
                    @enderror
                    {{-- عرض خطأ الجلسة إذا لم يكن هناك خطأ محدد لـ OTP --}}
                    @if(session('error') && (!isset($errors) || (isset($errors) && !$errors->has('otp'))))
                         <span class="error-feedback">{{ session('error') }}</span>
                    @endif
                </div>

                {{-- العداد التنازلي وزر إعادة الإرسال --}}
                <div class="resend-container">
                    <span id="timer">يمكنك طلب رمز جديد خلال <span id="countdown">10:00</span></span>
                    <button type="button" id="resendBtn" class="resend-btn" disabled>
                        إعادة إرسال الرمز
                    </button>
                     <span id="resendLoading" style="display: none;">
                         <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                          جاري الإرسال...
                     </span>
                    <div id="resendStatus" class="mt-1"></div> {{-- لعرض رسائل الحالة --}}
                </div>

                <div class="d-flex flex-column gap-2">
                    {{-- زر إرسال النموذج --}}
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        تأكيد وإكمال التسجيل
                    </button>

                    {{-- رابط العودة للخلف --}}
                    <a href="{{ route('register') }}" class="btn btn-outline-secondary mt-2">
                        العودة للخلف
                    </a>
                </div>
            </form>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} Fatimah Ali Photography. All Rights Reserved.</p>
        </div>
    </footer>

    {{-- تضمين Bootstrap JavaScript bundle (يشمل Popper) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    {{-- سكريبت للعداد التنازلي وإعادة إرسال OTP وتعطيل زر التأكيد والتحقق من الإدخال --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const countdownDisplay = document.getElementById('countdown');
            const resendBtn = document.getElementById('resendBtn');
            const timerText = document.getElementById('timer');
            const resendLoading = document.getElementById('resendLoading');
            const resendStatus = document.getElementById('resendStatus');
            const otpForm = document.getElementById('otpForm');         // **الحصول على النموذج**
            const submitButton = document.getElementById('submitBtn');   // **الحصول على زر الإرسال الرئيسي**
            const otpInput = document.getElementById('otp'); // الحصول على حقل الإدخال

            // تعيين وقت العد التنازلي (مثلاً 2 دقيقة = 120 ثانية) - يمكنك تعديله
            let timeLeft = 120;
            let countdownTimer;

            function startTimer() {
                resendBtn.disabled = true;
                resendLoading.style.display = 'none'; // تأكد من إخفاء التحميل
                timerText.style.display = 'inline'; // إظهار نص العداد
                countdownTimer = setInterval(function() {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    countdownDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    timeLeft--;

                    if (timeLeft < 0) {
                        clearInterval(countdownTimer);
                        timerText.style.display = 'none'; // إخفاء نص العداد عند الانتهاء
                        resendBtn.disabled = false;
                        countdownDisplay.textContent = ''; // مسح عرض الوقت
                    }
                }, 1000);
            }

            // بدء العداد عند تحميل الصفحة
            startTimer();

            // إضافة حدث النقر لزر إعادة الإرسال
            if (resendBtn) {
                resendBtn.addEventListener('click', function() {
                    if (!this.disabled) {
                        this.disabled = true; // تعطيل زر إعادة الإرسال
                        resendLoading.style.display = 'inline-block'; // إظهار التحميل
                        resendStatus.textContent = ''; // مسح الحالة السابقة
                        resendStatus.className = 'mt-1'; // إعادة التعيين للفئة الافتراضية

                        fetch('{{ route("register.resend.otp") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                mobile_number: '{{ $mobile_number }}' // استخدام الرقم من Blade
                            })
                        })
                        .then(response => response.json().then(data => ({ status: response.status, body: data })))
                        .then(({ status, body }) => {
                             resendLoading.style.display = 'none'; // إخفاء التحميل
                            if (status === 200 && body.success) {
                                timeLeft = 120; // إعادة تعيين الوقت (أو القيمة التي حددتها)
                                startTimer(); // بدء العداد من جديد
                                resendStatus.textContent = 'تم إرسال الرمز بنجاح.';
                                resendStatus.className = 'mt-1 text-success';
                            } else {
                                resendStatus.textContent = body.message || 'حدث خطأ. حاول مرة أخرى.';
                                resendStatus.className = 'mt-1 text-danger';
                                this.disabled = false; // إعادة تمكين الزر عند الخطأ
                            }
                             // إخفاء رسالة الحالة بعد فترة
                            setTimeout(() => resendStatus.textContent = '', 5000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                             resendLoading.style.display = 'none'; // إخفاء التحميل
                             resendStatus.textContent = 'خطأ في الاتصال. حاول مرة أخرى.';
                             resendStatus.className = 'mt-1 text-danger';
                            this.disabled = false; // إعادة تمكين الزر عند الخطأ
                            setTimeout(() => resendStatus.textContent = '', 5000);
                        });
                    }
                });
            }

            // تنسيق حقل OTP لتحسين تجربة المستخدم والتحقق
            if (otpInput) {
                 // Only allow numbers
                otpInput.addEventListener('keypress', function(e) {
                    if (isNaN(e.key) || e.key === ' ' || !/^[0-9]$/.test(e.key)) {
                        e.preventDefault();
                    }
                 });

                 // Limit length to 4 digits
                 otpInput.addEventListener('input', function(e) {
                     this.value = this.value.replace(/[^0-9]/g, '');
                      // --- تعديل هنا ---
                     if (this.value.length > 4) { // تغيير 6 إلى 4
                         this.value = this.value.slice(0, 4); // تغيير 6 إلى 4
                     }
                      // --- نهاية التعديل ---
                 });

                 // Clean pasted content
                 otpInput.addEventListener('paste', function(e) {
                     e.preventDefault();
                     const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                      // --- تعديل هنا ---
                     const numbersOnly = pastedText.replace(/[^0-9]/g, '').substring(0, 4); // تغيير 6 إلى 4
                      // --- نهاية التعديل ---
                     this.value = numbersOnly;
                 });

                 otpInput.focus(); // تركيز تلقائي
            }

             // **إضافة مستمع حدث الإرسال لنموذج OTP الرئيسي**
             if (otpForm && submitButton) {
                 otpForm.addEventListener('submit', function(event) {
                      // التحقق من أن الحقل ليس فارغًا ويحتوي على 4 أرقام
                     if (otpInput.value.length !== 4 || isNaN(otpInput.value)) {
                         event.preventDefault(); // منع الإرسال
                         // عرض رسالة خطأ أو تغيير نمط الحقل
                         otpInput.classList.add('is-invalid');
                         const errorSpan = otpForm.querySelector('.error-feedback') || document.createElement('span');
                         if (!otpForm.querySelector('.error-feedback')) {
                            errorSpan.className = 'error-feedback';
                            // وضع رسالة الخطأ بعد النص المساعد
                            const helperText = otpForm.querySelector('.form-text');
                             if(helperText){
                                 helperText.parentNode.insertBefore(errorSpan, helperText.nextSibling);
                             } else {
                                 otpInput.parentNode.appendChild(errorSpan);
                             }
                         }
                          errorSpan.textContent = 'يرجى إدخال 4 أرقام صحيحة.';
                          errorSpan.style.display = 'block';

                         // إعادة تمكين الزر إذا تم منعه
                         submitButton.disabled = false;
                         submitButton.innerHTML = 'تأكيد وإكمال التسجيل';
                         return; // الخروج من الدالة
                     }

                     // إذا كان الإدخال صحيحًا، قم بتعطيل الزر الرئيسي وتغيير حالته
                     submitButton.disabled = true;
                     submitButton.innerHTML = `
                         <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                         جاري التأكيد...
                     `;
                     // السماح للنموذج بالإرسال بشكل طبيعي
                 });
             } else {
                 console.error("Could not find OTP form (#otpForm) or submit button (#submitBtn)");
             }

        });
    </script>
</body>
</html>