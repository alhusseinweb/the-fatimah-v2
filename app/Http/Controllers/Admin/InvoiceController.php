<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Booking;
use App\Models\Payment; // <-- !!! إضافة موديل الدفع !!!
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\BookingConfirmedNotification; // <-- التأكد من استخدام الاسم الصحيح للإشعار
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth; // <-- لإضافة بيانات المدير إذا لزم الأمر

class InvoiceController extends Controller
{
    /**
     * Display a listing of the invoices.
     * (لا تغيير هنا)
     */
    public function index(Request $request): View
    {
        $statuses = Invoice::statuses();
        $query = Invoice::with([
                            'booking:id,booking_datetime,user_id,service_id',
                            'booking.user:id,name',
                        ])->latest();

        if ($request->filled('status') && array_key_exists($request->status, $statuses)) {
            $query->where('status', $request->status);
        }
        $invoices = $query->paginate(15)->withQueryString();
        return view('admin.invoices.index', compact('invoices', 'statuses'));
    }

    /**
     * Display the specified invoice details.
     * (لا تغيير هنا - تحميل الدفعات يتم في الواجهة)
     */
    public function show(Invoice $invoice): View
    {
        // تحميل العلاقات اللازمة يتم الآن في ملف الواجهة show.blade.php
        // $invoice->load(['booking.user', 'booking.service', 'payments']);
        $statuses = Invoice::statuses();
        return view('admin.invoices.show', compact('invoice', 'statuses'));
    }

    /**
     * Update the status of the specified invoice manually.
     * --- !!! تعديلات هنا لاستقبال المبلغ المدفوع وإنشاء سجل دفع !!! ---
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $newStatus = $request->input('status');
        $paidAmountInput = $request->input('paid_amount'); // المبلغ القادم من المودال

        // --- بداية: التحقق من المدخلات ---
        $rules = [
            'status' => ['required', Rule::in(array_keys(Invoice::statuses()))],
            // المبلغ المدفوع مطلوب ورقمي وأكبر من صفر فقط إذا كانت الحالة "مدفوعة جزئياً"
            'paid_amount' => [
                Rule::requiredIf($newStatus === Invoice::STATUS_PARTIALLY_PAID),
                'nullable', // اسمح بأن يكون null للحالات الأخرى
                'numeric',
                'min:0.01' // يجب أن يكون أكبر من صفر
            ],
        ];
        // رسائل خطأ مخصصة
        $messages = [
            'paid_amount.required' => 'عند اختيار حالة "مدفوعة جزئياً"، يجب إدخال المبلغ المدفوع.',
            'paid_amount.numeric' => 'المبلغ المدفوع يجب أن يكون رقماً.',
            'paid_amount.min' => 'المبلغ المدفوع يجب أن يكون أكبر من صفر.',
        ];

        // استخدام error bag مخصص لتوجيه الأخطاء للنموذج الصحيح
        $validator = validator($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->route('admin.invoices.show', $invoice)
                ->withErrors($validator, 'updateStatus') // اسم error bag
                ->withInput();
        }
        $validated = $validator->validated(); // البيانات التي تم التحقق منها
        // --- نهاية: التحقق من المدخلات ---

        $oldStatus = $invoice->status;

        // لا تقم بأي إجراء إذا لم تتغير الحالة (إلا إذا كانت مدفوعة جزئياً ويريد إضافة دفعة أخرى)
        if ($newStatus === $oldStatus && $newStatus !== Invoice::STATUS_PARTIALLY_PAID) {
             return redirect()->route('admin.invoices.show', $invoice)->with('info', 'لم تتغير حالة الفاتورة.');
        }

        try {
            DB::transaction(function () use ($invoice, $newStatus, $oldStatus, $validated) {
                $amountPaidNow = null; // المبلغ الذي سيتم تسجيله في جدول payments

                // --- تحديد المبلغ وإنشاء سجل الدفع إذا لزم الأمر ---
                if ($newStatus === Invoice::STATUS_PARTIALLY_PAID) {
                    $amountPaidNow = (float) $validated['paid_amount'];
                    // تأكد أن المبلغ المدفوع لا يتجاوز المبلغ المتبقي
                    $remaining = $invoice->remaining_amount; // استخدم Accessor
                    if ($amountPaidNow > $remaining) {
                         // إلقاء استثناء لإيقاف العملية وعرض خطأ
                         throw new \InvalidArgumentException("المبلغ المدفوع ({$amountPaidNow}) يتجاوز المبلغ المتبقي ({$remaining}).");
                    }
                } elseif ($newStatus === Invoice::STATUS_PAID) {
                     // إذا تم تحديد الحالة كـ "مدفوع"، نسجل المبلغ المتبقي كدفعة أخيرة
                     $amountPaidNow = $invoice->remaining_amount; // استخدم Accessor
                     // لا نسجل دفعة بقيمة صفر إذا كانت مدفوعة مسبقاً
                      if ($amountPaidNow <= 0 && $oldStatus !== Invoice::STATUS_PAID) {
                          Log::info("Invoice {$invoice->id} marked as PAID manually, but remaining amount was already zero or less.");
                          $amountPaidNow = null; // لا تسجل دفعة بقيمة صفر
                      } elseif($amountPaidNow < 0) { // حالة نادرة، لكن للتحقق
                           Log::error("Calculated negative remaining amount for Invoice {$invoice->id} when marking as PAID.");
                           $amountPaidNow = 0; // أو تعامل مع الخطأ بشكل مختلف
                      }
                }

                // إنشاء سجل Payment إذا كان هناك مبلغ تم دفعه الآن
                if (!is_null($amountPaidNow) && $amountPaidNow > 0) {
                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'transaction_id' => 'MANUAL-' . time(), // معرف فريد بسيط للدفع اليدوي
                        'amount' => $amountPaidNow,
                        'currency' => $invoice->currency,
                        'status' => 'completed', // نفترض أنها مكتملة عند التأكيد اليدوي
                        'payment_gateway' => 'manual_admin',
                        'payment_details' => ['confirmed_by' => Auth::id() ?? null], // تفاصيل إضافية اختيارية
                    ]);
                     Log::info("Manual payment record created for Invoice {$invoice->id}.", ['amount' => $amountPaidNow, 'new_status' => $newStatus]);
                }
                // --- نهاية إنشاء سجل الدفع ---


                // --- تحديث حالة الفاتورة وتاريخ الدفع ---
                $invoice->status = $newStatus;
                // تحديث تاريخ أول دفعة فقط إذا لم يكن محدداً من قبل وكانت الحالة مدفوعة (كامل/جزئي)
                if (is_null($invoice->paid_at) && in_array($newStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID])) {
                    $invoice->paid_at = Carbon::now();
                }
                // إذا أصبحت الفاتورة مدفوعة بالكامل، تأكد من تحديث paid_at
                 elseif ($newStatus === Invoice::STATUS_PAID) {
                     $invoice->paid_at = $invoice->paid_at ?? Carbon::now(); // استخدم التاريخ الحالي إذا لم يسجل من قبل
                 }
                $invoice->save();
                // --- نهاية تحديث الفاتورة ---


                // --- تحديث حالة الحجز المرتبط (إذا لزم الأمر) ---
                if ($invoice->booking) {
                    $booking = $invoice->booking;
                    $needsBookingUpdate = false;
                    $sendNotification = false;

                    // تأكيد الحجز عند أول دفعة (جزئية أو كاملة)
                    if (in_array($newStatus, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIALLY_PAID]) && $booking->status !== Booking::STATUS_CONFIRMED) {
                        $booking->status = Booking::STATUS_CONFIRMED;
                        $needsBookingUpdate = true;
                        $sendNotification = ($oldStatus !== $newStatus); // أرسل فقط إذا تغيرت الحالة بالفعل
                    }
                    // يمكنك إضافة منطق لإلغاء الحجز إذا تم إلغاء الفاتورة
                    elseif ($newStatus === Invoice::STATUS_CANCELLED && !in_array($booking->status, [Booking::STATUS_CANCELLED_BY_ADMIN, Booking::STATUS_CANCELLED_BY_USER])) {
                         $booking->status = Booking::STATUS_CANCELLED_BY_ADMIN; // أو حسب الحاجة
                         $needsBookingUpdate = true;
                         // $sendNotification = true; // قد ترغب في إرسال إشعار إلغاء
                     }

                    if ($needsBookingUpdate) {
                        $booking->save();
                        Log::info("Booking status updated via manual invoice update.", ['booking_id' => $booking->id, 'new_status' => $booking->status]);
                    }

                    // إرسال إشعار تأكيد الحجز (فقط عند التأكيد لأول مرة)
                    if ($sendNotification && $booking->user) {
                        try {
                            Log::info("Attempting to send BookingConfirmed notification for Booking ID: {$booking->id} via manual invoice update.");
                            // --- !!! استخدام اسم الإشعار الصحيح !!! ---
                            $booking->user->notify(new BookingConfirmedNotification($booking));
                            // ----------------------------------------
                            Log::info("BookingConfirmed notification queued/sent successfully for Booking ID: {$booking->id}");
                        } catch (\Exception $e) { Log::error("Failed to send BookingConfirmed notification ... Error: " . $e->getMessage()); }
                    }
                }
                // --- نهاية تحديث الحجز ---

            }); // نهاية Transaction
        } catch (\InvalidArgumentException $e) {
            // التقاط الخطأ الخاص بتجاوز المبلغ المتبقي
             Log::warning("Invalid manual payment amount for Invoice ID: {$invoice->id}. Error: " . $e->getMessage());
             return redirect()->route('admin.invoices.show', $invoice)
                 ->withErrors(['paid_amount' => $e->getMessage()], 'updateStatus') // توجيه الخطأ لحقل المبلغ
                 ->withInput();
         } catch (\Exception $e) {
            Log::error("Failed to update invoice status for Invoice ID: {$invoice->id} - Error: " . $e->getMessage());
            // استخدام رسالة خطأ عامة أو أكثر تحديداً إذا أمكن
            return redirect()->route('admin.invoices.show', $invoice)
                           ->with('error', __('An unexpected error occurred while updating the status.'));
        }

        return redirect()->route('admin.invoices.show', $invoice)
                       ->with('success', __('Invoice status and payment recorded successfully.')); // رسالة نجاح معدلة
    }


    /**
     * Confirm bank transfer payment for the specified invoice.
     * --- !!! تعديل هنا لإنشاء سجل Payment !!! ---
     */
    public function confirmBankTransfer(Invoice $invoice): RedirectResponse
    {
        // ... (التحققات الأولية كما هي) ...
        if ($invoice->payment_method !== 'bank_transfer') { /* ... */ }
        if ($invoice->status !== Invoice::STATUS_UNPAID) { /* ... */ }

        try {
            DB::transaction(function () use ($invoice) {
                $amountPaidNow = $invoice->amount; // المبلغ الكامل للتحويل البنكي

                // --- !!! إضافة إنشاء سجل Payment !!! ---
                 Payment::create([
                     'invoice_id' => $invoice->id,
                     'transaction_id' => 'BANK-' . time(), // معرف بسيط
                     'amount' => $amountPaidNow,
                     'currency' => $invoice->currency,
                     'status' => 'completed',
                     'payment_gateway' => 'bank_transfer',
                     'payment_details' => ['confirmed_by' => Auth::id() ?? null],
                 ]);
                 Log::info("Bank transfer payment record created for Invoice {$invoice->id}.", ['amount' => $amountPaidNow]);
                // --- !!! نهاية الإضافة !!! ---

                // تحديث الفاتورة والحجز المرتبط
                $invoice->status = Invoice::STATUS_PAID;
                $invoice->paid_at = Carbon::now();
                $invoice->save();

                $booking = $invoice->booking;
                if ($booking && $booking->status !== Booking::STATUS_CONFIRMED) {
                    $booking->status = Booking::STATUS_CONFIRMED;
                    $booking->save();

                    // --- إرسال إشعار تأكيد الحجز ---
                    if ($booking->user) {
                        try {
                            Log::info("Attempting to send BookingConfirmed notification for Booking ID: {$booking->id} via confirmBankTransfer");
                             // --- !!! استخدام اسم الإشعار الصحيح !!! ---
                            $booking->user->notify(new BookingConfirmedNotification($booking));
                             // ----------------------------------------
                            Log::info("BookingConfirmed notification queued/sent successfully for Booking ID: {$booking->id}");
                        } catch (\Exception $e) { Log::error("Failed ... Error: " . $e->getMessage()); }
                    }
                } else { Log::warning("Invoice ID: {$invoice->id} marked as paid via bank transfer, but associated booking not found or already confirmed."); }
            });
        } catch (\Exception $e) {
            Log::error("Failed to confirm bank transfer for Invoice ID: {$invoice->id} - Error: " . $e->getMessage());
            return redirect()->route('admin.invoices.show', $invoice)
                           ->with('error', __('An unexpected error occurred while confirming the payment.'));
        }

        return redirect()->route('admin.invoices.show', $invoice)
                       ->with('success', __('Bank transfer payment confirmed successfully. Booking status updated.'));
    }

} // نهاية الكلاس InvoiceController