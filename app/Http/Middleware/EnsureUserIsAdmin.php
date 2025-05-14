<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log; // لاستخدام السجلات إذا أردت تسجيل محاولات الوصول غير المصرح بها


class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // تحقق أولاً إذا كان المستخدم مسجل الدخول وأن صلاحيته is_admin تساوي true
        if (!Auth::check() || !Auth::user()->is_admin) {
            // إذا لم يكن المستخدم مسجل الدخول أو ليس لديه صلاحية المدير

            // (اختياري) تسجيل محاولة الوصول غير المصرح بها
            if (Auth::check()) {
                Log::warning('Non-admin user attempted to access admin area.', [
                    'user_id' => Auth::id(),
                    'user_email' => Auth::user()->email ?? 'N/A',
                    'requested_url' => $request->fullUrl(),
                ]);
            } else {
                Log::warning('Unauthenticated user attempted to access admin area.', [
                     'requested_url' => $request->fullUrl(),
                ]);
            }


            // إعادة التوجيه إلى لوحة تحكم العميل العادي
            // نستخدم route('customer.dashboard') لإنشاء الرابط بناءً على اسم المسار
             return redirect()->route('customer.dashboard')->with('error', 'ليس لديك صلاحية الوصول لهذه الصفحة.');


            // بدائل أخرى:
            // - إظهار خطأ 403 Forbidden مباشرة:
            // abort(403, 'ليس لديك صلاحية الوصول لهذه الصفحة.');
            // - التوجيه إلى الصفحة الرئيسية:
            // return redirect()->route('home')->with('error', 'ليس لديك صلاحية الوصول لهذه الصفحة.');
        }

        // إذا كان المستخدم مسجل الدخول ولديه صلاحية المدير، اسمح للطلب بالمرور إلى المتحكم
        return $next($request);
    }
}