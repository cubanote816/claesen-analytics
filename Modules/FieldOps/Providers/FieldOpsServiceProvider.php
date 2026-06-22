<?php

namespace Modules\FieldOps\Providers;

use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;

class FieldOpsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'FieldOps';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'Database/Migrations'));
        $this->loadRoutesFrom(module_path($this->name, 'Routes/api.php'));
    }

    public function register(): void {}
}
