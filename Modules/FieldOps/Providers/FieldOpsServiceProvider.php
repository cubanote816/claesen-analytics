<?php

namespace Modules\FieldOps\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Policies\FieldOpsTenantPolicy;
use Nwidart\Modules\Traits\PathNamespace;

class FieldOpsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'FieldOps';

    protected string $nameLower = 'fieldops';

    public function boot(): void
    {
        $this->loadConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'Database/Migrations'));
        $this->loadViewsFrom(module_path($this->name, 'resources/views'), $this->nameLower);
        $this->registerTranslations();
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        foreach ([
            FoClient::class,
            Complex::class,
            Terrain::class,
            Structure::class,
            LuminaireFrame::class,
            Luminaire::class,
            ElectricalBoard::class,
            FoMaintenanceRecord::class,
            FoMaintenanceWorkOrder::class,
        ] as $model) {
            Gate::policy($model, FieldOpsTenantPolicy::class);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\FieldOps\Console\Commands\SyncClientsFromRelationsCommand::class,
            \Modules\FieldOps\Console\Commands\SyncComplexesFromRelationDeliveriesCommand::class,
            \Modules\FieldOps\Console\Commands\GenerateMaintenanceWorkOrdersCommand::class,
        ]);
    }

    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('fieldops:generate-maintenance-work-orders')
                ->hourly()
                ->withoutOverlapping();
        });
    }

    protected function loadConfig(): void
    {
        $configPath = module_path($this->name, 'Config/config.php');

        $this->mergeConfigFrom($configPath, $this->nameLower);
        $this->publishes([$configPath => config_path($this->nameLower.'.php')], 'config');
    }

    public function registerTranslations(): void
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
}
