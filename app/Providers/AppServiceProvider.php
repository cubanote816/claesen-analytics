<?php

namespace App\Providers;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Mailing\Services\MicrosoftGraphMailer;
use Modules\Mailing\Services\SaaSMailer;
use Modules\Mailing\Services\SimulationMailer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CLA-532: fail-closed allow-list. config('app.mailing_driver') is the
        // single, config:cache-safe source; an unknown or unset value raises a
        // controlled MailConfigurationException before any send — there is no
        // implicit fallback to a real transport.
        $this->app->bind(MarketingCampaignInterface::class, function () {
            return match (config('app.mailing_driver')) {
                'simulation' => new SimulationMailer,
                'saas' => new SaaSMailer,
                'microsoft-graph' => new MicrosoftGraphMailer,
                default => throw new MailConfigurationException(
                    "Unknown mailing driver: '".config('app.mailing_driver')."'. "
                    .'Valid values: microsoft-graph, simulation, saas.'
                ),
            };
        });
    }

    public function boot(): void
    {
        // CLA-532: global "always to" override for the DEFAULT mailer, from
        // config('mail.always_to') (config:cache-safe) instead of env(). Applied
        // whenever the value is non-empty. The named 'microsoft-graph' mailer is
        // redirected separately in MailingServiceProvider::boot().
        if ($globalTo = config('mail.always_to')) {
            Mail::alwaysTo($globalTo);
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
