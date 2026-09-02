<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use ReflectionClass;
use Tests\TestCase;

/**
 * CLA-516 — Filament 5.7 / Livewire 4.4 / MediaLibrary 11 ya estaban en su sitio
 * (CLA-518/520). El unico cambio real de este ticket es
 * filament/spatie-laravel-media-library-plugin 3.3.30 -> 5.7.6: antes de CLA-519 el
 * plugin de la linea 3 (Filament 3) convivia con Filament 5, un emparejamiento
 * incorrecto. Estos tests fijan el emparejamiento correcto y que la capa de media
 * de los recursos Filament sigue certificada.
 */
class Laravel13FilamentMediaCompatibilityTest extends TestCase
{
    public function test_the_ui_stack_is_on_the_laravel_13_compatible_majors(): void
    {
        $this->assertStringStartsWith('5.', $this->prettyVersion('filament/filament'));
        $this->assertStringStartsWith('5.', $this->prettyVersion('filament/spatie-laravel-media-library-plugin'));
        $this->assertStringStartsWith('4.', $this->prettyVersion('livewire/livewire'));
        $this->assertStringStartsWith('11.', $this->prettyVersion('spatie/laravel-medialibrary'));
    }

    public function test_the_media_library_plugin_is_locked_in_step_with_filament_core(): void
    {
        // The v3 plugin (Filament 3 line) previously coexisted with Filament 5.
        // v5.7.x requires "filament/support": "self.version", so both must match.
        $this->assertSame(
            $this->prettyVersion('filament/support'),
            $this->prettyVersion('filament/spatie-laravel-media-library-plugin'),
        );
    }

    public function test_the_spatie_media_upload_component_extends_the_v5_file_upload(): void
    {
        $this->assertSame(
            FileUpload::class,
            (new ReflectionClass(SpatieMediaLibraryFileUpload::class))->getParentClass()->getName(),
        );

        // Methods the resource forms + saveRelationshipsUsing() closures rely on.
        foreach (['collection', 'conversion', 'conversionsDisk', 'customProperties', 'saveUploadedFiles'] as $method) {
            $this->assertTrue(
                method_exists(SpatieMediaLibraryFileUpload::class, $method),
                "SpatieMediaLibraryFileUpload::{$method}() is expected by application code.",
            );
        }
    }

    public function test_every_admin_panel_resource_resolves_without_error(): void
    {
        $failures = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            try {
                $resource::getModel();
                $resource::getPages();
                $resource::getNavigationLabel();
            } catch (\Throwable $e) {
                $failures[] = $resource.' => '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures);
    }

    private function prettyVersion(string $package): string
    {
        return ltrim(InstalledVersions::getPrettyVersion($package) ?? '', 'v');
    }
}
