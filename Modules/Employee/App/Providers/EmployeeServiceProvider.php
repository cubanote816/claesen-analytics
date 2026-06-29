<?php

namespace Modules\Employee\App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Employee\Contracts\EmployeeRankingContract;
use Modules\Employee\Repositories\EmployeeRepository;
use Modules\Employee\Repositories\ProjectRepository;
use Modules\Employee\Repositories\TimeEntryRepository;
use Modules\Employee\Services\EmployeeDashboardRankingService;
use Modules\Employee\Services\EmployeeRankingService;
use Modules\Employee\Services\EmployeeService;
use Modules\Employee\Services\EmployeeTimeService;
use Modules\Employee\Services\ProjectInvoiceService;
use Modules\Employee\Services\ProjectService;
use Modules\Employee\Services\StatsCalculator;

class EmployeeServiceProvider extends ServiceProvider
{
    protected string $moduleName      = 'Employee';
    protected string $moduleNameLower = 'employee';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(EmployeeRepository::class, fn() => new EmployeeRepository());
        $this->app->singleton(TimeEntryRepository::class, fn() => new TimeEntryRepository());
        $this->app->singleton(ProjectRepository::class,   fn() => new ProjectRepository());

        $this->app->singleton(StatsCalculator::class, fn() => new StatsCalculator());

        $this->app->singleton(ProjectService::class, fn($app) => new ProjectService(
            $app->make(ProjectRepository::class),
            $app->make(TimeEntryRepository::class)
        ));

        $this->app->singleton(ProjectInvoiceService::class, fn() => new ProjectInvoiceService());

        $this->app->singleton(EmployeeRankingService::class, fn($app) => new EmployeeRankingService(
            $app->make(EmployeeRepository::class),
            $app->make(TimeEntryRepository::class)
        ));

        $this->app->singleton(EmployeeTimeService::class, fn($app) => new EmployeeTimeService(
            $app->make(EmployeeRepository::class),
            $app->make(TimeEntryRepository::class),
            $app->make(ProjectRepository::class),
            $app->make(StatsCalculator::class),
            $app->make(ProjectService::class),
            $app->make(EmployeeRankingService::class)
        ));

        $this->app->singleton(EmployeeService::class, fn($app) => new EmployeeService(
            $app->make(EmployeeRepository::class)
        ));

        $this->app->singleton(EmployeeDashboardRankingService::class, fn($app) => new EmployeeDashboardRankingService(
            $app->make(EmployeeRepository::class),
            $app->make(TimeEntryRepository::class)
        ));

        $this->app->bind(EmployeeRankingContract::class, EmployeeDashboardRankingService::class);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $this->publishes([module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower . '.php')], 'config');
        $this->mergeConfigFrom(module_path($this->moduleName, 'config/config.php'), $this->moduleNameLower);
    }

    public function registerViews(): void
    {
        $viewPath   = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');
        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower . '-module-views']);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);

        $componentNamespace = str_replace('/', '\\', config('modules.namespace') . '\\' . $this->moduleName . '\\' . config('modules.paths.generator.component-class.path'));
        Blade::componentNamespace($componentNamespace, $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
