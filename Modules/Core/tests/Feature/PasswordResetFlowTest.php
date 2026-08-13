<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\Core\Notifications\PasswordResetNotification;
use Tests\TestCase;

// CLA-371: reuses activation_code_hash/activation_code_expires_at, the same
// fields ExchangeActivationCodeController uses — see that class + its tests
// in PasswordSetupFlowTest for the pattern this mirrors.
class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'password' => bcrypt('OldSecret123!'),
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_forgot_password_sends_a_notification_for_an_eligible_account(): void
    {
        Notification::fake();
        $user = $this->activeUser();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertSentTo($user, PasswordResetNotification::class);
        $this->assertNotNull($user->fresh()->activation_code_hash);
    }

    public function test_forgot_password_response_is_identical_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_does_not_notify_a_microsoft_only_account(): void
    {
        Notification::fake();
        $user = $this->activeUser(['microsoft_id' => 'azure-oid-123']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertNothingSent();
        $this->assertNull($user->fresh()->activation_code_hash);
    }

    public function test_forgot_password_does_not_notify_an_account_still_mid_activation(): void
    {
        Notification::fake();
        $user = UserFactory::new()->create(['is_active' => true]);
        $user->forceFill(['password' => null, 'password_set_at' => null])->saveQuietly();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_does_not_notify_an_inactive_account(): void
    {
        Notification::fake();
        $user = $this->activeUser(['is_active' => false]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(429);
    }

    private function issueResetCode(User $user): string
    {
        $code = Str::random(64);
        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(60),
        ])->saveQuietly();

        return $code;
    }

    public function test_reset_password_updates_password_revokes_tokens_and_enables_login(): void
    {
        $user = $this->activeUser();
        $staleToken = $user->createToken('old-session')->plainTextToken;
        $code = $this->issueResetCode($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully. You can now log in.');

        $fresh = $user->fresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('BrandNew123!', $fresh->password));
        $this->assertNull($fresh->activation_code_hash);
        $this->assertNull($fresh->activation_code_expires_at);
        $this->assertSame(0, $fresh->tokens()->count());

        $this->withToken($staleToken)->getJson('/api/v1/auth/introspect')->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'BrandNew123!',
        ])->assertOk();
    }

    public function test_reset_password_with_expired_code_is_rejected(): void
    {
        $user = $this->activeUser();
        $code = Str::random(64);
        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->subMinute(),
        ])->saveQuietly();

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertStatus(422);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('OldSecret123!', $user->fresh()->password));
    }

    public function test_reset_password_code_cannot_be_reused(): void
    {
        $user = $this->activeUser();
        $code = $this->issueResetCode($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'AnotherOne456!',
            'password_confirmation' => 'AnotherOne456!',
        ])->assertStatus(422);
    }

    public function test_reset_password_with_invalid_code_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'code' => Str::random(64),
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertStatus(422);
    }

    public function test_reset_password_requires_matching_confirmation(): void
    {
        $user = $this->activeUser();
        $code = $this->issueResetCode($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'Different123!',
        ])->assertJsonValidationErrors('password');
    }

    public function test_reset_password_rejects_a_code_issued_to_a_microsoft_account(): void
    {
        // Defense in depth: sendLink() never issues a code for a Microsoft
        // account, but reset() checks microsoft_id independently too, in case
        // a code was ever set some other way (e.g. directly via Filament).
        $user = $this->activeUser(['microsoft_id' => 'azure-oid-456']);
        $code = $this->issueResetCode($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'code' => $code,
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertStatus(422);
    }
}
