<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Modules\Core\Filament\Pages\Auth\Login;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * odd/tasks/release-trunk-to-main.md, T6 / S2c — a session signed in through the
 * Microsoft callback does not have to set up the app MFA again (Entra ID is the
 * second factor), while the email + local password sign-in keeps both the
 * set-up requirement and the Login page challenge — including for an account
 * that also has a `microsoft_id`.
 *
 * Everything goes through the public interface: the real callback (Socialite
 * faked), the real Login page, and real panel requests that carry the session
 * cookie of the sign-in.
 */
final class MicrosoftSessionMfaExemptionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret123!';

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
    }

    /** @return array<string, array{0: string}> */
    public static function adminRoles(): array
    {
        return ['super_admin' => ['super_admin'], 'admin' => ['admin']];
    }

    #[DataProvider('adminRoles')]
    public function test_a_microsoft_session_without_app_mfa_reaches_the_admin_dashboard(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->signInWithMicrosoft($user)
            ->get('/')
            ->assertOk();
    }

    public function test_a_microsoft_session_without_app_mfa_reaches_the_bertels_panel(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->signInWithMicrosoft($user)
            ->get('/bertels')
            ->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function panels(): array
    {
        return [
            'admin panel' => ['/', 'filament.admin.auth.multi-factor-authentication.set-up-required'],
            'bertels panel' => ['/bertels', 'filament.bertels.auth.multi-factor-authentication.set-up-required'],
        ];
    }

    #[DataProvider('panels')]
    public function test_a_password_session_of_an_account_that_also_has_a_microsoft_id_must_still_set_up_mfa(string $url, string $setUpRoute): void
    {
        $user = $this->userWithRole('super_admin');
        $this->assertNotNull($user->microsoft_id);

        $this->signInWithPassword($user)
            ->get($url)
            ->assertRedirect(route($setUpRoute));
    }

    public function test_a_password_sign_in_of_an_account_with_a_microsoft_id_is_still_challenged_once_mfa_is_set_up(): void
    {
        Notification::fake();

        $user = $this->userWithRole('super_admin');
        $user->toggleEmailAuthentication(true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    /** @return array<string, array{0: string}> */
    public static function forgeries(): array
    {
        return [
            'namespaced key' => ['core.auth_via'],
            'bare key' => ['auth_via'],
        ];
    }

    #[DataProvider('forgeries')]
    public function test_a_marker_forged_in_the_query_body_cookie_or_header_does_not_exempt_a_password_session(string $key): void
    {
        $user = $this->userWithRole('super_admin');
        $setUp = route('filament.admin.auth.multi-factor-authentication.set-up-required');

        $session = $this->signInWithPassword($user);

        $session->get('/?'.http_build_query([$key => 'microsoft']))->assertRedirect($setUp);
        $session->call('GET', '/', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([$key => 'microsoft']))
            ->assertRedirect($setUp);
        $session->withUnencryptedCookie($key, 'microsoft')->get('/')->assertRedirect($setUp);
        $session->withHeader($key, 'microsoft')->withHeader('X-'.$key, 'microsoft')->get('/')->assertRedirect($setUp);
    }

    public function test_another_sign_in_in_a_session_that_carried_the_microsoft_marker_drops_the_exemption(): void
    {
        $user = $this->userWithRole('super_admin');
        $session = $this->signInWithMicrosoft($user);
        $session->get('/')->assertOk();

        // The session-cookie sign-in used by the Sport app re-logs the same browser
        // session in without going through the Login page or a logout.
        $session->post(route('auth.login.sport'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSuccessful();

        $session->get('/')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_a_microsoft_session_followed_by_logout_and_a_password_login_must_set_up_mfa_again(): void
    {
        $user = $this->userWithRole('super_admin');
        $microsoft = $this->signInWithMicrosoft($user);
        $microsoft->get('/')->assertOk();

        $microsoft->post(route('filament.admin.auth.logout'))->assertRedirect();
        $this->assertGuest();

        $this->signInWithPassword($user)
            ->get('/')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_a_microsoft_session_does_not_change_what_other_roles_see(): void
    {
        $viewer = $this->userWithRole('viewer');

        $microsoft = $this->signInWithMicrosoft($viewer);
        $microsoft->get('/')->assertOk();
        $microsoft->post(route('filament.admin.auth.logout'));

        $this->signInWithPassword($viewer)->get('/')->assertOk();
    }

    public function test_when_the_enforcement_flag_is_off_both_paths_reach_the_dashboard(): void
    {
        config(['core.panel_mfa_enforced' => false]);

        $user = $this->userWithRole('super_admin');

        $microsoft = $this->signInWithMicrosoft($user);
        $microsoft->get('/')->assertOk();
        $microsoft->post(route('filament.admin.auth.logout'));

        $this->signInWithPassword($user)->get('/')->assertOk();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password' => self::PASSWORD,
            'password_set_at' => now(),
            'microsoft_id' => 'azure-oid-'.uniqid(),
            'has_email_authentication' => false,
        ]);
        $user->assignRole($role);

        return $user->refresh();
    }

    /**
     * Runs the real Microsoft callback (Socialite faked) as a Filament sign-in and
     * returns this test with the resulting session cookie attached.
     */
    private function signInWithMicrosoft(User $user): self
    {
        $azureUser = (new SocialiteUser)->map([
            'id' => $user->microsoft_id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $azureUser->token = 'fake-token';
        $azureUser->user = ['groups' => []];

        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($azureUser);

        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        $response = $this->withSession(['auth_source' => 'filament'])
            ->get(route('auth.microsoft.callback'));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        return $this->withSessionCookieOf($response);
    }

    /** Runs the real Login page with email + local password and keeps its session. */
    private function signInWithPassword(User $user): self
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);

        session()->save();

        return $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function withSessionCookieOf(\Illuminate\Testing\TestResponse $response): self
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'The callback did not issue a session cookie.');

        return $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }
}
