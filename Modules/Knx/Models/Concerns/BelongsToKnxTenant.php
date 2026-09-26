<?php

namespace Modules\Knx\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Knx\Support\KnxTenant;

/**
 * Every KNX row belongs to Electro Bertels, so nothing in this module should
 * ever have to remember to set the tenant by hand.
 *
 * This fills `organization_id` on create when the caller did not provide one,
 * and throws (KnxTenant) when Bertels does not exist yet — a KNX row under the
 * wrong organization is worse than a loud failure.
 *
 * Deliberately NOT a global scope: there is exactly one tenant here, so a scope
 * would only add a way for "no rows" bugs to hide. Cross-tenant authorisation is
 * the route middleware's job (`organization:electro-bertels`).
 */
trait BelongsToKnxTenant
{
    protected static function bootBelongsToKnxTenant(): void
    {
        static::creating(function (Model $model): void {
            if ($model->organization_id === null) {
                $model->organization_id = KnxTenant::organizationId();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
