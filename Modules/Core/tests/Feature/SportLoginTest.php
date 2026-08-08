<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// CLA-363: closes the same login/spa role gap CLA-344 closed for Client Portal,
// now for the dedicated login/sport endpoint. Safety already had its own gated
// login (Modules\Safety\Http\Controllers\AuthController::login), so it never
// needed a Core-side counterpart — no login/safety session-login test here.
class SportLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client', 'technician', 'project_manager', 'admin', 'super_admin'] as $role) {
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

    private function assertLoginAccepted(string $endpoint, User $user): void
    {
        $this->postJson($endpoint, [
            'email' => $user->email,
            'password' => 'Secret1234!',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
        $this->app['auth']->guard('web')->logout(); // each loop iteration below logs in a different user
    }

    private function assertLoginRejected(string $endpoint, User $user): void
    {
        $this->postJson($endpoint, [
            'email' => $user->email,
            'password' => 'Secret1234!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_sport_accepts_technician_project_manager_admin_and_super_admin(): void
    {
        foreach (['technician', 'project_manager', 'admin', 'super_admin'] as $role) {
            $user = $this->activeUser();
            $user->assignRole($role);

            $this->assertLoginAccepted('/api/v1/auth/login/sport', $user);
        }
    }

    public function test_login_sport_rejects_client_and_roleless_users(): void
    {
        $client = $this->activeUser();
        $client->assignRole('client');

        $roleless = $this->activeUser();

        $this->assertLoginRejected('/api/v1/auth/login/sport', $client);
        $this->assertLoginRejected('/api/v1/auth/login/sport', $roleless);
    }

    public function test_inactive_project_manager_is_still_rejected_from_sport(): void
    {
        $user = $this->activeUser(['is_active' => false]);
        $user->assignRole('project_manager');

        $this->assertLoginRejected('/api/v1/auth/login/sport', $user);
    }
}
