<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Core\Support\AuthenticatedVia;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-704 T2 (S6): Microsoft sign-in per panel. A Bertels-organization user always
 * lands on /bertels and a Claesen user on the Claesen panel; the panel carried by the
 * button is a strictly allowlisted hint, never a redirect target built from input.
 */
final class MicrosoftPanelSignInTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private Organization $bertels;

    protected function setUp(): void
    {
        parent::setUp();

        config(['core.panel_mfa_enforced' => true]);
        app()->setLocale('en');
        $this->withoutVite();

        foreach (['super_admin', 'admin', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bertels = $this->bertelsFixtureOrganization(['slug' => 'electro-bertels', 'name' => 'Electro Bertels']);
        $this->bertelsFixtureSite($this->bertels, ['key' => 'electro-bertels']);
    }

    // ------------------------------------------------------------ button

    public function test_the_bertels_login_renders_the_microsoft_button_with_the_bertels_panel(): void
    {
        $href = $this->buttonHref($this->get('/bertels/login')->assertOk());

        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        $this->assertSame('filament', $query['source']);
        $this->assertSame('bertels', $query['panel']);
    }

    public function test_the_claesen_login_renders_the_microsoft_button_as_today(): void
    {
        $href = $this->buttonHref($this->get('/login')->assertOk());

        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        $this->assertSame(['source' => 'filament'], $query);
    }

    public function test_the_redirect_remembers_an_allowlisted_panel(): void
    {
        $this->fakeSocialiteRedirect();

        $this->get(route('auth.microsoft.redirect', ['source' => 'filament', 'panel' => 'bertels']))
            ->assertRedirect('https://login.example.test/authorize');

        $this->assertSame('bertels', session('auth_panel'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function forgedPanels(): array
    {
        return [
            'unknown id' => ['nope'],
            'absolute url' => ['https://evil.example/phish'],
            'protocol-relative url' => ['//evil.example'],
            'array' => [['bertels']],
        ];
    }

    #[DataProvider('forgedPanels')]
    public function test_a_forged_panel_is_not_remembered(mixed $panel): void
    {
        $this->fakeSocialiteRedirect();

        $this->get(route('auth.microsoft.redirect', ['source' => 'filament', 'panel' => $panel]))
            ->assertRedirect('https://login.example.test/authorize');

        $this->assertNull(session('auth_panel'));
    }

    // ---------------------------------------------------------- landing

    public function test_a_bertels_user_signing_in_from_the_bertels_button_lands_on_the_bertels_panel(): void
    {
        $user = $this->microsoftAccount($this->bertels, 'admin');

        $session = $this->signInWithMicrosoft($user, 'bertels', url('/bertels'));

        $user->refresh();
        $this->assertSame('azure-oid-1', $user->microsoft_id);
        $this->assertSame(['admin'], $user->getRoleNames()->all());
        $this->assertTrue(AuthenticatedVia::isMicrosoft(session()->driver()));
        $session->get('/bertels')->assertOk();
    }

    public function test_a_bertels_viewer_keeps_the_role_and_reaches_the_panel_without_app_mfa_setup(): void
    {
        $user = $this->microsoftAccount($this->bertels, 'viewer');

        $session = $this->signInWithMicrosoft($user, 'bertels', url('/bertels'));

        $this->assertSame(['viewer'], $user->refresh()->getRoleNames()->all());
        $session->get('/bertels')->assertOk();
    }

    public function test_a_bertels_user_using_the_claesen_button_still_lands_on_the_bertels_panel(): void
    {
        $user = $this->microsoftAccount($this->bertels, 'viewer');

        $session = $this->signInWithMicrosoft($user, null, url('/bertels'));

        $session->get('/bertels')->assertOk();
    }

    public function test_a_bertels_user_is_not_sent_to_the_claesen_panel_by_a_forged_panel_value(): void
    {
        $user = $this->microsoftAccount($this->bertels, 'viewer');

        $this->signInWithMicrosoft($user, 'admin', url('/bertels'));
    }

    public function test_a_claesen_user_using_the_bertels_button_lands_on_the_claesen_panel(): void
    {
        $user = $this->microsoftAccount(null, 'admin', ['organization_id' => Organization::claesenId()]);

        $session = $this->signInWithMicrosoft($user, 'bertels', url('/'));

        $session->get('/')->assertOk();
        $session->get('/bertels')->assertForbidden();
    }

    public function test_a_claesen_user_who_first_opened_bertels_is_not_returned_there_after_microsoft_sign_in(): void
    {
        // Visiting /bertels unauthenticated stores it as the intended URL; this user cannot open it.
        $user = $this->microsoftAccount(null, 'admin', ['organization_id' => Organization::claesenId()]);

        $session = $this->signInWithMicrosoft($user, 'bertels', url('/'), intended: url('/bertels'));

        $session->get('/')->assertOk();
    }

    public function test_a_claesen_user_keeps_an_intended_claesen_url(): void
    {
        $user = $this->microsoftAccount(null, 'admin', ['organization_id' => Organization::claesenId()]);

        $this->signInWithMicrosoft($user, null, url('/users'), intended: url('/users'));
    }

    public function test_a_bertels_user_with_an_intended_claesen_url_still_lands_on_the_bertels_panel(): void
    {
        $user = $this->microsoftAccount($this->bertels, 'viewer');

        $this->signInWithMicrosoft($user, null, url('/bertels'), intended: url('/users'));
    }

    public function test_a_claesen_super_admin_using_the_bertels_button_lands_on_the_bertels_panel(): void
    {
        // Decision (L6): super_admin can open both panels; the button they clicked wins.
        $user = $this->microsoftAccount(null, 'super_admin', ['organization_id' => Organization::claesenId()]);

        $this->signInWithMicrosoft($user, 'bertels', url('/bertels'));
    }

    public function test_a_claesen_user_without_organization_lands_on_the_claesen_panel_as_today(): void
    {
        $user = $this->microsoftAccount(null, 'viewer', ['organization_id' => null]);

        $this->signInWithMicrosoft($user, null, url('/'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function forgedValues(): array
    {
        return [
            'unknown id' => ['nope'],
            'absolute url' => ['https://evil.example/phish'],
            'protocol-relative url' => ['//evil.example'],
        ];
    }

    #[DataProvider('forgedValues')]
    public function test_a_forged_panel_in_the_session_is_ignored_and_never_becomes_a_redirect(mixed $panel): void
    {
        $user = $this->microsoftAccount(null, 'admin', ['organization_id' => Organization::claesenId()]);

        $this->signInWithMicrosoft($user, $panel, url('/'));
    }

    // ---------------------------------------------------------- helpers

    private function microsoftAccount(?Organization $organization, string $role, array $attributes = []): User
    {
        // T1's Microsoft account: no password, no microsoft_id until the first sign-in.
        $user = User::factory()->create(array_merge([
            'organization_id' => $organization?->id,
            'is_active' => true,
            'password' => null,
            'password_set_at' => null,
            'microsoft_id' => null,
            'has_email_authentication' => false,
        ], $attributes));
        $user->assignRole($role);

        return $user->refresh();
    }

    private function buttonHref(TestResponse $response): string
    {
        $this->assertMatchesRegularExpression('/<a href="([^"]*auth\/microsoft\/redirect[^"]*)"/', $response->getContent());
        preg_match('/<a href="([^"]*auth\/microsoft\/redirect[^"]*)"/', $response->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    private function fakeSocialiteRedirect(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://login.example.test/authorize'));

        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    /** Runs the real callback (Socialite faked), asserts where it lands and that it is the signed-in user. */
    private function signInWithMicrosoft(User $user, mixed $panel, string $expectedLanding, ?string $intended = null): self
    {
        $azureUser = (new SocialiteUser)->map([
            'id' => 'azure-oid-1',
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $azureUser->token = 'fake-token';
        $azureUser->user = ['groups' => []];

        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($azureUser);
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        $session = ['auth_source' => 'filament'];
        if ($panel !== null) {
            $session['auth_panel'] = $panel;
        }
        if ($intended !== null) {
            $session['url.intended'] = $intended;
        }

        $response = $this->withSession($session)->get(route('auth.microsoft.callback'));

        $response->assertRedirect($expectedLanding);
        $this->assertAuthenticatedAs($user);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);

        return $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }
}
