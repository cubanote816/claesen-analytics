<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxEmployee;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * K1 — the session endpoints of docs/BACKEND-API.md §7.
 *
 * What matters here is who gets in, and that every refusal looks identical:
 * the endpoint must not become a way to enumerate accounts, or to find out
 * whether an account is missing a role, a person row or its active flag.
 */
final class KnxSessionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // `throttle:5,1` on the login route counts in the cache, and the array
        // store lives for the whole test process — without this, one test's logins
        // would 429 the next test's.
        Cache::flush();

        Role::findOrCreate('knx_office', 'web');
        Role::findOrCreate('viewer', 'web');

        $this->organization = Organization::factory()->create(['slug' => 'electro-bertels']);
    }

    /**
     * Laravel's test client reuses the application (and therefore the already
     * resolved `sanctum` guard) between requests inside one test, so a request
     * made after a logout or without a token would still look authenticated.
     * Dropping the guards makes each of those requests authenticate from scratch,
     * which is what a real HTTP client does.
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** An office person with an account that may sign in. */
    private function officeUser(array $userOverrides = [], array $employeeOverrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'lead@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
            'organization_id' => $this->organization->id,
        ], $userOverrides));
        $user->assignRole('knx_office');

        KnxEmployee::factory()->office()->forOrganization($this->organization)->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Lien Smet',
            'knx_role' => 'lead',
        ], $employeeOverrides));

        return $user;
    }

    public function test_a_lead_can_sign_in_and_gets_the_contract_session_payload(): void
    {
        $this->officeUser();

        $response = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'expires_in', 'user' => ['id', 'name', 'initials', 'role', 'email', 'domain']])
            ->assertJsonPath('user.name', 'Lien Smet')
            ->assertJsonPath('user.initials', 'LS')
            ->assertJsonPath('user.role', 'lead')
            ->assertJsonPath('user.email', 'lead@electrobertels.be')
            ->assertJsonPath('user.domain', config('knx.kantoor_domain'));

        $this->assertSame(60 * 60, $response->json('expires_in'));
        $this->assertNotEmpty($response->json('access_token'));
    }

    public function test_every_refusal_looks_exactly_the_same(): void
    {
        // Not a Kantoor user at all.
        $outsider = User::factory()->create([
            'email' => 'outsider@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
        ]);
        $outsider->assignRole('viewer');

        // Right role, but deactivated.
        $this->officeUser(['email' => 'inactive@electrobertels.be', 'is_active' => false]);

        // Right role and active, but no person row.
        $orphan = User::factory()->create([
            'email' => 'orphan@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
        ]);
        $orphan->assignRole('knx_office');

        // Right role, person row exists but is a field technician.
        $field = User::factory()->create([
            'email' => 'field@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
        ]);
        $field->assignRole('knx_office');
        KnxEmployee::factory()->field()->forOrganization($this->organization)->create(['user_id' => $field->id]);

        $cases = [
            ['nobody@electrobertels.be', 'Secret1234!'],   // unknown account
            ['outsider@electrobertels.be', 'Secret1234!'], // no role
            ['inactive@electrobertels.be', 'Secret1234!'], // deactivated
            ['orphan@electrobertels.be', 'Secret1234!'],   // no person row
            ['field@electrobertels.be', 'Secret1234!'],    // not an office person
            ['lead@electrobertels.be', 'wrong-password'],  // wrong password
        ];

        // One of the cases needs a real account to exist.
        $this->officeUser(['email' => 'lead@electrobertels.be']);

        foreach ($cases as [$email, $password]) {
            Cache::flush();

            $response = $this->postJson('/api/v1/knx/auth/login', compact('email', 'password'));

            $response->assertStatus(422)
                ->assertJsonPath('code', 'validation_error')
                ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);

            $this->assertNotEmpty($response->json('errors.email'), "no error for [{$email}]");
        }
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/knx/auth/login', ['email' => 'nobody@electrobertels.be', 'password' => 'x']);
        }

        $this->postJson('/api/v1/knx/auth/login', ['email' => 'nobody@electrobertels.be', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited');
    }

    public function test_me_session_returns_the_signed_in_person_and_requires_a_token(): void
    {
        $this->officeUser();
        $token = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ])->json('access_token');

        $this->withToken($token)->getJson('/api/v1/knx/me/session')
            ->assertOk()
            ->assertJsonPath('name', 'Lien Smet')
            ->assertJsonPath('role', 'lead');

        // withToken() is sticky for the rest of the test, so the "no token" case
        // has to clear the header explicitly.
        $this->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/knx/me/session')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_a_token_whose_account_lost_its_access_behaves_like_no_token(): void
    {
        $user = $this->officeUser();
        $token = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ])->json('access_token');

        $user->syncRoles([]);

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/knx/me/session')->assertUnauthorized();
    }

    public function test_refresh_rotates_the_presented_token_and_revokes_the_old_one(): void
    {
        $this->officeUser();
        $login = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ]);
        $old = $login->json('access_token');

        $new = $this->withToken($old)->postJson('/api/v1/knx/auth/refresh')
            ->assertOk()
            ->assertJsonPath('expires_in', 60 * 60)
            ->json('access_token');

        $this->assertNotSame($old, $new);

        // The rotated token no longer works, the fresh one does.
        $this->forgetGuards();
        $this->withToken($old)->getJson('/api/v1/knx/me/session')->assertUnauthorized();

        $this->forgetGuards();
        $this->withToken($new)->getJson('/api/v1/knx/me/session')->assertOk();
    }

    public function test_refresh_also_accepts_the_documented_refresh_token_body(): void
    {
        $this->officeUser();
        $token = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ])->json('access_token');

        $this->postJson('/api/v1/knx/auth/refresh', ['refresh_token' => $token])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'expires_in']);

        $this->postJson('/api/v1/knx/auth/refresh', ['refresh_token' => 'not-a-token'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['refresh_token']]);
    }

    public function test_a_deactivated_user_cannot_mint_a_new_token_from_an_old_one(): void
    {
        $user = $this->officeUser();
        $token = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ])->json('access_token');

        $user->update(['is_active' => false]);

        $this->postJson('/api/v1/knx/auth/refresh', ['refresh_token' => $token])
            ->assertStatus(422);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $this->officeUser();
        $token = $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lead@electrobertels.be',
            'password' => 'Secret1234!',
        ])->json('access_token');

        $this->assertSame(1, PersonalAccessToken::query()->count());

        $this->withToken($token)->postJson('/api/v1/knx/auth/logout')->assertNoContent();

        $this->assertSame(0, PersonalAccessToken::query()->count(), 'logout must revoke the presented token');

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/knx/me/session')->assertUnauthorized();
    }

    public function test_a_claesen_token_cannot_reach_the_knx_api(): void
    {
        // The route middleware is the cross-tenant gate; the seeded Kantoor user
        // belongs to Bertels, so a Claesen account must be refused outright.
        $claesen = User::factory()->create([
            'email' => 'claesen@claesen-verlichting.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
            'organization_id' => Organization::claesenId(),
        ]);
        $claesen->assignRole('knx_office'); // even with the role, wrong organization
        KnxEmployee::factory()->office()->forOrganization($this->organization)->create(['user_id' => $claesen->id]);

        $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'claesen@claesen-verlichting.be',
            'password' => 'Secret1234!',
        ])->assertStatus(422);
    }

    public function test_the_demo_seed_creates_a_usable_kantoor_account(): void
    {
        $this->seed(KnxDemoSeeder::class);

        $this->postJson('/api/v1/knx/auth/login', [
            'email' => 'lien.smet@electrobertels.be',
            'password' => KnxDemoSeeder::DEMO_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Lien Smet')
            ->assertJsonPath('user.role', 'lead');
    }
}
