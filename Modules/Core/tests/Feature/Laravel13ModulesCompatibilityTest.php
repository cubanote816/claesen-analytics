<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

/**
 * CLA-517 — nwidart/laravel-modules 12 -> 13 es un bump puro de compatibilidad con
 * Laravel 13 (el diff v12.0.5..v13.0.0 solo toca CI, README y las constraints de
 * composer.json; sin cambios de codigo, config ni API). Estos tests fijan que la
 * arquitectura modular sigue certificada: descubrimiento, rutas, comandos y las
 * claves de config de las que dependen los service providers de modulo.
 */
class Laravel13ModulesCompatibilityTest extends TestCase
{
    /** @var list<string> */
    private const EXPECTED_MODULES = [
        'Analytics', 'Cafca', 'Core', 'Employee', 'FieldOps', 'Intelligence',
        'Mailing', 'Performance', 'Prospects', 'Safety', 'Website',
    ];

    public function test_laravel_modules_is_on_the_v13_line(): void
    {
        $this->assertStringStartsWith(
            '13.',
            ltrim(InstalledVersions::getPrettyVersion('nwidart/laravel-modules') ?? '', 'v'),
        );
    }

    public function test_every_module_in_the_statuses_file_is_discovered_and_enabled(): void
    {
        // Module::allEnabled() keys by module alias (lower-case), not the studly name.
        $enabled = array_map('strtolower', array_keys(Module::allEnabled()));
        $expected = array_map('strtolower', self::EXPECTED_MODULES);

        sort($enabled);
        sort($expected);

        $this->assertSame($expected, $enabled);
    }

    public function test_module_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('api.cafca.index'), 'Expected module route api.cafca.index to be registered.');
    }

    public function test_module_console_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('fieldops:generate-maintenance-work-orders', $commands);
        $this->assertContains('mailing:dispatch-scheduled', $commands);
    }

    public function test_module_provider_config_path_key_resolves(): void
    {
        // Safety/Performance/Prospects providers do
        // module_path($name, config('modules.paths.generator.config.path'))
        // — a null here silently breaks their config loading.
        $this->assertNotNull(config('modules.paths.generator.config.path'));
    }

    public function test_filament_discovers_module_resources(): void
    {
        $resources = Filament::getPanel('admin')->getResources();

        $this->assertNotEmpty(array_filter(
            $resources,
            static fn (string $resource): bool => str_starts_with($resource, 'Modules\\'),
        ));
    }
}
