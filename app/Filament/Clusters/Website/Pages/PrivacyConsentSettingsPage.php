<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Privacy & consent — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * Los embeds que dependen de cookies y el seguimiento del consentimiento. `maps_consent_mode` es
 * el que decide si el mapa se carga siempre, sólo con consentimiento o nunca — y el mapa sólo se
 * puede ver en el sitio si esto lo permite, así que no es un ajuste cosmético.
 *
 * `contact_consent_version` es la versión del texto de consentimiento del formulario: se guarda
 * con cada envío, y hoy está **vacía a propósito** porque el cliente todavía no entregó el texto.
 * El formulario la exige sólo si el sitio declara una versión, así que mientras esté vacía el
 * campo no bloquea ningún envío.
 */
class PrivacyConsentSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-privacy';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function section(): string
    {
        return WebsiteSettingSections::PRIVACY;
    }
}
