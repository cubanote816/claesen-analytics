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
                : new \Modules\Mailing\Services\MicrosoftGraphMailer();
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

        // El backoffice no tiene salida a internet. Las peticiones directas de LAN a
        // backoffice.claesen.local (REMOTE_ADDR real) deben generar URLs de storage locales;
        // las peticiones proxied desde sbapu03 vía túnel también llegan con ese mismo Host
        // (backoffice.claesen.local, forzado por proxy_set_header en sbapu03) pero con
        // REMOTE_ADDR 127.0.0.1 — esas deben seguir usando MEDIA_URL para los visitantes públicos.
        if (! $this->app->runningInConsole()
            && request()->getHost() === parse_url(config('app.url'), PHP_URL_HOST)
            && request()->ip() !== '127.0.0.1'
        ) {
            config(['filesystems.disks.public.url' => rtrim(config('app.url'), '/').'/storage']);
        }
    }
}
