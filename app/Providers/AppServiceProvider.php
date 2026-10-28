<?php

namespace App\Providers;

use App\Models\Setting;
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
        Setting::applyToConfig();

        Gate::define('access-admin', fn (User $user) => $user->isStaff());
        Gate::define('manage', fn (User $user, string $area) => $user->canManage($area));
        Gate::define('run-shop', fn (User $user) => $user->shop?->isApproved() ?? false);

        // Sellers reset their password on the seller pages; everyone else on the member pages.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => route(
            $user->can('run-shop') && ! $user->member && ! $user->isStaff() ? 'vendor.password.edit' : 'password.reset',
            ['token' => $token, 'email' => $user->email],
        ));
    }
}
