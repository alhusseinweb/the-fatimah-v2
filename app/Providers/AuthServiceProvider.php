<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate; // Uncomment this if you use Gates
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Register your model policies here, for example:
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // If you use Gates, you can define them here:
        // Gate::define('edit-settings', function (User $user) {
        //     return $user->isAdmin;
        // });
    }
}