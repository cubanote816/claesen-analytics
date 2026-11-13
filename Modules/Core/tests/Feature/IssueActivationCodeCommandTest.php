<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-653: an account created from the Users resource starts with no password
 * (CreateUser stores password = null, password_set_at = null) and nothing issued
 * the activation code it needs to set one. `sendResetLink()` cannot: it refuses
 * every account that has not completed setup, which is exactly this one.
 *
 * These tests pin who may be handed a code, and that the printed code really
 * sets the password — a code nobody can consume would pass a weaker test.
 */
final class IssueActivationCodeCommandTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'core:issue-activation-code';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    /** Provisioned by an admin, never signed in: no password, no Microsoft link. */
    private function pendingUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'password' => null,
            'password_set_at' => null,
            'microsoft_id' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function runFor(User $user): string
    {
        Artisan::call(self::COMMAND, ['email' => $user->email]);

        return Artisan::output();
    }

    public function test_it_issues_a_code_an_account_without_a_password_can_actually_use(): void
    {
        $user = $this->pendingUser();

        $exitCode = Artisan::call(self::COMMAND, ['email' => $user->email]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        preg_match('/\b([A-Za-z0-9]{64})\b/', $output, $matches);
        $this->assertNotEmpty($matches, "The command must print the code. Output:\n{$output}");
        $code = $matches[1];

        $user->refresh();
        $this->assertSame(hash('sha256', $code), $user->activation_code_hash);
        $this->assertNotNull($user->activation_code_expires_at);
        $this->assertTrue($user->activation_code_expires_at->isFuture());

        // The point of the code: it sets the first password.
        app(PasswordResetService::class)->resetPassword($code, 'BrandNewSecret123!');

        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertNotNull($user->password_set_at);
        $this->assertTrue($user->hasCompletedPasswordSetup());
        $this->assertNull($user->activation_code_hash);
    }

    public function test_it_prints_the_reset_url_for_the_chosen_panel(): void
    {
        $user = $this->pendingUser();

        $output = $this->runFor($user);

        $this->assertStringContainsString('password-reset', $output);
    }

    public function test_it_refuses_a_microsoft_account(): void
    {
        $user = $this->pendingUser(['microsoft_id' => 'azure-object-id']);

        $exitCode = Artisan::call(self::COMMAND, ['email' => $user->email]);

        $this->assertSame(1, $exitCode);
        $this->assertNull($user->refresh()->activation_code_hash);
    }

    public function test_it_refuses_an_account_that_already_has_a_password(): void
    {
        $user = $this->pendingUser([
            'password' => bcrypt('Secret1234!'),
            'password_set_at' => now()->subDay(),
        ]);

        $exitCode = Artisan::call(self::COMMAND, ['email' => $user->email]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertNull($user->refresh()->activation_code_hash);
        $this->assertStringContainsStringIgnoringCase('reset', $output);
    }

    public function test_it_refuses_an_inactive_account(): void
    {
        $user = $this->pendingUser(['is_active' => false]);

        $exitCode = Artisan::call(self::COMMAND, ['email' => $user->email]);

        $this->assertSame(1, $exitCode);
        $this->assertNull($user->refresh()->activation_code_hash);
    }

    public function test_it_refuses_an_unknown_address_without_touching_anything(): void
    {
        $exitCode = Artisan::call(self::COMMAND, ['email' => 'nobody@example.test']);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, User::query()->whereNotNull('activation_code_hash')->count());
    }

    public function test_it_matches_the_address_the_way_the_panel_stores_it(): void
    {
        $user = $this->pendingUser(['email' => 'mixed.case@claesen-verlichting.be']);

        $exitCode = Artisan::call(self::COMMAND, ['email' => ' MIXED.CASE@Claesen-Verlichting.BE ']);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($user->refresh()->activation_code_hash);
    }

    // CLA-653 verify: the printed link follows --panel; the default stays on the admin panel.
    public function test_it_points_the_link_at_the_bertels_panel_when_asked(): void
    {
        $user = $this->pendingUser();

        $this->assertSame(0, Artisan::call(self::COMMAND, ['email' => $user->email, '--panel' => 'bertels']));
        $this->assertStringContainsString('/bertels/password-reset', Artisan::output());

        $this->assertSame(0, Artisan::call(self::COMMAND, ['email' => $user->email]));
        $this->assertStringNotContainsString('/bertels/', Artisan::output());
    }

    // CLA-653 verify: an unknown panel is refused before any code is issued, so an
    // already-issued code stays valid.
    public function test_an_unknown_panel_is_refused_and_keeps_the_existing_code(): void
    {
        $user = $this->pendingUser();
        app(PasswordResetService::class)->issueActivationCode($user);
        $before = $user->fresh()->getRawOriginal();

        $exitCode = Artisan::call(self::COMMAND, ['email' => $user->email, '--panel' => 'missing']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown panel [missing]. Available: admin, bertels.', Artisan::output());
        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    // CLA-653 verify: a refused account keeps its current code untouched.
    public function test_a_refused_account_keeps_its_existing_code(): void
    {
        $user = $this->pendingUser();
        app(PasswordResetService::class)->issueActivationCode($user);
        $user->forceFill(['is_active' => false])->saveQuietly();
        $before = $user->fresh()->getRawOriginal();

        $this->assertSame(1, Artisan::call(self::COMMAND, ['email' => $user->email]));
        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    // CLA-653 verify: two stored addresses that normalize to the same one are ambiguous;
    // the command refuses instead of picking one of them.
    public function test_it_refuses_an_address_that_matches_more_than_one_account(): void
    {
        $a = $this->pendingUser(['email' => ' person@example.test']);
        $b = $this->pendingUser(['email' => 'Person@example.test']);

        $exitCode = Artisan::call(self::COMMAND, ['email' => 'person@example.test']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('more than one account', Artisan::output());
        $this->assertNull($a->fresh()->activation_code_hash);
        $this->assertNull($b->fresh()->activation_code_hash);
    }
}
