<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// CLA-344: the Client Portal must be usable only by active users with the
// 'client' role. Covers the dedicated login/client-portal endpoint.
class ClientPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function activeUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'password' => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_active_client_role_user_can_log_in_to_the_client_portal(): void
    {
        $user = $this->activeUser();
        $user->assignRole('client');

        $this->postJson('/api/v1/auth/login/client-portal', [
            'email' => $user->email,
            'password' => 'Secret1234!',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.roles.0', 'client');

        $this->assertAuthenticatedAs($user);
    }

    public function test_active_non_client_user_is_rejected_from_the_client_portal_with_a_generic_error(): void
    {
        $admin = $this->activeUser();
        $admin->assignRole('admin');

        $this->postJson('/api/v1/auth/login/client-portal', [
            'email' => $admin->email,
            'password' => 'Secret1234!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_active_roleless_user_is_rejected_from_the_client_portal(): void
    {
        $user = $this->activeUser();

        $this->postJson('/api/v1/auth/login/client-portal', [
            'email' => $user->email,
            'password' => 'Secret1234!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_client_user_is_still_rejected_from_the_client_portal(): void
    {
        $user = $this->activeUser(['is_active' => false]);
        $user->assignRole('client');

        $this->postJson('/api/v1/auth/login/client-portal', [
            'email' => $user->email,
            'password' => 'Secret1234!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }
}
