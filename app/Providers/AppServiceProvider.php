<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\Unit;
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
        // A unit's HOD and assistant run its area; staff who manage units can open any of them.
        Gate::define('lead-unit', fn (User $user, Unit $unit) => $unit->isLedBy($user->member) || $user->canManage('units'));
        // The media unit's tools: its members draft, report faults and tick tasks; its leaders (and
        // staff with the media role) manage kit, stream keys, going live and publishing.
        Gate::define('media-team', fn (User $user, Unit $unit) => $unit->isMedia()
            && (($user->member && $user->member->status === 'active' && $user->member->unit_id === $unit->id) || $unit->isLedBy($user->member) || $user->canManage('sermons')));
        Gate::define('lead-media', fn (User $user, Unit $unit) => $unit->isMedia() && ($unit->isLedBy($user->member) || $user->canManage('sermons') || $user->canManage('units')));
        // Welfare cases are confidential: the welfare unit's leaders and staff with the welfare area only.
        Gate::define('manage-welfare', fn (User $user, Unit $unit) => $unit->isWelfare() && ($unit->isLedBy($user->member) || $user->canManage('welfare')));
        Gate::define('run-shop', fn (User $user) => $user->shop?->isApproved() ?? false);

        // Sellers reset their password on the seller pages; everyone else on the member pages.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => route(
            $user->can('run-shop') && ! $user->member && ! $user->isStaff() ? 'vendor.password.edit' : 'password.reset',
            ['token' => $token, 'email' => $user->email],
        ));
    }
}
