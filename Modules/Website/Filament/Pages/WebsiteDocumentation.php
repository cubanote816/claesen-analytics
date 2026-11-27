<?php

declare(strict_types=1);

namespace Modules\Website\Filament\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use App\Filament\Documentation\ModuleDocumentationPage;

/**
 * Website guide (CLA-720). Content: Modules/Website/docs/user-guide/{en,nl}.md.
 *
 * Shown on both panels (the bertels panel registers it by hand). Access follows the projects
 * resource, which is open to whoever can use the panel's Website screens, and never the
 * technician role (CLAUDE.md rule 5, user decision 2026-11-26).
 */
class WebsiteDocumentation extends ModuleDocumentationPage
{
    protected static ?int $navigationSort = 3;

    public static function module(): string
    {
        return 'Website';
    }

    public static function accessFollows(): string
    {
        return ProjectResource::class;
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && ! (auth()->user()?->hasRole('technician') ?? false);
    }
}
