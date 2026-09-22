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
        $this->tenants->assertCanManageContacts($client, $actor);
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

    // CLA-554: updates an EXISTING fo_client_user row only — it never attaches a
    // new one. Attaching new memberships is invite()'s job alone; without the
    // existing-membership check below, this endpoint would double as an
    // undocumented, unaudited way to attach a user to a client, or (BOLA) let an
    // actor mutate an arbitrary (client_id, user_id) pair by guessing both ids.
    public function updateMembership(FoClient $client, User $actor, User $target, array $data): void
    {
        $this->tenants->assertCanManageContacts($client, $actor);

        // A manager can't lock themselves out by mistake; recovery for that case
        // already exists via the backoffice (CLA-553's EditUser), not this endpoint.
        if ($target->id === $actor->id) {
            throw new AuthorizationException;
        }

        // Defense in depth — fo_client_user should only ever hold client-role
        // users (invite() already enforces this on creation), but this endpoint
        // must not become a way to attach pivot capabilities to a non-client account.
        abort_unless($target->hasRole('client'), 404);

        $membershipExists = $target->fieldOpsClients()
            ->where('fo_clients.id', $client->id)
            ->exists();
        abort_unless($membershipExists, 404);

        $pivot = collect($data)
            ->only(['is_active', 'can_view', 'can_report', 'can_manage_contacts'])
            ->map(fn ($value): bool => (bool) $value)
            ->all();

        if ($pivot !== []) {
            $target->fieldOpsClients()->updateExistingPivot($client->id, $pivot);
        }
    }
}
