<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Contact — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * Teléfono, WhatsApp y correo, que es lo que el visitante ve en todo el sitio. Incluye `phone`,
 * que es la clave textual de **Claesen** (su frontend la lee literal): el prototipo no la
 * menciona porque es del backoffice de Bertels, y sacarla de la UI rompería la edición de Claesen.
 */
class ContactSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-contact';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    public static function section(): string
    {
        return WebsiteSettingSections::CONTACT;
    }
}
