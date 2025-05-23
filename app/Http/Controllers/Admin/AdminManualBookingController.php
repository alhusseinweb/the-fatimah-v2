<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\User;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AdminManualBookingController extends Controller
{
    /**
     * Display the form to create a manual booking.
     */
    public function create()
    {
        $services = Service::where('is_active', true)->orderBy('name_ar')->get();
        return view('admin.manual-booking.create', compact('services'));
    }

    /**
     * Store a newly created manual booking in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Customer Details
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|regex:/^05[0-9]{8}$/|unique:users,mobile_number',
            'customer_email' => 'required|string|email|max:255|unique:users,email',

            // Booking Details
            'service_id' => 'required|integer|exists:services,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booking_time' => 'required|date_format:H:i',
            'event_location' => 'nullable|string|max:255',
            'groom_name_en' => 'nullable|string|max:255',
            'bride_name_en' => 'nullable|string|max:255',
            'customer_notes' => 'nullable|string|max:1000',
            'booking_amount' => 'required|numeric|min:0', // Total amount for this booking
            'amount_paid' => 'required|numeric|min:0|lte:booking_amount', // Amount paid by customer via admin
        ], [
            'customer_mobile.regex' => 'رقم الجوال يجب أن يكون بصيغة سعودية صحيحة (مثال: 05XXXXXXXX).',
            'customer_mobile.unique' => 'رقم الجوال هذا مسجل لعميل آخر.',
            'customer_email.unique' => 'البريد الإلكتروني هذا مسجل لعميل آخر.',
            'amount_paid.lte' => 'المبلغ المدفوع لا يمكن أن يكون أكبر من مبلغ الحجز الإجمالي.',
        ]);

        try {
            $bookingDateTime = Carbon::createFromFormat('Y-m-d H:i', $validatedData['booking_date'] . ' ' . $validatedData['booking_time']);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'صيغة تاريخ أو وقت الحجز غير صحيحة.');
        }

        $user = null;
        $booking = null;
        $invoice = null;

        DB::beginTransaction();
        try {
            // 1. Create or Find User (without sending OTP or verification emails)
            $user = User::firstOrCreate(
                ['email' => $validatedData['customer_email']],
                [
                    'name' => $validatedData['customer_name'],
                    'mobile_number' => $validatedData['customer_mobile'],
                    'password' => Hash::make(Str::random(10)), // Set a random password
                    'mobile_verified_at' => null, // Mark as unverified initially or as per your logic
                    'is_admin' => false,
                ]
            );

            if ($user->wasRecentlyCreated) {
                Log::info("Admin Manual Booking: New user created.", ['user_id' => $user->id, 'email' => $user->email]);
            } else {
                // Optionally update name/mobile if different, or just use the existing user
                if ($user->name !== $validatedData['customer_name'] || $user->mobile_number !== $validatedData['customer_mobile']) {
                    $user->name = $validatedData['customer_name'];
                    $user->mobile_number = $validatedData['customer_mobile']; // Consider if mobile should be updatable if it's key for OTP
                    $user->save();
                    Log::info("Admin Manual Booking: Existing user found and details updated.", ['user_id' => $user->id]);
                } else {
                     Log::info("Admin Manual Booking: Existing user found.", ['user_id' => $user->id]);
                }
            }

            // 2. Create Booking
            $bookingAmount = (float) $validatedData['booking_amount'];
            $amountPaid = (float) $validatedData['amount_paid'];

            $booking = Booking::create([
                'user_id' => $user->id,
                'service_id' => $validatedData['service_id'],
                'booking_datetime' => $bookingDateTime,
                'status' => ($amountPaid > 0) ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING,
                'event_location' => $validatedData['event_location'],
                'groom_name_en' => $validatedData['groom_name_en'],
                'bride_name_en' => $validatedData['bride_name_en'],
                'customer_notes' => $validatedData['customer_notes'],
                'agreed_to_policy' => true, // Admin is agreeing on behalf
                'down_payment_amount' => ($amountPaid > 0 && $amountPaid < $bookingAmount) ? $amountPaid : null,
                // If amountPaid is full, down_payment_amount can be null or full amount.
                // For simplicity, storing it only if it's a true partial payment.
                // Or consistently store $amountPaid if $amountPaid > 0:
                // 'down_payment_amount' => $amountPaid > 0 ? $amountPaid : null,
                // Let's use $amountPaid if it's a downpayment scenario (paid < total)
            ]);
             // Ensure down_payment_amount is explicitly what was paid if it's a "down payment" context
            if ($amountPaid > 0 && $amountPaid < $bookingAmount) {
                $booking->down_payment_amount = $amountPaid;
            } elseif ($amountPaid == $bookingAmount && $amountPaid > 0) {
                // If full amount paid, technically no 'down_payment_amount' needed,
                // but if your logic relies on it for "initial payment", store it.
                // Or set to null as it's not a downpayment. Let's set it to $amountPaid for consistency if paid.
                 $booking->down_payment_amount = $amountPaid;
            } else {
                 $booking->down_payment_amount = null;
            }
            $booking->save();


            // 3. Create Invoice
            $invoiceStatus = Invoice::STATUS_UNPAID;
            $paymentOption = 'full'; // Default

            if ($amountPaid >= $bookingAmount && $bookingAmount > 0) {
                $invoiceStatus = Invoice::STATUS_PAID;
                $paymentOption = 'full';
            } elseif ($amountPaid > 0 && $amountPaid < $bookingAmount) {
                $invoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
                $paymentOption = 'down_payment';
            }

            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'invoice_number' => 'INV-M-' . $booking->id . '-' . time(), // 'M' for Manual
                'amount' => $bookingAmount,
                'currency' => 'SAR',
                'status' => $invoiceStatus,
                'payment_method' => ($amountPaid > 0) ? 'manual_by_admin' : null, // Payment method for the recorded payment
                'payment_option' => $paymentOption,
                'due_date' => $bookingDateTime->isFuture() ? $bookingDateTime->subDays(1) : Carbon::today(),
                'paid_at' => ($invoiceStatus === Invoice::STATUS_PAID || $invoiceStatus === Invoice::STATUS_PARTIALLY_PAID) ? Carbon::now() : null,
            ]);
            
            $booking->invoice_id = $invoice->id; // Link invoice to booking
            $booking->save();

            // 4. Create Payment Record if amount paid
            if ($amountPaid > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => 'MANUAL-' . Str::uuid(),
                    'amount' => $amountPaid,
                    'currency' => 'SAR',
                    'status' => 'completed',
                    'payment_gateway' => 'manual_by_admin',
                    'payment_details' => json_encode(['admin_id' => auth()->id(), 'notes' => 'Manual booking entry']),
                ]);
            }

            DB::commit();
            Log::info("Admin Manual Booking: Successfully created booking and invoice.", [
                'user_id' => $user->id, 'booking_id' => $booking->id, 'invoice_id' => $invoice->id
            ]);

            return redirect()->route('admin.bookings.show', $booking->id)
                             ->with('success', 'تم إنشاء الحجز والعميل بنجاح. الفاتورة جاهزة.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Admin Manual Booking: Validation failed.', ['errors' => $e->errors()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Manual Booking: Failed to create booking.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withInput()->with('error', 'فشل إنشاء الحجز اليدوي: ' . $e->getMessage());
        }
    }
}
