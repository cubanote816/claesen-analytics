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


        // F1/P5c of the multi-organization program (ADR D5,
        // docs/ai/adr-multi-organization.md). With the flag off (the
        // default in every environment today) this is byte-for-byte the
        // original rule: super_admin bypasses every ability, unconditionally.
        //
        // With the flag on: (1) a subject registered in
        // organizations.owned_models only grants the bypass if it belongs to
        // the super_admin's own organization — a mismatch defers to the
        // model's own scope/policy (null), which deny; (2) an unregistered
        // subject is deliberately left untouched (keeps the old unconditional
        // bypass) — the registry is an allowlist that grows deliberately, see
        // config/organizations.php; (3) no subject at all only bypasses for
        // an ability explicitly listed in platform_abilities (empty today,
        // so this branch is currently unreachable in practice).
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! $user->hasRole('super_admin')) {
                return null;
            }

            if (! config('organizations.enforce')) {
                return true;
            }

            $subject = $arguments[0] ?? null;
            $subjectClass = is_object($subject) ? get_class($subject) : (is_string($subject) ? $subject : null);

            if ($subjectClass === null) {
                return in_array($ability, config('organizations.platform_abilities'), true) ? true : null;
            }

            $ownedModules = config('organizations.owned_models');

            if (! isset($ownedModules[$subjectClass])) {
                return true;
            }

            $moduleKey = $ownedModules[$subjectClass];
            $owningOrgSlug = collect(config('organizations.owned_modules'))
                ->filter(fn (array $modules) => in_array($moduleKey, $modules, true))
                ->keys()
                ->first();

            return $owningOrgSlug !== null && $user->organization?->slug === $owningOrgSlug ? true : null;
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
