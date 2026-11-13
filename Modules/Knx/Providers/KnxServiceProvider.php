<?php

namespace Modules\Knx\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Modules\Knx\Console\Commands\KnxImportWorklistCommand;
use Modules\Knx\Console\Commands\KnxNotificationDigestCommand;
use Modules\Knx\Http\Middleware\AssertDocumentedSuccessCode;
use Modules\Knx\Services\PlanAnalysis\OpenAiApiKeyCredentials;
use Modules\Knx\Services\PlanAnalysis\OpenAiCredentialSource;
use Nwidart\Modules\Traits\PathNamespace;

/**
 * KNX installation management (Electro Bertels).
 *
 * Consumed by the office app *Kantoor* and, later, by the field app *Veld*.
 * The wire contract lives in the front repos:
 *   - docs/BACKEND-API.md        (32 endpoints, 13 groups)
 *   - docs/BACKEND-API-ZONES.md  (zones: closed contract)
 *
 * Everything in this module is tenant-scoped to Electro Bertels; see
 * config/knx.php and the `organization:` middleware on its routes.
 */
class KnxServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Knx';

    protected string $nameLower = 'knx';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // KNX-20 — the path-only anti-drift test could not see a controller that
        // returned a 204 while the spec documented a 200. This guard closes that gap
        // by checking every 2xx the suite actually produces; it is test-only.
        if ($this->app->runningUnitTests()) {
            $this->app->make(Kernel::class)
                ->pushMiddleware(AssertDocumentedSuccessCode::class);
        }

        // KNX-54: the digest runs hourly. `emailed_at` on the notice makes a second run
        // a no-op, so the cadence is the only thing this schedule decides.
        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('knx:notification-digest')
                ->hourly()
                ->withoutOverlapping();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Plan analysis (KNX-48, plano IA): the OpenAI adapter takes its bearer token from
        // a credential source; today only the API key. Another source (a "Sign in with
        // ChatGPT" plan token) would be bound here without touching the adapter.
        $this->app->bind(
            OpenAiCredentialSource::class,
            OpenAiApiKeyCredentials::class,
        );

        // `knx:import-worklist`: loads a governed KNX worklist into a project's boards
        // (KNX-3). Registered here because the module had no commands before.
        $this->commands([
            KnxImportWorklistCommand::class,
            // KNX-54: the non-urgent notices go out as a digest, hourly and at visit
            // close.
            KnxNotificationDigestCommand::class,
        ]);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $this->mergeConfigFrom(
            module_path($this->name, 'config/config.php'),
            $this->nameLower,
        );
    }

    protected function registerViews(): void
    {
        $sourcePath = module_path($this->name, 'resources/views');

        if (is_dir($sourcePath)) {
            $this->loadViewsFrom($sourcePath, $this->nameLower);
        }
    }
}
