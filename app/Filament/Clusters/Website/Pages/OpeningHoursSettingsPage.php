<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Opening hours — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * El horario semanal que el sitio muestra en el footer y en la página de contacto. El
 * `Repeater` de un día por fila con las **cuatro** claves del contrato y el `Toggle` derivado de
 * `hours === null` se **movió** acá tal como estaba: era el trabajo verificado de la ronda
 * anterior.
 *
 * La «nota opcional traducible» del prototipo **no** está: no hay una clave para eso, y agregar
 * una que el sync del sitio descarta en silencio sería un campo que no llega a ningún lado.
 */
class OpeningHoursSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-hours';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    public static function section(): string
    {
        return WebsiteSettingSections::HOURS;
    }
}
