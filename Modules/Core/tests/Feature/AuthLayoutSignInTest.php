<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\Auth\Login;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The shared two-panel auth layout and the Sign in screen on top of it
 * (odd/tasks/backoffice-auth-redesign.md, T1: S1–S5, S8).
 *
 * Presentation only: the auth logic itself is covered by LandingOnTheWrongPanelTest,
 * PanelAccessTest, MicrosoftAuthRoleGateTest and the MFA tests. What is pinned here
 * is what the screen shows, where its links point, and that the restyle did not
 * change what a failed or successful login does.
 */
final class AuthLayoutSignInTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN_COPY = 'Cash-flow guardian & AI auditor for every project';

    private const CREDENTIALS_ERROR = 'These credentials do not match our records.';

    /** @return list<array{0: string, 1: string}> */
    public static function panelLogins(): array
    {
        return [
            'admin panel' => ['/login', 'admin'],
            'bertels panel' => ['/bertels/login', 'bertels'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        foreach (['super_admin', 'admin', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function claesenUser(string $password = 'Secret123!'): User
    {
        $user = User::factory()->create([
            'password' => $password,
            'is_active' => true,
            'has_email_authentication' => false,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panelLogins')]
    public function test_both_panels_render_the_same_dual_brand_sign_in_screen(string $url, string $panel): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'en'])->get($url);

        $response->assertOk();

        foreach ([
            'Two trades, one shared backoffice',
            'Sports lighting and electrical installations — one shared backoffice for both companies.',
            'Outdoor & stadium sports lighting, every level',
            'Electrical installations & home automation for homes, businesses and industry',
            'Sign in',
            'Welcome back. Enter your details to continue.',
            'Remember me for 30 days',
            'Forgot password?',
            'Sign in with Microsoft',
            '© '.now()->year.' · All rights reserved',
        ] as $text) {
            $response->assertSeeText($text);
        }

        // Brand logos: the existing transparent assets, with descriptive alt text.
        $response->assertSee('img/claesen-logo-login.png', false);
        $response->assertSee('img/bertels-logo-dark.png', false);
        $response->assertSee('alt="Claesen Outdoor Lighting"', false);
        $response->assertSee('alt="Electro Bertels"', false);

        // Titles: the brand panel owns the <h1>, the form panel the <h2>.
        $response->assertSee('<h1 class="cafca-login-brand__title">', false);
        $response->assertSee('<h2 class="cafca-login-heading__title">Sign in</h2>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'), 'exactly one <h1> on the page');

        // Fonts load on the auth page through the panel's own (Bunny) provider.
        $response->assertSee('family=sora:500,600,700|inter:400,500,600', false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panelLogins')]
    public function test_the_forbidden_brand_line_appears_nowhere(string $url, string $panel): void
    {
        $this->assertStringNotContainsString(self::FORBIDDEN_COPY, $this->get($url)->getContent());
        $this->assertStringNotContainsString('Cash-flow guardian', $this->get($url)->getContent());

        foreach (['en', 'nl'] as $locale) {
            $lines = trans('core::auth', [], $locale);

            foreach (array_filter($lines, 'is_string') as $line) {
                $this->assertStringNotContainsString('Cash-flow guardian', $line);
            }

            $this->assertArrayNotHasKey('brand_bullet_shared', $lines);
        }
    }

    public function test_the_dutch_screen_uses_the_dutch_copy(): void
    {
        app()->setLocale('nl');

        $response = $this->withHeaders(['Accept-Language' => 'nl'])->get('/login');

        $response->assertOk();
        $response->assertSeeText('Twee vakgebieden, één gedeeld backoffice');
        $response->assertSeeText('Aanmelden met Microsoft');
        $response->assertSeeText('Wachtwoord vergeten?');
        $response->assertDontSeeText('Two trades, one shared backoffice');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('panelLogins')]
    public function test_the_links_point_at_the_existing_routes(string $url, string $panel): void
    {
        $response = $this->get($url);

        // Existing Microsoft SSO flow (MicrosoftAuthController), not a new route.
        $response->assertSee('href="'.route('auth.microsoft.redirect', ['source' => 'filament']).'"', false);

        // That panel's own password-reset request route.
        $resetUrl = route("filament.{$panel}.auth.password-reset.request");
        $response->assertSee('class="cafca-login-link" href="'.$resetUrl.'"', false);
    }

    public function test_the_email_field_follows_the_design(): void
    {
        $response = $this->get('/login');

        $response->assertSee('type="email"', false);
        $response->assertSee('autocomplete="username"', false);
        $response->assertSee('placeholder="you@company.com"', false);

        // Required asterisks are decoration for assistive technology.
        $this->assertSame(2, substr_count($response->getContent(), '<span class="cafca-login-req" aria-hidden="true">*</span>'));
        $response->assertDontSee('fi-fo-field-label-required-mark', false);

        // Show/hide toggle keeps Filament's accessible names.
        $response->assertSee('aria-label="Show password"', false);
        $response->assertSee('aria-label="Hide password"', false);
    }

    public function test_a_failed_login_shows_the_real_error_once_as_an_alert_above_the_fields(): void
    {
        $user = $this->claesenUser();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertSee(self::CREDENTIALS_ERROR)
            ->assertSeeHtml('role="alert"');

        $this->assertGuest();

        $html = $component->html();

        // The notice carries the real validation error, and sits above the fields.
        $this->assertMatchesRegularExpression(
            '/<div class="cafca-login-alert" role="alert">.*?<span>'.preg_quote(self::CREDENTIALS_ERROR, '/').'<\/span>/s',
            $html,
        );
        $this->assertLessThan(strpos($html, 'id="form.email"'), strpos($html, 'role="alert"'));

        // ... and is not repeated under the email input: the field's own copy is
        // hidden by the stylesheet, so the only visible text is the notice. Both are
        // pinned: the hiding hook exists on the field, the rule exists in the theme.
        $this->assertStringContainsString('cafca-login-field--email', $html);
        $this->get('/login')->assertSee('.cafca-login-field--email [data-validation-error]', false);
    }

    public function test_a_clean_screen_has_no_alert(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)->assertDontSeeHtml('role="alert"');
    }

    public function test_a_successful_login_still_works_and_remembers_for_thirty_days(): void
    {
        $user = $this->claesenUser();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'Secret123!', 'remember' => true])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);

        // "Remember me for 30 days" is literally true: the queued remember cookie lives
        // 30 × 24h (minutes, not calendar days, so the DST change this week is irrelevant).
        $recaller = collect(cookie()->getQueuedCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($recaller, 'the remember-me cookie was queued');
        $this->assertEqualsWithDelta(
            now()->addMinutes(60 * 24 * 30)->getTimestamp(),
            $recaller->getExpiresTime(),
            120,
        );
    }

    public function test_the_mfa_challenge_step_renders_inside_the_layout(): void
    {
        Notification::fake();

        $user = $this->claesenUser();
        $user->forceFill(['has_email_authentication' => true])->saveQuietly();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'Secret123!'])
            ->call('authenticate')
            ->assertNoRedirect();

        // Still a pending challenge, not a login.
        $this->assertGuest();
        $this->assertNotEmpty($component->get('userUndertakingMultiFactorAuthentication'));

        // The challenge uses the same <h2> heading block as the sign-in form, with
        // Filament's own challenge copy, and shows no credentials notice.
        $component->assertSeeHtml('<h2 class="cafca-login-heading__title">')
            ->assertDontSee('Welcome back. Enter your details to continue.')
            ->assertDontSeeHtml('role="alert"');
    }

    public function test_the_rate_limit_still_applies(): void
    {
        $user = $this->claesenUser();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'wrong-password']);

        foreach (range(1, 5) as $attempt) {
            $component->call('authenticate');
        }

        // The sixth attempt is throttled before any credential check — even the right password.
        $component->fillForm(['email' => $user->email, 'password' => 'Secret123!'])
            ->call('authenticate')
            ->assertNoRedirect();

        $this->assertGuest();
    }
}
