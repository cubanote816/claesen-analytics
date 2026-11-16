<?php

declare(strict_types=1);

namespace App\Filament\Documentation;

use App\Support\Documentation\ModuleGuide;
use App\Support\Documentation\RenderedGuide;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

/**
 * "Documentation" → one page per module (CLA-693).
 *
 * A module adds its guide with a thin subclass in `Modules/<Module>/Filament/Pages`, which the
 * admin panel already discovers. The subclass names the module and the resource whose access
 * the guide follows: whoever can open the module can read its guide (user decision), so a guard
 * change on the resource changes the guide's guard too.
 *
 * Lives outside app/Filament/Pages on purpose: the admin panel auto-discovers that directory,
 * and an abstract page must never be registered.
 */
abstract class ModuleDocumentationPage extends Page
{
    protected string $view = 'filament.documentation.module-guide';

    protected static ?int $navigationSort = null;

    private ?RenderedGuide $guide = null;

    /** Module directory name under Modules/, e.g. "Mailing". */
    abstract public static function module(): string;

    /**
     * Resource or page whose canAccess() the guide follows.
     *
     * @return class-string
     */
    abstract public static function accessFollows(): string;

    public static function canAccess(): bool
    {
        return static::accessFollows()::canAccess();
    }

    /**
     * The group as registered on the panel — the page and the panel must share this label.
     * The label is a closure (CLAUDE.md rule 10): panel() runs once, in the default locale.
     */
    public static function panelNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::make(fn (): string => static::groupLabel())->collapsible();
    }

    public static function groupLabel(): string
    {
        return __('navigation.groups.documentation');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::groupLabel();
    }

    public static function getNavigationLabel(): string
    {
        $meta = ModuleGuide::meta(static::guideDirectory());

        return (string) ($meta['navigation_label'] ?? $meta['title'] ?? static::module());
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'documentation/' . Str::kebab(static::module());
    }

    public static function guideDirectory(): string
    {
        return ModuleGuide::directoryFor(static::module());
    }

    public function guide(): RenderedGuide
    {
        return $this->guide ??= ModuleGuide::render(static::guideDirectory());
    }

    public function getTitle(): string
    {
        return $this->guide()->title ?: static::module();
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => static::groupLabel(),
            static::getNavigationLabel(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::SevenExtraLarge;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['guide' => $this->guide()];
    }
}
