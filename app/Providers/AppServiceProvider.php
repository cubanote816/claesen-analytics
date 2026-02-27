<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Contracts\MarketingCampaignInterface::class, function ($app) {
            $driver = config('app.mailing_driver', env('MAILING_DRIVER', 'simulation'));
            return $driver === 'saas'
                ? new \App\Services\Mailers\SaaSMailer()
                : new \App\Services\Mailers\SimulationMailer();
        });
    }
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
