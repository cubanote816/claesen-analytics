<?php

namespace Modules\Knx\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Knx';

    public function map(): void
    {
        // docs/BACKEND-API.md §2.1 keeps every path relative to /api; this repo
        // versions module APIs, so the office app points VITE_API_BASE_URL at
        // /api/v1/knx and needs no code change.
        Route::middleware('api')
            ->prefix('api')
            ->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
