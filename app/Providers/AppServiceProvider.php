<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->is_admin);
        Gate::define('run-shop', fn (User $user) => $user->shop?->isApproved() ?? false);

        // Password reset emails go to the vendor set-password page (admins are managed from the command line).
        ResetPassword::createUrlUsing(fn (User $user, string $token) => route('vendor.password.edit', ['token' => $token, 'email' => $user->email]));
    }
}
