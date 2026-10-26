<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\Pages\Concerns\BuildsSiteSettingsSection;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Social links — WU-S2 del rediseño del backoffice (`odd/tasks/publish-to-live-website.md`).
 *
 * Los perfiles que el footer enlaza, como lista editable de plataforma + URL. Es el `Repeater`
 * que ya existía: la única diferencia con el resto es que el prototipo no le asigna claves de
 * `site_settings` propias, y tiene razón — su dato es `social_links`, un JSON con forma de lista.
 */
class SocialLinksSettingsPage extends Page implements HasForms
{
    use BuildsSiteSettingsSection;

    protected static ?string $cluster = WebsiteCluster::class;

    protected string $view = 'website::filament.pages.site-settings';

    protected static ?string $slug = 'settings-social';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    public static function section(): string
    {
        return WebsiteSettingSections::SOCIAL;
    }
}
