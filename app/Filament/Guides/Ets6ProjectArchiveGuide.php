<?php

declare(strict_types=1);

namespace App\Filament\Guides;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

/**
 * "Guides & Procedures" → ETS6 Project Archive: how the team shares one KNX project in ETS6.
 *
 * One page and one view for both panels; only the skin differs. Electro Bertels uses its approved
 * mockup as-is, Claesen gets the same content in its own identity (DESIGN.md). The view picks the
 * skin from the current panel, so neither panel can render the other company's look.
 *
 * Registered explicitly by both panel providers, and outside app/Filament/Pages on purpose: the
 * admin panel auto-discovers that directory and the bertels panel discovers nothing, so an
 * explicit ->pages() entry is the only registration that reads the same in both.
 *
 * super_admin only, in BOTH panels (user decision): the bertels panel also admits the client
 * organisation's users, and an internal procedure is not theirs to read.
 *
 * The guide body stays in English, as the approved mockups are; only the navigation is translated.
 */
class Ets6ProjectArchiveGuide extends Page
{
    protected static ?string $slug = 'guides/ets6-project-archive';

    protected string $view = 'filament.guides.ets6-project-archive';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (bool) $user->is_active && $user->hasRole('super_admin');
    }

    /**
     * The group as registered on each panel — the page and the panel must share this label.
     * The label is a closure (CLAUDE.md rule 10): panel() runs once, in the default locale.
     */
    public static function panelNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::make(fn (): string => static::groupLabel())->collapsible();
    }

    public static function groupLabel(): string
    {
        return __('navigation.groups.guides_procedures');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::groupLabel();
    }

    /**
     * Claesen's groups carry the icon and Bertels' items do (Filament draws one or the other,
     * never both), so the item follows the convention of the panel it is in.
     */
    public static function getNavigationIcon(): ?string
    {
        return static::isBertels() ? 'heroicon-o-book-open' : null;
    }

    public static function getNavigationLabel(): string
    {
        return 'ETS6 Project Archive';
    }

    public function getTitle(): string
    {
        return 'Working together on KNX projects with the ETS6 Project Archive';
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => static::groupLabel(),
            'ETS6 Project Archive',
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FiveExtraLarge;
    }

    public static function isBertels(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'bertels';
    }

    /**
     * The folders the team copies into ETS6. UNC paths on purpose: drive letters differ per PC.
     *
     * CLA-645: identical on both panels (user decision) — both companies work out of the same
     * Project Archive share, with Electro Bertels' own Linked folder for Claesen's projects. The
     * former per-panel paths were wrong: Bertels' Linked folder routed through BertelsGedeeld
     * instead of ClaesenGedeeld, and Claesen's guide pointed at a separate, now-unused
     * ClaesenGedeeld Project Archive path.
     *
     * @return list<array{label: string, location: string, path: string}>
     */
    public static function networkFolders(): array
    {
        return [
            [
                'label' => 'Project Archive',
                'location' => 'Settings → Data Storage → Project Archive',
                'path' => '\\\\RSBertels\\BertelsGedeeld\\Projecten\\Automation\\_ETS6_ProjectArchive',
            ],
            [
                'label' => 'Linked folder',
                'location' => 'Settings → Data Storage → Linked Folders → Add Link',
                'path' => '\\\\RSBertels\\ClaesenGedeeld\\Projecten\\Automation\\_ETS6_Claesen_ProjectArchive',
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'skin' => static::isBertels() ? 'bertels' : 'claesen',
            'folders' => static::networkFolders(),
        ];
    }
}
