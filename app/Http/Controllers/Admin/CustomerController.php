<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking; // ستحتاجه لعرض حجوزات العميل
use App\Models\Invoice; // ستحتاجه لعرض فواتير العميل
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // للتحقق من المدخلات
use Illuminate\Validation\Rule; // لقواعد التحقق المتقدمة

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index(Request $request)
    {
        // جلب العملاء فقط (is_admin = false) مع إمكانية البحث والترتيب
        $query = User::where('is_admin', false);

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('mobile_number', 'like', "%{$searchTerm}%");
            });
        }

        // يمكنك إضافة ترتيب هنا، مثلاً بالأحدث تسجيلاً
        $customers = $query->orderBy('created_at', 'desc')->paginate(15); // 15 عميل في كل صفحة

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(User $customer) // استخدام Route Model Binding
    {
        // التأكد من أننا نعدل عميلاً وليس مديراً آخر عن طريق الخطأ
        if ($customer->is_admin) {
            return redirect()->route('admin.customers.index')->with('error', 'لا يمكن تعديل حساب مدير من هذا القسم.');
        }
        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, User $customer)
    {
        if ($customer->is_admin) {
            return redirect()->route('admin.customers.index')->with('error', 'لا يمكن تعديل حساب مدير.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($customer->id), // تجاهل البريد الحالي للمستخدم عند التحقق من التفرد
            ],
            'mobile_number' => [
                'required',
                'string',
                'max:20', // أو أي طول مناسب لأرقام الجوالات
                Rule::unique('users')->ignore($customer->id), // تجاهل الجوال الحالي للمستخدم
                // يمكنك إضافة regex هنا إذا أردت نمط معين للجوال السعودي
                // 'regex:/^(05|5)\d{8}$/'
            ],
            // لا نقم بتعديل كلمة المرور من هنا عادةً، أو يمكن إضافتها كخيار منفصل
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.customers.edit', $customer->id)
                        ->withErrors($validator)
                        ->withInput();
        }

        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->mobile_number = $request->mobile_number;
        // إذا كنت تسمح بتغيير حالة التحقق من الجوال/البريد (بحذر)
        // $customer->mobile_verified_at = $request->has('mobile_verified') ? ($customer->mobile_verified_at ?? now()) : null;
        // $customer->email_verified_at = $request->has('email_verified') ? ($customer->email_verified_at ?? now()) : null;

        $customer->save();

        return redirect()->route('admin.customers.show', $customer->id)->with('success', 'تم تحديث بيانات العميل بنجاح.');
    }

    /**
     * Display the specified customer's details, bookings, and invoices.
     */
    public function show(User $customer, Request $request)
    {
        if ($customer->is_admin) {
            return redirect()->route('admin.customers.index')->with('error', 'هذا الحساب لمدير وليس لعميل.');
        }

        // جلب الحجوزات غير المكتملة (قيد الانتظار، مؤكد، إلخ)
        $ongoingBookings = $customer->bookings()
                                ->whereNotIn('status', [Booking::STATUS_COMPLETED_DELIVERED, Booking::STATUS_CANCELLED_BY_ADMIN, Booking::STATUS_CANCELLED_BY_USER])
                                ->with(['service', 'invoice']) // جلب العلاقات اللازمة
                                ->orderBy('booking_datetime', 'desc')
                                ->get();

        // جلب الفواتير غير المدفوعة بالكامل والمرتبطة بالعميل (من خلال الحجوزات)
        $unpaidInvoices = Invoice::whereHas('booking', function ($query) use ($customer) {
                                    $query->where('user_id', $customer->id);
                                })
                                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PENDING, Invoice::STATUS_PENDING_CONFIRMATION])
                                ->with(['booking.service'])
                                ->orderBy('created_at', 'desc')
                                ->get();

        // حساب إجمالي المبالغ المستحقة وغير المدفوعة
        // نجمع المبالغ المتبقية من الفواتير غير المدفوعة أو المدفوعة جزئياً
        $totalDueAmount = 0;
        foreach ($unpaidInvoices as $invoice) {
            $totalDueAmount += $invoice->remaining_amount; // افترضنا وجود Accessor getRemainingAmountAttribute في موديل Invoice
        }

        // قسم الحجوزات المكتملة (يظهر عند الطلب)
        $completedBookings = null;
        if ($request->has('view_completed')) {
            $completedBookings = $customer->bookings()
                                ->whereIn('status', [Booking::STATUS_COMPLETED_DELIVERED]) // يمكن إضافة حالات أخرى مكتملة
                                ->with(['service', 'invoice'])
                                ->orderBy('booking_datetime', 'desc')
                                ->paginate(10, ['*'], 'completed_page'); // استخدام paginator مختلف إذا لزم الأمر
        }


        return view('admin.customers.show', compact(
            'customer',
            'ongoingBookings',
            'unpaidInvoices',
            'totalDueAmount',
            'completedBookings'
        ));
    }

    // يمكنك إضافة دالة destroy إذا أردت السماح بحذف العملاء (بحذر شديد!)
    // public function destroy(User $customer)
    // {
    // if ($customer->is_admin) { return back()->with('error', 'لا يمكن حذف حساب مدير.'); }
    // // تحقق من عدم وجود حجوزات مرتبطة قبل الحذف أو قم بمعالجتها
    // if ($customer->bookings()->exists()) {
    // return back()->with('error', 'لا يمكن حذف العميل لوجود حجوزات مرتبطة به.');
    // }
    // $customer->delete();
    // return redirect()->route('admin.customers.index')->with('success', 'تم حذف العميل بنجاح.');
    // }
}