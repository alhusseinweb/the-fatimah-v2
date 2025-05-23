<?php

// Use statements
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestSmsController; // افترض أنه موجود أو قم بإزالته إذا لم يكن كذلك
use App\Models\Setting;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Controllers\PaymentController;
use App\Notifications\BookingConfirmedNotification;
use App\Models\User;
use App\Models\Booking;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Frontend\CustomerDashboardController;
use App\Http\Controllers\Frontend\ServiceController as FrontendServiceController;
use App\Http\Controllers\Frontend\BookingController as FrontendBookingController;
use App\Http\Controllers\Admin\AdminAvailabilityController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ServiceCategoryController as AdminServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\DiscountCodeController;
use App\Http\Controllers\Admin\SettingController; // المتحكم الخاص بالإعدادات العامة
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Controllers\Admin\SmsTemplateController;
use App\Http\Controllers\Admin\GoogleCalendarController;
// --- MODIFICATION START: Import new AdminManualBookingController ---
use App\Http\Controllers\Admin\AdminManualBookingController;
// --- MODIFICATION END ---

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/test-android-sms', [TestSmsController::class, 'sendTestSms']);

// Homepage Route
Route::get('/', function () {
    $settings = Setting::whereIn('key', ['homepage_logo_path', 'homepage_slider_images'])
                        ->pluck('value', 'key')
                        ->toArray();
    $settings['homepage_slider_images'] = isset($settings['homepage_slider_images'])
                                            ? (json_decode($settings['homepage_slider_images'], true) ?? [])
                                            : [];
    $services = Service::where('is_active', true)->orderBy('name_ar')->take(6)->get();
    return view('frontend.homepage', compact('settings', 'services'));
})->name('home');

// Frontend Service Routes
Route::get('/services', [FrontendServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [FrontendServiceController::class, 'show'])->name('services.show');
Route::get('/services/category/{category}', [FrontendServiceController::class, 'category'])
    ->name('services.category')
    ->where('category', '[0-9]+');

// --- Booking Process (Requires Auth) ---
Route::middleware('auth')->group(function () {
    Route::get('/booking/{service}/calendar', [FrontendBookingController::class, 'showCalendar'])->where('service', '[0-9]+')->name('booking.calendar');
    Route::get('/booking/confirm', [FrontendBookingController::class, 'showBookingForm'])->name('booking.showForm');
    Route::post('/booking/submit', [FrontendBookingController::class, 'submitBooking'])->name('booking.submit');
    Route::get('/booking/{booking}/pending', [FrontendBookingController::class, 'showPendingPage'])->where('booking', '[0-9]+')->name('booking.pending');

    Route::get('/tamara/success/{invoice}', [PaymentController::class, 'handleTamaraSuccess'])->name('tamara.success');
    Route::get('/tamara/failure/{invoice}', [PaymentController::class, 'handleTamaraFailure'])->name('tamara.failure');
    Route::get('/tamara/cancel/{invoice}', [PaymentController::class, 'handleTamaraCancel'])->name('tamara.cancel');
    Route::post('/payment/retry/tamara/{invoice}', [PaymentController::class, 'retryTamaraPayment'])
        ->where('invoice', '[0-9]+')
        ->name('payment_retry_tamara');

    Route::prefix('customer')->name('customer.')->group(function () {
        Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/bookings', [CustomerDashboardController::class, 'listBookings'])->name('bookings.index');
        Route::get('/invoices/{invoice}', [CustomerDashboardController::class, 'showInvoice'])->where('invoice', '[0-9]+')->name('invoices.show');
    });
});

// --- Authentication Routes ---
Route::middleware('guest')->group(function () {
    Route::get('login/otp', [OtpLoginController::class, 'showLoginForm'])->name('login.otp.form');
    Route::post('login/otp/request', [OtpLoginController::class, 'requestOtp'])->name('login.otp.request');
    Route::get('login/otp/verify', [OtpLoginController::class, 'showOtpForm'])->name('login.otp.verify.form');
    Route::post('login/otp/verify', [OtpLoginController::class, 'verifyOtp'])->name('login.otp.verify');
    Route::get('login', function() { return redirect()->route('login.otp.form'); })->name('login');

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
    Route::get('/register/verify', [RegisterController::class, 'showVerifyForm'])->name('register.verify.form');
    Route::post('/register/verify', [RegisterController::class, 'verifyOtp'])->name('register.verify');
    Route::post('/register/resend-otp', [RegisterController::class, 'resendOtp'])->name('register.resend.otp');
});

Route::post('/logout', [OtpLoginController::class, 'logout'])->middleware('auth')->name('logout');

// --- Admin Routes ---
Route::middleware(['auth', EnsureUserIsAdmin::class])
     ->prefix('admin')
     ->name('admin.')
     ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('service-categories', AdminServiceCategoryController::class)->parameters(['service-categories' => 'serviceCategory']);
        Route::resource('services', AdminServiceController::class);
        Route::resource('bank-accounts', BankAccountController::class)->except(['show']);
        Route::resource('discount-codes', DiscountCodeController::class)->except(['show']);

        Route::patch('bank-accounts/{bank_account}/toggle-active', [BankAccountController::class, 'toggleActive'])->name('bank-accounts.toggleActive');
        Route::patch('discount-codes/{discount_code}/toggle-active', [DiscountCodeController::class, 'toggleActive'])->name('discount-codes.toggleActive');

        Route::get('availability', [AdminAvailabilityController::class, 'index'])->name('availability.index');
        Route::post('availability/schedule', [AdminAvailabilityController::class, 'updateSchedule'])->name('availability.schedule.update');
        Route::post('availability/exceptions', [AdminAvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
        Route::delete('availability/exceptions/{exception}', [AdminAvailabilityController::class, 'destroyException'])->name('availability.exceptions.destroy');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/settings/google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('settings.google-calendar.connect');
        Route::get('/settings/google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('settings.google-calendar.callback');
        Route::post('/settings/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('settings.google-calendar.disconnect');

        Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.updateStatus');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
        Route::patch('invoices/{invoice}/confirm-bank-transfer', [InvoiceController::class, 'confirmBankTransfer'])->name('invoices.confirm-bank-transfer');
		
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        Route::get('sms-templates', [SmsTemplateController::class, 'index'])->name('sms-templates.index');
        Route::get('sms-templates/{smsTemplate}/edit', [SmsTemplateController::class, 'edit'])->name('sms-templates.edit');
        Route::put('sms-templates/{smsTemplate}', [SmsTemplateController::class, 'update'])->name('sms-templates.update');

        // --- MODIFICATION START: Routes for Manual Booking by Admin ---
        Route::get('manual-booking/create', [AdminManualBookingController::class, 'create'])->name('manual-booking.create');
        Route::post('manual-booking', [AdminManualBookingController::class, 'store'])->name('manual-booking.store');
        // --- MODIFICATION END ---
});

// Tamara Webhook Routes
Route::post('/tamara/webhook', [PaymentController::class, 'handleTamaraWebhook'])->name('tamara.webhook');
Route::post('/tamara/Webhook', [PaymentController::class, 'handleTamaraWebhook']);

// Tamara Webhook Test and Diagnostic Routes
Route::get('/tamara/test', function() {
    return response()->json([
        'status' => 'ok',
        'message' => 'Tamara routes are working',
        'timestamp' => now()->toDateTimeString(),
        'server_time' => date('Y-m-d H:i:s')
    ]);
});

Route::get('/tamara/check-token', function() {
    $token = config('services.tamara.notification_token');
    return response()->json([
        'token_length' => strlen($token),
        'token_first_10_chars' => substr($token, 0, 10) . '...',
        'server_time' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ]);
});
