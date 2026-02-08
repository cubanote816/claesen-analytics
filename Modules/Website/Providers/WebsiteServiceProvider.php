<?php

namespace Modules\Website\Providers;

use Illuminate\Support\ServiceProvider;
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
    }
}
