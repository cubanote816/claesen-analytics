<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Address & maps — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * Dónde está el negocio y cómo lo encuentra el visitante. El domicilio estructurado (cuatro
 * campos, no JSON crudo) y el nombre del país traducible existían y se **movieron** acá; la línea
 * de `address` de Claesen convive con ellos porque son dos representaciones explícitas del mismo
 * hecho, que es una decisión declarada (2A) y no un descuido.
 */
class AddressSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-address';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function section(): string
    {
        return WebsiteSettingSections::ADDRESS;
    }
}
