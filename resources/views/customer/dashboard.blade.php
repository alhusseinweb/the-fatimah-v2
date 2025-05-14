<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم</title>
    {{-- يمكنك إضافة ملفات CSS هنا لاحقاً --}}
</head>
<body>

    <h1>أهلاً بك في لوحة التحكم</h1>

    {{-- التحقق من وجود المستخدم المسجل (احتياطي) --}}
    @auth
        <p>مرحباً، {{ Auth::user()->name }}!</p>
        <p>رقم جوالك: {{ Auth::user()->mobile_number }}</p>

        {{-- هنا يمكنك إضافة محتوى لوحة التحكم لاحقاً --}}
        <h2>حجوزاتك القادمة (مثال):</h2>
        {{-- @if($upcomingBookings->count() > 0)
            <ul>
                @foreach($upcomingBookings as $booking)
                    <li>{{ $booking->service->name_ar ?? 'خدمة محذوفة' }} - {{ $booking->booking_datetime->format('Y-m-d H:i') }}</li>
                @endforeach
            </ul>
        @else
            <p>لا توجد حجوزات قادمة.</p>
        @endif --}}
        <hr>

        {{-- زر/نموذج تسجيل الخروج --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">تسجيل الخروج</button>
        </form>
    @else
        <p>يرجى تسجيل الدخول للوصول لهذه الصفحة.</p>
        <a href="{{ route('login.otp.form') }}">تسجيل الدخول</a>
    @endauth

    {{-- يمكنك إضافة ملفات JavaScript هنا لاحقاً --}}
</body>
</html>