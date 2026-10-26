<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * F1/CLA-457 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Reusable fixture builders for organization-isolation tests, so every module's
 * isolation suite stops hand-rolling its own "create a fixture org + a user in
 * it" boilerplate (which is what every P5b/c/d test this session did ad hoc).
 *
 * Never touches or creates anything resembling a REAL Electro Bertels
 * organization/user (ADR D10, "regla de hierro" — no real Bertels data before
 * P5 is verified in a real deployed environment, which staging still isn't,
 * CLA-530). `bertelsFixtureOrganization()` creates a throwaway `Organization`
 * row inside the test's own transaction (RefreshDatabase) or truncated table
 * (DatabaseTruncation) — same pattern already used throughout P5's own tests,
 * just centralized. Its slug is randomized and prefixed so it can never collide
 * with a real future Bertels seed row.
 */
trait InteractsWithOrganizationFixtures
{
    /**
     * Flips config('organizations.enforce') on for the current test only —
     * every P1-P6 mechanism is a no-op with it off (D4), so isolation
     * assertions are meaningless without this. Laravel resets config
     * between tests automatically; nothing to tear down.
     */
    protected function enableOrganizationEnforcement(): void
    {
        config(['organizations.enforce' => true]);
    }

    protected function bertelsFixtureOrganization(array $attributes = []): Organization
    {
        return Organization::factory()->create(array_merge([
            'slug' => 'bertels-fixture-'.Str::lower(Str::random(8)),
            'name' => 'Bertels Fixture Org',
        ], $attributes));
    }

    protected function bertelsFixtureSite(?Organization $organization = null, array $attributes = []): Site
    {
        $organization ??= $this->bertelsFixtureOrganization();

        return Site::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'key' => 'bertels-fixture-'.Str::lower(Str::random(8)),
        ], $attributes));
    }

    protected function claesenUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'organization_id' => Organization::claesenId(),
        ], $attributes));
    }

    protected function bertelsFixtureUser(?Organization $organization = null, array $attributes = []): User
    {
        $organization ??= $this->bertelsFixtureOrganization();

        return User::factory()->create(array_merge([
            'organization_id' => $organization->id,
        ], $attributes));
    }

    /**
     * super_admin still belongs to exactly one organization (D2 — a single FK
     * column, never a membership table); its cross-organization reach comes
     * from the role/Gate::before (D5), not from having no organization at all.
     * Defaults to Claesen, matching every real super_admin today.
     */
    protected function superAdminUser(?Organization $organization = null, array $attributes = []): User
    {
        $user = $organization === null
            ? $this->claesenUser($attributes)
            : User::factory()->create(array_merge(['organization_id' => $organization->id], $attributes));

        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    /**
     * Grants $abilities (Spatie permission names) to $user, creating them if
     * missing. Used to isolate the organization-enforcement variable in a
     * mutation test from an unrelated ability-check 403 (e.g. CLA-496's
     * fieldops.create) — the harness asserts organization isolation, not the
     * module's own authorization matrix.
     *
     * @param  string[]  $abilities
     */
    protected function grantAbilities(User $user, array $abilities): void
    {
        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }
    }

    /**
     * Row-level mirrored dataset: creates one instance via $factory "owned by"
     * Claesen and a mirrored instance owned by a fresh Bertels fixture
     * organization, via $stampOrganizationId — a closure mapping an
     * organization id to the create attributes that stamp it onto the row
     * (e.g. fn (int $orgId) => ['site_id' => Site::factory()->create(['organization_id' => $orgId])->id]).
     *
     * Ready for F3/F4 row-level isolation (Website's site_id scoping, D3) but
     * UNEXERCISED by any real enforcement today — no row-level scope is active
     * anywhere yet (BelongsToSite's global scope stays inert until P5/F3
     * activates it for that domain, D4). Today's only real enforcement is
     * module-level (RequireOrganization, P5b) and panel-level (canAccessPanel,
     * P5a) — see AssertsCrossOrganizationIsolation for those.
     *
     * @return array{0: mixed, 1: mixed, 2: Organization} [claesenModel, bertelsModel, bertelsOrganization]
     */
    protected function mirroredDataset(Factory $factory, callable $stampOrganizationId): array
    {
        $bertelsOrganization = $this->bertelsFixtureOrganization();

        $claesenModel = $factory->create($stampOrganizationId(Organization::claesenId()));
        $bertelsModel = $factory->create($stampOrganizationId($bertelsOrganization->id));

        return [$claesenModel, $bertelsModel, $bertelsOrganization];
    }
}
