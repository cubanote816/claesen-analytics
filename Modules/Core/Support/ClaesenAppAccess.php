<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Models\Organization;
use Modules\Core\Models\User;

/**
 * CLA-704 (S7): the organization boundary of the Claesen apps' sign-ins, independent of
 * config('organizations.enforce') (off in production). A user whose organization is set
 * and is not Claesen never signs in to a Claesen app; a user with no organization keeps
 * today's behaviour. Fails closed: when the Claesen organization cannot be resolved,
 * every user with a set organization is denied.
 */
final class ClaesenAppAccess
{
    public static function denies(User $user): bool
    {
        if ($user->organization_id === null) {
            return false;
        }

        $claesenId = Organization::query()->where('slug', Organization::CLAESEN_SLUG)->value('id');

        return $claesenId === null || (int) $user->organization_id !== (int) $claesenId;
    }
}
