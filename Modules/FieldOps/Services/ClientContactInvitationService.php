<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Notifications\ClientContactInvitationNotification;
use Spatie\Permission\Models\Role;

class ClientContactInvitationService
{
    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function invite(FoClient $client, User $actor, array $data): User
    {
        $this->assertCanManageContacts($client, $actor);
        $email = strtolower(trim($data['email']));
        $activationCode = null;

        $user = DB::transaction(function () use ($client, $data, $email, &$activationCode): User {
            $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->lockForUpdate()->first();
            if ($user && ($user->employee_id !== null || ! $user->hasRole('client'))) {
                throw ValidationException::withMessages(['email' => 'This email belongs to a non-client account.']);
            }

            if (! $user) {
                $activationCode = Str::random(64);
                $user = User::query()->create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'is_active' => true,
                    'password' => null,
                    'password_set_at' => null,
                    'language' => $data['language'] ?? $client->language ?? 'nl',
                ]);
                $user->forceFill([
                    'activation_code_hash' => hash('sha256', $activationCode),
                    'activation_code_expires_at' => now()->addDays(7),
                ])->saveQuietly();
                $user->syncRoles([Role::findByName('client', 'web')->id]);
            } elseif (! $user->hasCompletedPasswordSetup()) {
                $activationCode = Str::random(64);
                $user->forceFill([
                    'activation_code_hash' => hash('sha256', $activationCode),
                    'activation_code_expires_at' => now()->addDays(7),
                ])->saveQuietly();
            }

            $user->fieldOpsClients()->syncWithoutDetaching([
                $client->id => [
                    'is_active' => true,
                    'can_view' => (bool) ($data['can_view'] ?? true),
                    'can_report' => (bool) ($data['can_report'] ?? true),
                    'can_manage_contacts' => (bool) ($data['can_manage_contacts'] ?? false),
                ],
            ]);

            return $user;
        });

        $user->notify(new ClientContactInvitationNotification($client, $activationCode));

        return $user->fresh('fieldOpsClients');
    }

    private function assertCanManageContacts(FoClient $client, User $actor): void
    {
        if (! $this->tenants->isClientUser($actor)) {
            if (! $actor->hasAnyRole(['admin', 'super_admin'])) {
                throw new AuthorizationException;
            }

            return;
        }

        $allowed = $actor->fieldOpsClients()
            ->where('fo_clients.id', $client->id)
            ->wherePivot('is_active', true)
            ->wherePivot('can_view', true)
            ->wherePivot('can_manage_contacts', true)
            ->exists();
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
