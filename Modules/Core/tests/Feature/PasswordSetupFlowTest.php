<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordSetupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin',     'guard_name' => 'web']);
    }

    // User fully set up (existing account with password).
    private function fullUser(): User
    {
        return UserFactory::new()->create([
            'password'        => bcrypt('Secret1234!'),
            'password_set_at' => now()->subDay(),
        ]);
    }

    // User provisioned by admin — no password yet.
    private function pendingUser(): User
    {
        $user = UserFactory::new()->create([
            'password'        => null,
            'password_set_at' => null,
        ]);
        // forceFill needed to bypass the hashed cast setting null
        $user->forceFill(['password' => null])->saveQuietly();
        return $user->fresh();
    }

    private function issueActivationCode(User $user): string
    {
        $code = Str::random(64);
        $user->forceFill([
            'activation_code_hash'       => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(10),
        ])->saveQuietly();
        return $code;
    }

    // -----------------------------------------------------------------------
    // 9. Existing account with password: hasCompletedPasswordSetup() = true
    // -----------------------------------------------------------------------
    public function test_existing_account_with_password_is_fully_set_up(): void
    {
        $user = $this->fullUser();
        $this->assertTrue($user->hasCompletedPasswordSetup());
    }

    // -----------------------------------------------------------------------
    // 10. Local login with password=null → 401 generic (Core + Safety)
    // -----------------------------------------------------------------------
    public function test_core_login_rejects_account_with_null_password(): void
    {
        $pending = $this->pendingUser();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $pending->email,
            'password' => 'anything',
        ])->assertStatus(422); // ValidationException returns 422
    }

    public function test_safety_login_rejects_account_with_null_password(): void
    {
        $pending = $this->pendingUser();

        $this->postJson('/api/v1/login', [
            'email'    => $pending->email,
            'password' => 'anything',
        ])->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // 11. Activation code issued for pending account — NOT a full bearer token
    // -----------------------------------------------------------------------
    public function test_activation_code_stored_hashed_not_plaintext(): void
    {
        $pending = $this->pendingUser();
        $code    = $this->issueActivationCode($pending);
        $pending->refresh();

        $this->assertNotNull($pending->activation_code_hash);
        $this->assertNotSame($code, $pending->activation_code_hash);
        $this->assertSame(hash('sha256', $code), $pending->activation_code_hash);
        $this->assertNull($pending->password);
    }

    // -----------------------------------------------------------------------
    // 12. Expired code → POST /api/v1/auth/activate → 422
    // -----------------------------------------------------------------------
    public function test_expired_activation_code_is_rejected(): void
    {
        $pending = $this->pendingUser();
        $code    = Str::random(64);

        $pending->forceFill([
            'activation_code_hash'       => hash('sha256', $code),
            'activation_code_expires_at' => now()->subMinute(),   // already expired
        ])->saveQuietly();

        $this->postJson('/api/v1/auth/activate', ['code' => $code])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // 13. Code used twice → second exchange rejected
    // -----------------------------------------------------------------------
    public function test_activation_code_cannot_be_used_twice(): void
    {
        $pending = $this->pendingUser();
        $code    = $this->issueActivationCode($pending);

        // First exchange succeeds
        $this->postJson('/api/v1/auth/activate', ['code' => $code])
            ->assertStatus(200)
            ->assertJsonStructure(['setup_token', 'expires_in']);

        // Second exchange with the same code must fail
        $this->postJson('/api/v1/auth/activate', ['code' => $code])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // 13b. Invalid / random code → 422
    // -----------------------------------------------------------------------
    public function test_invalid_activation_code_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/activate', ['code' => Str::random(64)])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // 13c. Rate limit: 6th attempt within 1 minute → 429
    // -----------------------------------------------------------------------
    public function test_activation_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/activate', ['code' => Str::random(64)]);
        }

        $this->postJson('/api/v1/auth/activate', ['code' => Str::random(64)])
            ->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // 15. Successful setup saves password and password_set_at; login works after
    // -----------------------------------------------------------------------
    public function test_setup_via_token_activates_account_and_enables_local_login(): void
    {
        $pending   = $this->pendingUser();
        $code      = $this->issueActivationCode($pending);

        $exchange = $this->postJson('/api/v1/auth/activate', ['code' => $code])
            ->assertStatus(200);

        $setupToken = $exchange->json('setup_token');

        $this->withToken($setupToken)
            ->postJson('/api/v1/auth/setup-password', [
                'password'              => 'NewSecure123!',
                'password_confirmation' => 'NewSecure123!',
            ])
            ->assertStatus(200);

        $pending->refresh();
        $this->assertNotNull($pending->password);
        $this->assertNotNull($pending->password_set_at);
        $this->assertTrue($pending->hasCompletedPasswordSetup());

        // Local login now works
        $this->postJson('/api/v1/auth/login', [
            'email'    => $pending->email,
            'password' => 'NewSecure123!',
        ])->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // 16. setup:password token is blocked from Core business endpoints
    // -----------------------------------------------------------------------
    public function test_setup_token_blocked_from_core_business_endpoints(): void
    {
        $pending    = $this->pendingUser();
        $code       = $this->issueActivationCode($pending);
        $exchange   = $this->postJson('/api/v1/auth/activate', ['code' => $code]);
        $setupToken = $exchange->json('setup_token');

        // Use introspect (Core-only, no Safety duplicate) to confirm EnsurePasswordIsSet blocks.
        $this->withToken($setupToken)
            ->getJson('/api/v1/auth/introspect')
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // 17. setup:password token is blocked from Safety endpoints
    // -----------------------------------------------------------------------
    public function test_setup_token_blocked_from_safety_endpoints(): void
    {
        $pending = $this->pendingUser();
        $pending->assignRole('project_manager');

        $code       = $this->issueActivationCode($pending);
        $exchange   = $this->postJson('/api/v1/auth/activate', ['code' => $code]);
        $setupToken = $exchange->json('setup_token');

        // setup:password token doesn't have role:safety-access ability → 403
        $this->withToken($setupToken)
            ->getJson('/api/v1/safety/inspections')
            ->assertStatus(403);
    }
}
