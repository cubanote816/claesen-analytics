<?php

namespace Modules\Website\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Website\Console\RegenerateProjectMediaCommand;
use Modules\Website\Contracts\ProjectRepositoryInterface;
use Modules\Website\Repositories\EloquentProjectRepository;
use Modules\Website\Contracts\MessageRepositoryInterface;
use Modules\Website\Repositories\EloquentMessageRepository;

class WebsiteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryInterface::class, EloquentProjectRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'website');

        \Modules\Website\Models\ConsultationRequest::observe(\Modules\Website\Observers\ConsultationRequestObserver::class);
        \Modules\Website\Models\Project::observe(\Modules\Website\Observers\ProjectObserver::class);
        \Spatie\MediaLibrary\MediaCollections\Models\Media::observe(\Modules\Website\Observers\MediaObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RegenerateProjectMediaCommand::class]);
        }
    }
}
