<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Horizon exposes queue internals (job payloads, failed-job traces), so
     * it is limited to super-admins and holders of `view-audit-logs`.
     * Guests ($user === null) are always denied. Gate::before in
     * AppServiceProvider already admits super-admins to every ability, the
     * explicit role check keeps this gate strict on its own.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            if ($user === null) {
                return false;
            }

            return $user->hasRole('super-admin')
                || $user->hasPermissionTo('view-audit-logs');
        });
    }
}
