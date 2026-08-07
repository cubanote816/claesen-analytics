<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;

class UserObserver
{
    /**
     * CLA-347: a deactivated user must lose access immediately, not merely
     * fail future logins. Without this, an active Sanctum token or web
     * session survives until it naturally expires.
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('is_active') || $user->is_active) {
            return;
        }

        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
