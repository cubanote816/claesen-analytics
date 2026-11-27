<?php

namespace Modules\Prospects\Policies;

use Modules\Core\Models\User;
use Modules\Prospects\Models\ContactLog;

/**
 * CLA-711 (S3): management uses the admin role, so only super_admin and admin
 * may add or remove manual contact entries.
 */
class ContactLogPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function delete(User $user, ContactLog $contactLog): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }
}
