<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Invoice;
use App\Models\Booking; // <-- Added use statement for Booking model
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CustomerDashboardController extends Controller
{
    /**
     * Display the customer's main dashboard.
     *
     * MODIFIED: Fetches upcoming bookings, unpaid invoices, and booking history.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View // Changed method name from dashboard to index to match route definition
    {
        $user = Auth::user();

        // --- Fetch Data ---

        // 1. Get Upcoming Appointments (Confirmed & Future Date)
        $upcomingBookings = Booking::where('user_id', $user->id)
            ->where('status', 'confirmed') // Only confirmed bookings
            ->where('booking_datetime', '>=', now()) // Only future or present bookings
            ->with('service') // Eager load service details
            ->orderBy('booking_datetime', 'asc') // Order by soonest first
            ->get();

        // 2. Get Unpaid Invoices
        // Assumes an Invoice belongs to a Booking, and a Booking belongs to a User.
        // Adjust the relationship query if your structure is different.
        $unpaidInvoices = Invoice::whereHas('booking', function ($query) use ($user) {
                $query->where('user_id', $user->id); // Filter invoices by the user's bookings
            })
            ->where('status', '!=', 'paid') // Get invoices that are not paid
            ->with('booking.service') // Eager load booking and service details
            ->orderBy('created_at', 'desc') // Order by newest invoice first
            ->get();

        // 3. Get Booking History (All bookings for the user, or filter as needed)
        $bookingHistory = Booking::where('user_id', $user->id)
            // Optionally filter history (e.g., only completed/cancelled)
            // ->whereIn('status', ['completed', 'cancelled'])
            ->with(['service', 'invoice']) // Eager load service and invoice details
            ->orderBy('booking_datetime', 'desc') // Order by most recent booking date first
            ->get(); // Use paginate(10) if you expect many history items

        // --- Pass Data to View ---
        return view('frontend.customer.dashboard', compact(
            'user', // Keep existing user data
            'upcomingBookings',
            'unpaidInvoices',
            'bookingHistory'
        ));
    }

    /**
     * Display the customer's booking list.
     * (Kept original method as it might be used by another route)
     *
     * @return \Illuminate\View\View
     */
    public function listBookings(): View
    {
        $user = Auth::user();
        $bookings = $user->bookings() // Assumes 'bookings' relationship exists on User model
                         ->with(['service', 'invoice'])
                         ->latest('booking_datetime') // Order by latest booking date
                         ->paginate(10); // Example pagination

        // Ensure the view path is correct
        return view('frontend.customer.bookings.index', [
            'bookings' => $bookings
        ]);
    }

    /**
     * Display the specified invoice.
     * (Kept original method as it might be used by another route)
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function showInvoice(Invoice $invoice): View|Response
    {
        Log::info("Attempting to show Invoice ID: {$invoice->id} for User ID: " . Auth::id());

        // --- Authorization Check ---
        // Ensure the invoice's booking belongs to the currently authenticated user
        if ($invoice->booking?->user_id !== Auth::id()) {
            Log::warning("Unauthorized attempt to view Invoice ID: {$invoice->id} by User ID: " . Auth::id());
            abort(404); // Use 404 (Not Found) which is common practice here
        }

        // --- Eager Loading ---
        // Load related data efficiently
        $invoice->load(['booking.service', 'booking.user']);

        // --- Display View ---
        try {
             // Ensure the view path is correct
            return view('frontend.customer.invoices.show', compact('invoice'));
        } catch (\Exception $e) {
            Log::error("Error rendering invoice view for Invoice ID {$invoice->id}: " . $e->getMessage());
            abort(500, "Error displaying invoice view.");
        }
    }

} // Ensure this closing brace exists