<?php

declare(strict_types=1);

namespace Modules\FieldOps\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\FieldOps\Services\FieldOpsTenantService;

class FieldOpsTenantPolicy
{
    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function view(User $user, Model $model): bool
    {
        return $this->tenants->canView($user, $model);
    }
}
