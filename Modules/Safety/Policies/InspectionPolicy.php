<?php

namespace Modules\Safety\Policies;

use Modules\Core\Models\User;
use Modules\Safety\Models\Inspection;

class InspectionPolicy
{
    public function view(User $user, Inspection $inspection): bool
    {
        return $user->hasRole('super_admin') || $inspection->user_id === $user->id;
    }

    public function downloadPdf(User $user, Inspection $inspection): bool
    {
        return $user->hasRole('super_admin') || $inspection->user_id === $user->id;
    }

    public function viewPhoto(User $user, Inspection $inspection): bool
    {
        return $user->hasRole('super_admin') || $inspection->user_id === $user->id;
    }
}
