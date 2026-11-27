<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website;

/**
 * CLA-734: who may open the Website screens, besides the panel's own site check.
 *
 * The technician role enters the admin panel only to reach its own work orders (CLAUDE.md rule 5,
 * CLA-581). Every Website screen asks this one place, so a new screen cannot forget the rule.
 */
final class WebsiteAccess
{
    public static function allowsCurrentUser(): bool
    {
        return ! (auth()->user()?->hasRole('technician') ?? false);
    }
}
