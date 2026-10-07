<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * General — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * La identidad legal que el sitio usa en el footer, en las páginas legales y en los datos de
 * búsqueda. Toda la lógica del formulario vive en el trait; esta clase sólo dice **qué sección**
 * es, y por eso cada sección tiene su propia URL.
 */
class GeneralSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-general';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    public static function section(): string
    {
        return WebsiteSettingSections::GENERAL;
    }
}
