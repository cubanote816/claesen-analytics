<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function localUser(): User
    {
        return UserFactory::new()->create([
            'password'        => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'microsoft_id'    => null,
        ]);
    }

    private function microsoftUser(): User
    {
        return UserFactory::new()->create([
            'password'        => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'microsoft_id'    => 'aad-object-id-123',
        ]);
    }

    private function pendingLocalUser(): User
    {
        $user = UserFactory::new()->create([
            'password_set_at' => null,
            'microsoft_id'    => null,
        ]);
        $user->forceFill(['password' => null])->saveQuietly();
        return $user->fresh();
    }

    // 1. Local user changes password successfully
    public function test_local_user_can_change_password(): void
    {
        $user  = $this->localUser();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'Secret1234!',
                'password'              => 'NewSecret5678!',
                'password_confirmation' => 'NewSecret5678!',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'Password updated successfully.']);

        // New password works for login
        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'NewSecret5678!',
        ])->assertStatus(200);
    }

    // 2. Wrong current_password returns 422
    public function test_wrong_current_password_returns_422(): void
    {
        $user  = $this->localUser();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'WrongPassword!',
                'password'              => 'NewSecret5678!',
                'password_confirmation' => 'NewSecret5678!',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }

    // 3. Microsoft user gets 403
    public function test_microsoft_user_cannot_change_password(): void
    {
        $user  = $this->microsoftUser();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'Secret1234!',
                'password'              => 'NewSecret5678!',
                'password_confirmation' => 'NewSecret5678!',
            ])
            ->assertStatus(403);
    }

    // 4. Unauthenticated returns 401
    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/auth/change-password', [
            'current_password'      => 'Secret1234!',
            'password'              => 'NewSecret5678!',
            'password_confirmation' => 'NewSecret5678!',
        ])->assertStatus(401);
    }

    // 5. Pending user (no password set) blocked by EnsurePasswordIsSet → 403
    public function test_pending_user_blocked_by_ensure_password_is_set(): void
    {
        $user  = $this->pendingLocalUser();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'anything',
                'password'              => 'NewSecret5678!',
                'password_confirmation' => 'NewSecret5678!',
            ])
            ->assertStatus(403)
            ->assertJsonFragment(['message' => 'Account setup required. Complete password activation first.']);
    }

    // 6. Other tokens revoked; current token remains valid
    public function test_other_tokens_revoked_current_token_stays_valid(): void
    {
        $user          = $this->localUser();
        $currentResult = $user->createToken('current-device');
        $otherResult   = $user->createToken('other-device');

        $this->withToken($currentResult->plainTextToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'Secret1234!',
                'password'              => 'NewSecret5678!',
                'password_confirmation' => 'NewSecret5678!',
            ])
            ->assertStatus(200);

        // Clear the in-memory RequestGuard cache so each request re-authenticates
        // against the DB, not the cached user from the change-password request.
        auth()->forgetGuards();

        $this->withToken($currentResult->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertStatus(200);

        auth()->forgetGuards();

        $this->withToken($otherResult->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }
}
