<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\Login;
use Modules\Core\Models\User;
use Modules\Core\Notifications\VerifyEmailAuthenticationWithBrand;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-648 — the Login page's second step must actually ask for, and verify, the MFA code.
 *
 * The page used to carry a hand copy of Filament's `authenticate()`. After the Filament 5.10.3
 * upgrade the copy stored the challenge state in the old shape, so Filament rendered the
 * challenge form WITHOUT a code input and the copy then validated that empty form and signed
 * the user in. These tests pin the observable contract through the real Login page: the code
 * input is rendered, a missing or wrong code leaves the user a guest, a right code signs in,
 * and a client is refused before any code is sent.
 */
final class LoginMultiFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret123!';

    private const EMAIL_CODE_FIELD = 'data.multiFactor.email_code.code';

    private const APP_CODE_FIELD = 'data.multiFactor.app.code';

    protected function setUp(): void
    {
        parent::setUp();

        config(['core.panel_mfa_enforced' => true]);
        app()->setLocale('en');
        $this->withoutVite();

        foreach (['super_admin', 'admin', 'viewer', 'client'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_email_challenge_renders_the_code_input(): void
    {
        Notification::fake();

        $user = $this->emailMfaUser();

        $this->submitPassword($user)
            ->assertNoRedirect()
            ->assertSeeHtml('id="multiFactorChallengeForm.email_code.code"');

        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmailAuthenticationWithBrand::class);
    }

    public function test_the_email_challenge_rejects_a_submission_without_a_code(): void
    {
        Notification::fake();

        $user = $this->emailMfaUser();

        $this->submitPassword($user)
            ->call('authenticate')
            ->assertHasErrors([self::EMAIL_CODE_FIELD => 'required'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_the_email_challenge_rejects_a_wrong_code(): void
    {
        Notification::fake();

        $user = $this->emailMfaUser();

        $page = $this->submitPassword($user);

        $page->set(self::EMAIL_CODE_FIELD, $this->aCodeOtherThan($this->emailedCode($user)))
            ->call('authenticate')
            ->assertHasErrors([self::EMAIL_CODE_FIELD])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_the_email_challenge_signs_in_with_the_emailed_code(): void
    {
        Notification::fake();

        $user = $this->emailMfaUser();

        $page = $this->submitPassword($user);

        $code = Notification::sent($user, VerifyEmailAuthenticationWithBrand::class)->first()->code;

        $page->set(self::EMAIL_CODE_FIELD, $code)
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_app_challenge_renders_the_code_input_and_rejects_a_missing_or_wrong_code(): void
    {
        $user = $this->appMfaUser();

        $page = $this->submitPassword($user)
            ->assertNoRedirect()
            ->assertSeeHtml('id="multiFactorChallengeForm.app.code"');

        $page->call('authenticate')
            ->assertHasErrors([self::APP_CODE_FIELD => 'required'])
            ->assertNoRedirect();
        $this->assertGuest();

        $page->set(self::APP_CODE_FIELD, $this->aCodeOtherThan($this->currentAppCode($user)))
            ->call('authenticate')
            ->assertHasErrors([self::APP_CODE_FIELD])
            ->assertNoRedirect();
        $this->assertGuest();
    }

    /** The code the challenge just sent to this user. */
    private function emailedCode(User $user): string
    {
        return Notification::sent($user, VerifyEmailAuthenticationWithBrand::class)->first()->code;
    }

    /**
     * A six-digit code that is guaranteed not to be the valid one.
     *
     * A hardcoded literal would occasionally collide with the real code and turn this
     * into a flaky false pass, so the last digit is shifted away from it instead.
     */
    private function aCodeOtherThan(string $validCode): string
    {
        return substr($validCode, 0, 5).((((int) $validCode[5]) + 1) % 10);
    }

    public function test_the_app_challenge_signs_in_with_a_valid_code(): void
    {
        $user = $this->appMfaUser();

        $page = $this->submitPassword($user);

        $page->set(self::APP_CODE_FIELD, $this->currentAppCode($user))
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_client_is_refused_at_the_first_step_and_no_code_is_sent(): void
    {
        Notification::fake();

        $client = $this->emailMfaUser('client');

        Livewire::test(Login::class)
            ->fillForm(['email' => $client->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertNoRedirect()
            ->assertDontSeeHtml('id="multiFactorChallengeForm.email_code.code"');

        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_remember_me_still_lasts_thirty_days_after_the_challenge(): void
    {
        Notification::fake();

        $user = $this->emailMfaUser();

        $page = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD, 'remember' => true])
            ->call('authenticate');

        $code = Notification::sent($user, VerifyEmailAuthenticationWithBrand::class)->first()->code;

        $page->set(self::EMAIL_CODE_FIELD, $code)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);

        $recaller = collect(cookie()->getQueuedCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'the remember-me cookie was queued');
        $this->assertEqualsWithDelta(
            now()->addMinutes(60 * 24 * 30)->getTimestamp(),
            $recaller->getExpiresTime(),
            120,
        );
    }

    private function emailMfaUser(string $role = 'super_admin'): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => self::PASSWORD,
            'password_set_at' => now(),
            'has_email_authentication' => true,
        ]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function appMfaUser(): User
    {
        $user = $this->emailMfaUser();
        $user->forceFill([
            'has_email_authentication' => false,
            'app_authentication_secret' => app(AppAuthentication::class)->generateSecret(),
        ])->save();

        return $user->refresh();
    }

    private function currentAppCode(User $user): string
    {
        return AppAuthentication::make()->getCurrentCode($user);
    }

    /** Fills the password step and submits it, leaving the page on the challenge step. */
    private function submitPassword(User $user): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate');
    }
}
