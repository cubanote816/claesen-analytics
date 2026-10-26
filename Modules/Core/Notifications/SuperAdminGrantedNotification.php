<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\User;

/**
 * F2/CLA-465 — "alertas por creación de super_admin". Fired from
 * Modules\Core\Models\User::assignRole()/syncRoles() (see their own
 * docblocks for why Spatie Permission itself never emits a domain event
 * here — the model_has_roles pivot is written with attach()/sync(), no
 * Eloquent events).
 */
class SuperAdminGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly User $grantedUser) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'granted_user_id' => $this->grantedUser->id,
            'granted_user_name' => $this->grantedUser->name,
            'granted_user_email' => $this->grantedUser->email,
            'message' => "{$this->grantedUser->name} ({$this->grantedUser->email}) was granted the super_admin role.",
        ];
    }
}
