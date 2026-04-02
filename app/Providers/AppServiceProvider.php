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
                ? new \Modules\Mailing\Services\SaaSMailer()
                : new \Modules\Mailing\Services\SimulationMailer();
        });
    }
    public function boot(): void
    {
        // Intercepta todos los correos si hay una dirección global de prueba configurada
        if ($globalTo = env('MAIL_TO_ADDRESS')) {
            $addresses = array_map('trim', explode(',', $globalTo));
            \Illuminate\Support\Facades\Mail::alwaysTo($addresses);
        }


        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
