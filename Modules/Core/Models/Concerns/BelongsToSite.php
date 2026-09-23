<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Exceptions\MissingOrganizationContext;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;

/**
 * F1/P3 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * `site_id` is the single source of truth on every site-owned row (decision
 * D3); the organization is always derived through `sites.organization_id`,
 * never duplicated as a second foreign key on the owning table.
 *
 * Gated by config('organizations.enforce') (D4) — false everywhere in every
 * real environment today, so this stays a no-op for Claesen. F3/CLA-471 gave
 * it its first real consumer: Modules\Core\Http\Middleware\ResolveRequestSite
 * sets the site for the public Website API; OrganizationContext::site()
 * falls back to the authenticated user's own organization's site for
 * everything else (the admin panel). With the flag on and no site resolved
 * either way, this fails loud (MissingOrganizationContext) rather than
 * silently returning nothing — a code path querying a site-owned model
 * outside any site-resolving context is a bug to surface, not hide.
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope('site', function (Builder $query): void {
            if (! config('organizations.enforce')) {
                return;
            }

            $siteId = app(OrganizationContext::class)->siteId();

            if ($siteId === null) {
                throw new MissingOrganizationContext(static::class);
            }

            $query->where($query->getModel()->qualifyColumn('site_id'), $siteId);
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
