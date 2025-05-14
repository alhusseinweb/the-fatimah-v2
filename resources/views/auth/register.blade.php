<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF Token for forms --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- العنوان الديناميكي للصفحة --}}
    <title>{{ config('app.name', 'Fatimah Booking') }} - تسجيل حساب جديد</title>

    {{-- تضمين الخطوط المخصصة --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">

    {{-- تضمين Bootstrap CSS وأيقونات Font Awesome --}}
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

        /* تنسيقات صفحة التسجيل */
        .register-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: url('{{ asset('images/login.PNG') }}') no-repeat center center;
            background-size: cover;
            position: relative;
        }

        .register-container {
            width: 100%;
            max-width: 550px;
            padding: 2.5rem;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .register-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-logo img {
            max-width: 150px;
            height: auto;
        }

        .register-title {
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
        /* Style for disabled button */
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

        /* Custom validation for inputs */
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: none;
        }
        .form-control.is-valid {
            border-color: #198754; /* Bootstrap success color */
        }


        /* توضيح لحقول الإدخال */
        .form-text {
            font-size: 0.75rem;
            color: #6c757d;
        }

        /* تنسيق الجزء السفلي من النموذج */
        .form-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
        }

        /* تنسيق قسم التجميع */
        .form-group {
            margin-bottom: 1.25rem;
        }

        /* تنسيق شرح إضافي للصفحة */
        .register-instructions {
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #555;
        }
    </style>
</head>
<body>
    <section class="register-section">
        <div class="register-container">
            <div class="register-logo">
                <img src="{{ asset('images/logo.png') }}" alt="Fatimah Ali Photography Logo">
            </div>

            <h1 class="register-title">تسجيل حساب جديد</h1>

            <div class="register-instructions">
                <p>قم بإدخال بياناتك أدناه. سيتم إرسال رمز تحقق إلى رقم جوالك لتأكيد الحساب.</p>
            </div>

            {{-- نموذج تسجيل حساب جديد --}}
            <form method="POST" action="{{ route('register') }}" id="registerForm">
                @csrf

                {{-- حقل الإسم --}}
                <div class="form-group mb-3">
                    <label for="name" class="form-label">الإسم الكامل</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name') }}"
                           placeholder="أدخل الإسم الكامل" required autofocus>
                    @error('name')
                        <span class="error-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- حقل البريد الإلكتروني --}}
                <div class="form-group mb-3">
                    <label for="email" class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email') }}"
                           placeholder="example@domain.com" required>
                    <div class="form-text">سيتم استخدام البريد الإلكتروني للتواصل وإرسال التأكيدات</div>
                    @error('email')
                        <span class="error-feedback">{{ $message }}</span>
                    @enderror
                </div>

                {{-- حقل رقم الجوال --}}
                <div class="form-group mb-4">
                    <label for="mobile_number" class="form-label">رقم الجوال</label>
                    <input type="text" class="form-control @error('mobile_number') is-invalid @enderror"
                           id="mobile_number" name="mobile_number"
                           value="{{ old('mobile_number') ?? request()->get('mobile_number') }}"
                           placeholder="05xxxxxxxx"
                           pattern="^05\d{8}$" required>
                    <div class="form-text">يجب أن يبدأ الرقم بـ 05 ويتكون من 10 أرقام. سيتم إرسال رمز التحقق على هذا الرقم.</div>
                    @error('mobile_number')
                        <span class="error-feedback">{{ $message }}</span>
                    @enderror
                     <span id="phoneErrorClient" class="error-feedback" style="display: none;">رقم جوال غير صالح</span>
                </div>

                {{-- عرض رسائل الخطأ العامة --}}
                @if(session('error'))
                    <div class="alert alert-danger" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="d-flex flex-column gap-2">
                    {{-- زر إرسال النموذج --}}
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        متابعة التسجيل
                    </button>

                    {{-- رابط العودة لصفحة تسجيل الدخول --}}
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary mt-2">
                        لديك حساب بالفعل؟ تسجيل الدخول
                    </a>
                </div>
            </form>

            <div class="form-footer">
                <p>بالتسجيل، أنت توافق على <a href="#">شروط الخدمة</a> و <a href="#">سياسة الخصوصية</a>.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} Fatimah Ali Photography. All Rights Reserved.</p>
        </div>
    </footer>

    {{-- تضمين Bootstrap JavaScript bundle (يشمل Popper) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    {{-- سكريبت للتحقق من رقم الجوال وتعطيل الزر --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('mobile_number');
            const registerForm = document.getElementById('registerForm');
            const submitButton = document.getElementById('submitBtn'); // **الحصول على زر الإرسال**
            const phoneErrorClient = document.getElementById('phoneErrorClient'); // رسالة الخطأ من جانب العميل

            // دالة للتحقق من صحة رقم الجوال
            function validatePhone() {
                 const phonePattern = /^05\d{8}$/;
                 const phoneValue = phoneInput.value.trim();

                 if (!phonePattern.test(phoneValue)) {
                     phoneInput.classList.add('is-invalid');
                     phoneInput.classList.remove('is-valid');
                     if (phoneValue.length > 0) { // اعرض الخطأ فقط إذا كان هناك إدخال
                         phoneErrorClient.style.display = 'block';
                     } else {
                         phoneErrorClient.style.display = 'none'; // إخفاء الخطأ إذا كان الحقل فارغًا
                     }
                     return false; // غير صالح
                 } else {
                     phoneInput.classList.remove('is-invalid');
                     phoneInput.classList.add('is-valid');
                     phoneErrorClient.style.display = 'none';
                     return true; // صالح
                 }
            }

            if(phoneInput && registerForm && submitButton && phoneErrorClient) { // **التأكد من وجود الزر**
                // التحقق من رقم الجوال عند تغيير القيمة
                phoneInput.addEventListener('input', validatePhone);

                // التحقق قبل إرسال النموذج وتعطيل الزر
                registerForm.addEventListener('submit', function(event) {
                    // قم بإجراء التحقق من جانب العميل أولاً
                    if (!validatePhone()) {
                        event.preventDefault(); // منع الإرسال إذا فشل التحقق من جانب العميل
                        alert('يرجى إدخال رقم جوال صالح يبدأ بـ 05 ويتكون من 10 أرقام.');
                    } else {
                        // --- **تعطيل الزر إذا نجح التحقق من جانب العميل** ---
                        submitButton.disabled = true;
                        submitButton.innerHTML = `
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            جاري التسجيل...
                        `;
                        // --- **نهاية التعطيل** ---
                        // السماح للنموذج بالإرسال
                    }
                });
            } else {
                 console.error("Could not find all required elements: #mobile_number, #registerForm, #submitBtn, #phoneErrorClient");
            }
        });
    </script>
</body>
</html>