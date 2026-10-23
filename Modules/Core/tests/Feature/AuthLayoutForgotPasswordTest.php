<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\RequestPasswordReset;
use Modules\Core\Models\User;
use Modules\Core\Notifications\PasswordResetNotification;
use Tests\TestCase;

/**
 * Forgot password on the shared two-panel auth layout (odd/tasks/backoffice-auth-redesign.md,
 * T2: S1–S4, S6, S8). Presentation plus the S6 non-disclosure rule: the confirmation is the
 * same whether or not the account exists. The reset mechanism itself is covered by
 * BackofficePasswordResetTest.
 */
final class AuthLayoutForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function panels(): array
    {
        return [
            'admin panel' => ['/password-reset/request', 'admin'],
            'bertels panel' => ['/bertels/password-reset/request', 'bertels'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function user(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'password' => 'Secret123!',
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $overrides));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panels')]
    public function test_the_form_state_follows_the_design_on_both_panels(string $url, string $panel): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en'])->get($url);

        $response->assertOk();
        $response->assertSeeText('Forgot your password?');
        $response->assertSeeText("Enter the email linked to your account and we'll send you a link to reset it.");
        $response->assertSeeText('Back to sign in');
        $response->assertSeeText('Send reset link');
        $response->assertSeeText('Email address');
        $response->assertSee('type="email"', false);
        $response->assertSee('autocomplete="username"', false);
        $response->assertSee('placeholder="you@company.com"', false);
        $response->assertSee('<span class="cafca-login-req" aria-hidden="true">*</span>', false);
        $response->assertSee('<h2 class="cafca-login-heading__title">Forgot your password?</h2>', false);
        $response->assertSee('href="'.route("filament.{$panel}.auth.login").'"', false);
        $response->assertSee('cafca-login-brand__title', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'exactly one <h1> on the page');
        $response->assertDontSee('class="cafca-login-state"', false);
    }

    public function test_the_dutch_screen_uses_the_dutch_copy(): void
    {
        app()->setLocale('nl');

        $this->withHeaders(['Accept-Language' => 'nl'])->get('/password-reset/request')
            ->assertOk()
            ->assertSeeText('Wachtwoord vergeten?')
            ->assertDontSeeText('Forgot your password?');
    }

    public function test_an_existing_and_an_unknown_address_reach_the_identical_confirmation(): void
    {
        Notification::fake();
        $user = $this->user();

        $existing = Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertSet('sent', true)
            ->assertSeeHtml('role="status"')
            ->assertSeeText('Check your inbox');

        $unknown = Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertSet('sent', true)
            ->assertSeeHtml('role="status"')
            ->assertSeeText('Check your inbox');

        // Same markup once the address is factored out.
        $normalise = static fn (string $html, string $email): string => str_replace($email, 'EMAIL', $html);

        $this->assertSame(
            $this->statusBlock($normalise($existing->html(), $user->email)),
            $this->statusBlock($normalise($unknown->html(), 'nobody@example.com')),
        );

        $unknown->assertSeeHtml('If an account exists for <strong class="cafca-login-email">nobody@example.com</strong>, you\'ll receive a link to reset your password shortly.');
    }

    private function statusBlock(string $html): string
    {
        $start = strpos($html, 'role="status"');
        $this->assertNotFalse($start);

        // Drop Livewire ids/keys so two component instances compare equal.
        return preg_replace('/(wire:id|wire:key|id|x-data|wire:snapshot)="[^"]*"/', '', substr($html, $start, 2500));
    }

    public function test_only_an_existing_account_gets_the_reset_notification(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'nobody@example.com'])->call('request');
        Notification::assertNothingSent();

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $user->email])->call('request');
        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_an_inactive_and_a_microsoft_account_see_the_same_confirmation_and_get_nothing(): void
    {
        Notification::fake();

        foreach ([['is_active' => false], ['microsoft_id' => 'azure-oid-1']] as $overrides) {
            $user = $this->user($overrides);

            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $user->email])
                ->call('request')
                ->assertSet('sent', true)
                ->assertSeeHtml('role="status"');
        }

        Notification::assertNothingSent();
    }

    public function test_the_confirmation_escapes_the_typed_address(): void
    {
        Notification::fake();

        $component = Livewire::test(RequestPasswordReset::class);
        $component->set('sent', true)->set('sentTo', '"><script>alert(1)</script>@x.test');

        $component->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    public function test_the_confirmation_offers_two_ways_out_and_use_a_different_email_returns_to_the_form(): void
    {
        Notification::fake();

        $component = Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertSeeHtml('class="cafca-login-btn cafca-login-btn--primary" href="'.route('filament.admin.auth.login').'"')
            ->assertSeeText('Back to sign in')
            ->assertSeeText('Use a different email')
            ->assertSeeHtml('type="button" wire:click="useDifferentEmail"');

        $component->call('useDifferentEmail')
            ->assertSet('sent', false)
            ->assertDontSeeHtml('role="status"')
            ->assertSeeText('Send reset link')
            ->assertSeeText('Forgot your password?');
    }

    public function test_the_back_link_points_at_the_current_panels_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        Livewire::test(RequestPasswordReset::class)
            ->assertSeeHtml('href="'.route('filament.bertels.auth.login').'"');
    }

    public function test_the_throttle_does_not_depend_on_the_address(): void
    {
        Notification::fake();
        $user = $this->user();

        $component = Livewire::test(RequestPasswordReset::class);

        // Two attempts are allowed; the third is throttled whatever address it carries.
        foreach (['nobody@example.com', $user->email, 'other@example.com'] as $email) {
            $component->set('sent', false)->fillForm(['email' => $email])->call('request');
        }

        $component->assertSet('sent', false);
        Notification::assertSentToTimes($user, PasswordResetNotification::class, 1);
    }
}
