<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\ResetPassword;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * Reset password on the shared two-panel auth layout (odd/tasks/backoffice-auth-redesign.md,
 * T3: S1–S4, S7, S8). The reset mechanism itself is covered by BackofficePasswordResetTest.
 *
 * Password policy of this page: Filament's PasswordRule::default() with no Password::defaults()
 * override anywhere, i.e. min 8 and nothing else (same min:8 as the API requests).
 */
final class AuthLayoutResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function panels(): array
    {
        return ['admin panel' => ['admin'], 'bertels panel' => ['bertels']];
    }

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array{0: User, 1: string} */
    private function userWithCode(): array
    {
        $user = User::factory()->create([
            'password' => 'OldSecret123!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $code = Str::random(64);
        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(30),
        ])->saveQuietly();

        return [$user, $code];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panels')]
    public function test_a_valid_reset_url_renders_the_design_on_both_panels(string $panel): void
    {
        [$user, $code] = $this->userWithCode();
        $url = Filament::getPanel($panel)->getResetPasswordUrl($code, $user);

        $response = $this->withHeaders(['Accept-Language' => 'en'])->get($url);

        $response->assertOk();
        $response->assertSeeText('Set a new password');
        $response->assertSeeText("Choose a password you haven't used before. You'll use it to sign in from now on.");
        $response->assertSeeText('New password');
        $response->assertSeeText('Confirm new password');
        $response->assertSeeText('At least 8 characters.');
        $response->assertSeeText('Update password');
        $response->assertSee('minlength="8"', false);
        $response->assertSee('aria-describedby="reset-password-hint"', false);
        $response->assertSee('id="reset-password-hint"', false);
        $response->assertSee('autocomplete="new-password"', false);
        $response->assertSee('aria-label="Show passwords"', false);
        $this->assertSame(2, substr_count($response->getContent(), '<span class="cafca-login-req" aria-hidden="true">*</span>'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'exactly one <h1> on the page');
        $response->assertSee('<h2 class="cafca-login-heading__title">Set a new password</h2>', false);
        $response->assertSee('cafca-login-brand__title', false);

        // The token and email of the signed URL still reach the component, as before.
        $this->assertStringContainsString($code, html_entity_decode($response->getContent()));
        $this->assertStringContainsString($user->email, html_entity_decode($response->getContent()));
    }

    public function test_mismatched_passwords_show_the_alert_and_leave_the_password_unchanged(): void
    {
        [$user, $code] = $this->userWithCode();
        $before = $user->password;

        $component = Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'BrandNew123!', 'passwordConfirmation' => 'Different123!'])
            ->call('resetPassword')
            ->assertHasErrors(['password'])
            ->assertSet('done', false)
            ->assertSeeHtml('<span class="cafca-login-field-error" role="alert">The two passwords don&#039;t match.</span>');

        $this->assertSame(1, substr_count($component->html(), 'role="alert"'));
        $this->assertSame($before, $user->fresh()->password);
        $this->assertNotNull($user->fresh()->activation_code_hash);
    }

    public function test_the_policy_is_min_eight_and_nothing_else(): void
    {
        [$user, $code] = $this->userWithCode();

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'abc1234', 'passwordConfirmation' => 'abc1234'])
            ->call('resetPassword')
            ->assertHasErrors(['password'])
            ->assertSet('done', false);

        $this->assertTrue(Hash::check('OldSecret123!', $user->fresh()->password));

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'abcdefgh', 'passwordConfirmation' => 'abcdefgh'])
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertSet('done', true);
    }

    public function test_a_valid_reset_updates_the_password_and_shows_the_success_state(): void
    {
        [$user, $code] = $this->userWithCode();

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'BrandNew123!', 'passwordConfirmation' => 'BrandNew123!'])
            ->call('resetPassword')
            ->assertSet('done', true)
            ->assertSeeHtml('role="status"')
            ->assertSeeText('Password updated')
            ->assertSeeText('Your password has been changed. Sign in with your new password to continue.')
            ->assertSeeText('Go to sign in')
            ->assertSeeHtml('class="cafca-login-btn cafca-login-btn--primary" href="'.route('filament.admin.auth.login').'"')
            ->assertDontSeeHtml('fi-sc-form');

        $this->assertTrue(Hash::check('BrandNew123!', $user->fresh()->password));
    }

    public function test_the_success_link_points_at_the_current_panels_login(): void
    {
        [$user, $code] = $this->userWithCode();
        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'BrandNew123!', 'passwordConfirmation' => 'BrandNew123!'])
            ->call('resetPassword')
            ->assertSeeHtml('href="'.route('filament.bertels.auth.login').'"');
    }

    public function test_an_unknown_or_expired_token_still_fails_and_changes_nothing(): void
    {
        [$user, $code] = $this->userWithCode();

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => 'not-a-real-code'])
            ->fillForm(['password' => 'BrandNew123!', 'passwordConfirmation' => 'BrandNew123!'])
            ->call('resetPassword')
            ->assertSet('done', false)
            ->assertDontSeeHtml('role="status"');

        $user->forceFill(['activation_code_expires_at' => now()->subMinute()])->saveQuietly();

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $code])
            ->fillForm(['password' => 'BrandNew123!', 'passwordConfirmation' => 'BrandNew123!'])
            ->call('resetPassword')
            ->assertSet('done', false);

        $this->assertTrue(Hash::check('OldSecret123!', $user->fresh()->password));
    }

    public function test_the_dutch_screen_uses_the_dutch_copy(): void
    {
        app()->setLocale('nl');
        [$user, $code] = $this->userWithCode();

        $this->withHeaders(['Accept-Language' => 'nl'])
            ->get(Filament::getPanel('admin')->getResetPasswordUrl($code, $user))
            ->assertOk()
            ->assertSeeText('Stel een nieuw wachtwoord in')
            ->assertSeeText('Minstens 8 tekens.')
            ->assertDontSeeText('Set a new password');
    }
}
