<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Modules\Core\Http\Middleware\RequireOrganization;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Core\Support\ClaesenAppAccess;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * CLA-704 T3 (S7): the Claesen apps never sign in, and Claesen API routes never serve,
 * a user whose organization is set and is not Claesen — independent of ORGANIZATIONS_ENFORCE.
 */
final class ClaesenApiOrganizationBoundaryTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private const PASSWORD = 'Secret1234!';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client', 'technician', 'project_manager', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['organizations.enforce' => false]);
        // Sanctum bootstraps the session middleware for stateful API requests from the Origin.
        $this->withHeader('Origin', 'http://localhost');
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> [uri, role, kind] */
    public static function passwordSignIns(): array
    {
        return [
            'api login' => ['/api/v1/auth/login', 'admin', 'token'],
            'login sport' => ['/api/v1/auth/login/sport', 'admin', 'session'],
            'login client portal' => ['/api/v1/auth/login/client-portal', 'client', 'session'],
            'safety login' => ['/api/v1/login', 'admin', 'safety'],
        ];
    }

    private function account(?Organization $organization, string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'organization_id' => $organization?->id,
            'password' => self::PASSWORD,
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function signIn(string $uri, string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson($uri, ['email' => $email, 'password' => $password]);
    }

    private function bertelsUser(string $role): User
    {
        return $this->account($this->bertelsFixtureOrganization(), $role);
    }

    // ---------------------------------------------------- password / token sign-ins

    #[DataProvider('passwordSignIns')]
    public function test_a_bertels_user_with_the_right_password_gets_the_unauthorized_response(string $uri, string $role, string $kind): void
    {
        $bertels = $this->bertelsUser($role);
        $other = $this->account(null, $role);

        $baseline = $this->signIn($uri, $other->email, 'wrong-password');
        $response = $this->signIn($uri, $bertels->email);

        $response->assertStatus($baseline->getStatusCode());
        $this->assertSame($baseline->json(), $response->json());
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('passwordSignIns')]
    public function test_a_bertels_user_is_rejected_even_when_enforcement_is_on(string $uri, string $role, string $kind): void
    {
        $this->enableOrganizationEnforcement();
        $bertels = $this->bertelsUser($role);

        $this->signIn($uri, $bertels->email)->assertStatus($uri === '/api/v1/login' ? 401 : 422);
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[DataProvider('passwordSignIns')]
    public function test_a_rejected_bertels_sign_in_does_not_change_the_rate_limiters(string $uri, string $role, string $kind): void
    {
        if ($kind === 'safety') {
            $this->markTestSkipped('Safety login has no rate limiter of its own.');
        }

        $bertels = $this->bertelsUser($role);
        $email = strtolower($bertels->email);

        $this->signIn($uri, $bertels->email)->assertStatus(422);

        $this->assertSame(0, RateLimiter::attempts("core-login-email:{$email}"));
    }

    #[DataProvider('passwordSignIns')]
    public function test_claesen_and_null_organization_users_still_sign_in(string $uri, string $role, string $kind): void
    {
        $claesen = $this->account(Organization::query()->find(Organization::claesenId()), $role);
        $unassigned = $this->account(null, $role);

        foreach ([$claesen, $unassigned] as $user) {
            $response = $this->signIn($uri, $user->email)->assertOk();
            $this->assertSame($user->id, $response->json('user.id'));
            $this->app['auth']->guard('web')->logout();
        }

        $this->assertDatabaseCount('personal_access_tokens', $kind === 'token' ? 2 : 0);
    }

    // ------------------------------------------------------------- activation code

    private function pendingUser(?Organization $organization): array
    {
        $code = str_repeat('a', 64);
        $user = User::factory()->create([
            'organization_id' => $organization?->id,
            'password' => null,
            'password_set_at' => null,
            'is_active' => true,
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(10),
        ]);

        return [$user, $code];
    }

    public function test_a_bertels_activation_code_is_not_exchanged_and_looks_like_an_invalid_code(): void
    {
        [$user, $code] = $this->pendingUser($this->bertelsFixtureOrganization());

        $baseline = $this->postJson('/api/v1/auth/activate', ['code' => str_repeat('b', 64)]);
        $response = $this->postJson('/api/v1/auth/activate', ['code' => $code]);

        $response->assertStatus($baseline->getStatusCode());
        $this->assertSame($baseline->json('message'), $response->json('message'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(hash('sha256', $code), $user->fresh()->activation_code_hash);
    }

    public function test_claesen_and_null_organization_activation_codes_still_exchange(): void
    {
        foreach ([Organization::query()->find(Organization::claesenId()), null] as $organization) {
            [, $code] = $this->pendingUser($organization);

            $this->postJson('/api/v1/auth/activate', ['code' => $code])
                ->assertOk()
                ->assertJsonStructure(['setup_token', 'expires_in']);
        }
    }

    // ----------------------------------------------------------- Microsoft (API)

    private function microsoftCallback(User|string $account, array $session = []): TestResponse
    {
        $email = $account instanceof User ? $account->email : $account;
        $azureUser = (new SocialiteUser)->map(['id' => 'azure-oid-1', 'name' => 'Azure Name', 'email' => $email]);
        $azureUser->token = 'fake-token';
        $azureUser->user = ['groups' => []];

        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($azureUser);
        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);

        return $this->withSession($session)->get('/api/v1/auth/microsoft/callback');
    }

    public function test_a_bertels_user_is_rejected_by_the_microsoft_api_callback_like_an_unauthorized_account(): void
    {
        $bertels = $this->bertelsUser('admin');

        $response = $this->microsoftCallback($bertels);

        $response->assertRedirect('/login');
        $this->assertSame(
            "Toegang Geweigerd: Uw Microsoft-account ({$bertels->email}) is niet geautoriseerd voor deze applicatie. Neem contact op met de beheerder.",
            session('errors')->first('microsoft'),
        );
        $this->assertGuest();
        $fresh = $bertels->fresh();
        $this->assertNull($fresh->microsoft_id);
        $this->assertNull($fresh->azure_token);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_bertels_user_with_a_pending_setup_gets_no_activation_code_from_the_microsoft_api_callback(): void
    {
        $bertels = $this->account($this->bertelsFixtureOrganization(), 'admin', ['password' => null, 'password_set_at' => null]);

        $this->microsoftCallback($bertels)->assertRedirect('/login');

        $this->assertNull($bertels->fresh()->activation_code_hash);
        $this->assertGuest();
    }

    public function test_a_claesen_user_still_signs_in_through_the_microsoft_api_callback(): void
    {
        $user = $this->account(Organization::query()->find(Organization::claesenId()), 'admin');

        $response = $this->microsoftCallback($user);

        $this->assertNotSame(url('/login'), $response->headers->get('Location'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_null_organization_user_still_signs_in_through_the_microsoft_api_callback(): void
    {
        $user = $this->account(null, 'admin');

        $response = $this->microsoftCallback($user);

        $this->assertNotSame(url('/login'), $response->headers->get('Location'));
        $this->assertAuthenticatedAs($user);
    }

    // ------------------------------------------------- authenticated requests (routes)

    /** @return array<string, array{0: string}> */
    public static function claesenRoutes(): array
    {
        return ['cafca' => ['/api/v1/cafcas'], 'safety' => ['/api/v1/me']];
    }

    #[DataProvider('claesenRoutes')]
    public function test_a_bertels_token_gets_403_with_enforcement_off_and_on(string $uri): void
    {
        $token = $this->bertelsUser('admin')->createToken('t')->plainTextToken;

        foreach ([false, true] as $enforce) {
            config(['organizations.enforce' => $enforce]);

            $this->withToken($token)->getJson($uri)->assertForbidden();
        }
    }

    #[DataProvider('claesenRoutes')]
    public function test_claesen_and_null_organization_tokens_still_reach_the_route_with_enforcement_off(string $uri): void
    {
        foreach ([Organization::query()->find(Organization::claesenId()), null] as $organization) {
            $token = $this->account($organization, 'admin')->createToken('t')->plainTextToken;

            $this->assertNotSame(403, $this->withToken($token)->getJson($uri)->getStatusCode());
        }
    }

    // ------------------------------------------------------------ middleware / rule

    private function runMiddleware(?User $user, string $slug): int
    {
        $request = Request::create('/x');
        $request->setUserResolver(fn () => $user);

        try {
            return (new RequireOrganization)->handle($request, fn () => new Response('ok'), $slug)->getStatusCode();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
    }

    public function test_the_middleware_compares_against_its_own_slug_not_claesen(): void
    {
        $bertels = $this->bertelsFixtureOrganization(['slug' => 'electro-bertels']);
        $user = $this->account($bertels, 'admin');
        $claesen = $this->account(Organization::query()->find(Organization::claesenId()), 'admin');

        $this->assertSame(200, $this->runMiddleware($user, 'electro-bertels'));
        $this->assertSame(403, $this->runMiddleware($claesen, 'electro-bertels'));
        $this->assertSame(403, $this->runMiddleware($user, 'claesen'));
    }

    public function test_an_unresolvable_slug_denies_a_set_organization_user_and_passes_a_null_one_with_enforcement_off(): void
    {
        $this->assertSame(403, $this->runMiddleware($this->bertelsUser('admin'), 'no-such-organization'));
        $this->assertSame(200, $this->runMiddleware($this->account(null, 'admin'), 'no-such-organization'));
    }

    public function test_with_enforcement_on_a_null_organization_user_is_still_rejected(): void
    {
        $this->enableOrganizationEnforcement();

        $this->assertSame(403, $this->runMiddleware($this->account(null, 'admin'), 'claesen'));
    }

    public function test_the_rule_denies_only_set_non_claesen_organizations(): void
    {
        $this->assertTrue(ClaesenAppAccess::denies($this->bertelsUser('admin')));
        $this->assertFalse(ClaesenAppAccess::denies($this->account(null, 'admin')));
        $this->assertFalse(ClaesenAppAccess::denies($this->account(Organization::query()->find(Organization::claesenId()), 'admin')));
    }

    public function test_the_rule_fails_closed_when_the_claesen_organization_cannot_be_resolved(): void
    {
        $claesen = $this->account(Organization::query()->find(Organization::claesenId()), 'admin');
        $unassigned = $this->account(null, 'admin');
        Organization::query()->where('slug', Organization::CLAESEN_SLUG)->update(['slug' => 'claesen-renamed']);

        $this->assertTrue(ClaesenAppAccess::denies($claesen));
        $this->assertFalse(ClaesenAppAccess::denies($unassigned));
    }
}
