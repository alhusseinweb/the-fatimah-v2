<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // لاستخدامه لاحقاً إذا أردنا جلب بيانات المستخدم

class DashboardController extends Controller
{
    /**
     * عرض لوحة تحكم العميل.
     * Display the customer dashboard.
     */
    public function index()
    {
        // لاحقاً، يمكنك جلب بيانات خاصة بالعميل هنا، مثل حجوزاته القادمة
        // $user = Auth::user();
        // $upcomingBookings = $user->bookings()->where('booking_datetime', '>=', now())->orderBy('booking_datetime')->get();

        // حالياً، نعرض الواجهة فقط
        return view('customer.dashboard'); // اسم الواجهة: resources/views/customer/dashboard.blade.php
        // يمكنك تمرير البيانات للواجهة باستخدام: ->with('user', $user)->with('bookings', $upcomingBookings);
    }
}