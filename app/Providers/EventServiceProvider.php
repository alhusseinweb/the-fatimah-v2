<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // Add your other event listeners here
        // Example:
        // \App\Events\BookingMade::class => [
        //     \App\Listeners\SendBookingConfirmation::class,
        // ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool // Return type added for clarity, adjust if needed based on your Laravel version
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Set to true if you want automatic event discovery
    }
}