<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Site;

/**
 * F1/P3 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * `site_id` is the single source of truth on every site-owned row (decision
 * D3); the organization is always derived through `sites.organization_id`,
 * never duplicated as a second foreign key on the owning table.
 *
 * INERT AS OF PHASE P3 (decision D4). The global scope below returns without
 * touching the query while `config('organizations.enforce')` is false, which
 * is its value everywhere until phase P5 turns enforcement on. That is the
 * whole point of the sequence structure → context → authorization →
 * enforcement: a fail-closed scope must never land before the context that
 * would feed it exists.
 *
 * Deliberately absent here: any resolution of "which site is this request
 * for". Phase P3 has no answer for that (the panel is still single-site and
 * the public API is still site-agnostic), so inventing one now would be
 * guesswork frozen into a trait. Phase P5 adds it together with the
 * fail-closed MissingOrganizationContext behaviour.
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope('site', function (Builder $query): void {
            if (! config('organizations.enforce')) {
                return;
            }

            // Phase P5 fills this in. Reaching here with enforcement on and no
            // implementation is a bug, not a silent pass-through — but P5 owns
            // that decision, so P3 leaves the branch unreachable by config.
            throw new \LogicException(
                'organizations.enforce is on but site scoping is not implemented yet (phase P5).'
            );
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
