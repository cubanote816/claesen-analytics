<?php

declare(strict_types=1);

namespace Modules\FieldOps\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\FieldOps\Services\FieldOpsTenantService;

/**
 * CLA-496: authorization for the infrastructure resources (Complex, Terrain,
 * Structure, LuminaireFrame, Luminaire, ElectricalBoard) only. Kept separate from
 * FieldOpsTenantPolicy (FoClient + the 3 maintenance models) because Laravel strips
 * the class-string argument before invoking a class-based ability like `create` —
 * a single shared policy has no way to tell which of the 10 registered models
 * triggered the call, so create()/update()/delete() can only be added safely once
 * the infrastructure resources have their own policy.
 */
class FieldOpsInfrastructurePolicy
{
    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function view(User $user, Model $model): bool
    {
        return $this->tenants->canView($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('fieldops.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->tenants->canView($user, $model) && $user->can('fieldops.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->tenants->canView($user, $model) && $user->can('fieldops.delete-infrastructure');
    }
}
