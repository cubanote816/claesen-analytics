<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Site;

/**
 * CLA-598: a Filament resource whose model carries `site_id` shows only the rows
 * of the site the current panel manages (config('organizations.panel_sites')).
 * Fail-closed: with no site (its row does not exist yet) the resource is hidden
 * and its query matches nothing. Independent of ORGANIZATIONS_ENFORCE on purpose —
 * the BelongsToSite global scope is inert while the flag is off.
 */
trait ScopedToPanelSite
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where($query->getModel()->qualifyColumn('site_id'), Site::forPanel()?->id ?? 0);
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }
}
