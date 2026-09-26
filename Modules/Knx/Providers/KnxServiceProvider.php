<?php

namespace Modules\Knx\Providers;

use Illuminate\Support\ServiceProvider;
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
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
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
