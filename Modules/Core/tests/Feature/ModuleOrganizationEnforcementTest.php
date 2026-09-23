<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\RequireOrganization;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F1/P5b of the multi-organization program (CLA-552) —
 * docs/ai/adr-multi-organization.md.
 *
 * Second enforcement layer the ADR names: "rutas de módulos Claesen". P5a
 * (CLA-460) already gates the Filament admin panel via canAccessPanel(),
 * but these 8 modules are consumed directly over Sanctum-token auth
 * (Claesen-Sport, Safety PWA) — a request never passes through a Filament
 * panel at all, so P5a's gate never runs for them.
 * Modules\Core\Http\Middleware\RequireOrganization (alias `organization`)
 * closes that gap on their auth:sanctum route groups.
 *
 * Gated by config('organizations.enforce') (D4) — off (the default
 * everywhere today) it changes nothing observable for Claesen.
 *
 * No real non-Claesen user exists (ADR D10, "regla de hierro") — every
 * test here uses a fixture organization created and rolled back within
 * RefreshDatabase's transaction, never persisted for real.
 *
 * Endpoints chosen deliberately need no extra business setup: FieldOps'
 * /pin-catalog and Safety's /me take no route-model parameter, so
 * EnforceFieldOpsTenantAccess/EnsureSafetyAccess never block a plain
 * non-client user before the request reaches RequireOrganization;
 * Employee's index has no extra role gate; Mailing/Intelligence/
 * Performance/Prospects/Cafca's apiResource index() is an unfleshed
 * nwidart scaffold stub (`return view('x::index')`, confirmed by reading
 * each controller — never wired to real logic) — still a real route with
 * real middleware, so it's a legitimate enforcement target even though
 * nothing behind it does real work yet.
 */
final class ModuleOrganizationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'fieldops' => ['GET', '/api/v1/fieldops/pin-catalog'],
            'safety' => ['GET', '/api/v1/me'],
            'employees' => ['GET', '/api/v1/employees'],
            'mailing' => ['GET', '/api/v1/mailings'],
            'intelligence' => ['GET', '/api/v1/intelligence'],
            'performance' => ['GET', '/api/v1/performances'],
            'prospects' => ['GET', '/api/v1/prospects'],
            'cafca' => ['GET', '/api/v1/cafcas'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_with_enforcement_off_a_user_outside_claesens_organization_can_still_reach_the_endpoint(string $method, string $uri): void
    {
        config(['organizations.enforce' => false]);

        $user = $this->userInFixtureOrganization();

        $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

        $this->assertNotSame(403, $response->getStatusCode(), "Expected {$uri} to be reachable with enforcement off.");
    }

    #[DataProvider('endpointProvider')]
    public function test_with_enforcement_on_a_user_outside_claesens_organization_is_rejected(string $method, string $uri): void
    {
        config(['organizations.enforce' => true]);

        $user = $this->userInFixtureOrganization();

        $this->actingAs($user, 'sanctum')->json($method, $uri)->assertForbidden();
    }

    #[DataProvider('endpointProvider')]
    public function test_with_enforcement_on_a_claesen_user_is_unaffected(string $method, string $uri): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true]); // defaults to Claesen's org
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')->json($method, $uri);

        $this->assertNotSame(403, $response->getStatusCode(), "Expected {$uri} to stay reachable for a Claesen user.");
    }

    public function test_a_user_with_no_organization_at_all_is_rejected_when_enforcement_is_on(): void
    {
        config(['organizations.enforce' => true]);

        $user = User::factory()->create(['is_active' => true, 'organization_id' => null]);
        $user->assignRole('admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/fieldops/pin-catalog')
            ->assertForbidden();
    }

    public function test_the_middleware_fails_loudly_for_a_misconfigured_organization_slug(): void
    {
        config(['organizations.enforce' => true]);

        $middleware = app(RequireOrganization::class);
        $request = Request::create('/whatever');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not-a-real-org');

        $middleware->handle($request, fn (Request $req) => response('unreachable'), 'not-a-real-org');
    }

    private function userInFixtureOrganization(): User
    {
        $fixtureOrg = Organization::factory()->create(['slug' => 'fixture-non-claesen-org']);

        $user = User::factory()->create([
            'organization_id' => $fixtureOrg->id,
            'is_active' => true,
            'password_set_at' => now(),
        ]);
        $user->assignRole('admin');

        return $user->refresh();
    }
}
