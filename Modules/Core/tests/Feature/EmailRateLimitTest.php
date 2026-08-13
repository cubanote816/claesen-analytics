<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// CLA-347: the email|ip throttle alone lets an attacker rotating IPs
// brute-force a single account without ever tripping the per-IP limit. This
// covers the email-only limiter added alongside it.
class EmailRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // AccessAnalyticsService notifies super_admins past a failure threshold.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function activeUser(): User
    {
        return UserFactory::new()->create([
            'password' => 'Secret1234!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function test_rotating_ips_still_trips_the_email_only_limiter(): void
    {
        $user = $this->activeUser();

        // 20 failed attempts, each from a distinct IP, so the per-IP limit
        // (5 attempts) never trips on any single IP — only the email-only
        // limiter accumulates across all of them.
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson('/api/v1/auth/login/sport', [
                    'email' => $user->email,
                    'password' => 'WrongPassword!',
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.21'])
            ->postJson('/api/v1/auth/login/sport', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');
    }

    public function test_a_single_ips_five_attempt_limit_still_applies_independently(): void
    {
        $user = $this->activeUser();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/v1/auth/login/sport', [
                    'email' => $user->email,
                    'password' => 'WrongPassword!',
                ])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/v1/auth/login/sport', [
                'email' => $user->email,
                'password' => 'WrongPassword!',
            ])
            ->assertStatus(429);
    }
}
