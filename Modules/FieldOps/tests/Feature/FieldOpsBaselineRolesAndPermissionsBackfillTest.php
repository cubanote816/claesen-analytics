<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-496 (backfill, 2026-08-29): `fieldops.view-all-clients` and the `technician`
 * role have only ever been created by RolesAndPermissionsSeeder, never by a
 * migration — an environment whose last seeder run predates CLA-364/the technician
 * role is left permanently without this baseline, since deploy.sh never runs a
 * seeder. This migration (037) backfills exactly that gap. It must not modify or
 * duplicate migration 036 (already applied in production) and must never revoke
 * anything on rollback — see down()'s own docblock.
 *
 * Same caveat as FieldOpsInfrastructurePermissionsMigrationTest (036): migration 037
 * is a real migration, so RefreshDatabase's migrate:fresh already runs it once (with
 * zero roles present) before any test method here starts — `technician` and
 * `fieldops.view-all-clients` already exist by the time a test body runs. Tests that
 * need the true "nothing exists yet" branch explicitly tear that state back down
 * first, rather than assuming it.
 */
class FieldOpsBaselineRolesAndPermissionsBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const BROAD_ROLES = [
        'super_admin',
        'admin',
        'project_manager',
        'financial_manager',
        'hr_manager',
        'viewer',
    ];

    private const TECHNICIAN_PERMISSIONS = [
        'fieldops.create',
        'fieldops.update',
        'fieldops.media',
        'fieldops.ai',
    ];

    private function migration(): object
    {
        return require base_path('Modules/FieldOps/Database/Migrations/2026_08_29_037_backfill_fieldops_baseline_roles_and_permissions.php');
    }

    public function test_it_grants_view_all_clients_to_every_existing_broad_role(): void
    {
        foreach (self::BROAD_ROLES as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->migration()->up();

        $this->assertDatabaseHas('permissions', ['name' => 'fieldops.view-all-clients', 'guard_name' => 'web']);

        foreach (self::BROAD_ROLES as $roleName) {
            $this->assertTrue(
                Role::findByName($roleName)->hasPermissionTo('fieldops.view-all-clients'),
                "{$roleName} should have fieldops.view-all-clients after the backfill",
            );
        }
    }

    public function test_it_is_safe_when_no_roles_exist_yet(): void
    {
        // migrate:fresh already ran this migration once for real before this test
        // started (see class docblock) — tear that state back down to exercise the
        // true from-scratch branch (fresh install, RolesAndPermissionsSeeder never
        // ran) without depending on migration order/caching quirks.
        Role::query()->delete();
        Permission::where('name', 'fieldops.view-all-clients')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame(0, Role::count());

        $this->migration()->up();

        $this->assertDatabaseHas('permissions', ['name' => 'fieldops.view-all-clients', 'guard_name' => 'web']);
        // technician is (re)created by up() itself even with every other role absent.
        $this->assertSame(1, Role::count());
        $this->assertDatabaseHas('roles', ['name' => 'technician', 'guard_name' => 'web']);
    }

    public function test_it_creates_the_technician_role_with_scoped_permissions_and_never_delete(): void
    {
        // Same caveat as above: delete the role migrate:fresh already created for
        // real, to exercise the "role doesn't exist yet" creation branch explicitly.
        Role::where('name', 'technician')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertDatabaseMissing('roles', ['name' => 'technician']);

        $this->migration()->up();

        $this->assertDatabaseHas('roles', ['name' => 'technician', 'guard_name' => 'web', 'sort' => 8]);

        $technician = Role::findByName('technician');
        foreach (self::TECHNICIAN_PERMISSIONS as $permission) {
            $this->assertTrue($technician->hasPermissionTo($permission));
        }
        $this->assertFalse($technician->hasPermissionTo('fieldops.delete-infrastructure'));
    }

    public function test_it_never_creates_the_client_role(): void
    {
        $this->migration()->up();

        $this->assertDatabaseMissing('roles', ['name' => 'client']);
    }

    public function test_narrow_roles_do_not_receive_view_all_clients(): void
    {
        // Sanity check on the matrix itself: only the 6 broad roles are touched.
        // A role outside that list (e.g. a pre-existing technician) must not gain
        // fieldops.view-all-clients as a side effect of this backfill.
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);

        $this->migration()->up();

        $this->assertFalse(Role::findByName('technician')->hasPermissionTo('fieldops.view-all-clients'));
    }

    public function test_it_backfills_the_exact_partial_production_state_found_in_the_audit(): void
    {
        // Mirrors what `permission:show` found for real: the six broad roles exist
        // with the CLA-496 (036) grants already applied where applicable, but
        // fieldops.view-all-clients was never created and technician never existed.
        foreach (self::BROAD_ROLES as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        Role::findByName('super_admin')->givePermissionTo(['fieldops.create', 'fieldops.update', 'fieldops.delete-infrastructure', 'fieldops.media', 'fieldops.ai']);
        Role::findByName('admin')->givePermissionTo(['fieldops.create', 'fieldops.update', 'fieldops.delete-infrastructure', 'fieldops.media', 'fieldops.ai']);
        Role::findByName('project_manager')->givePermissionTo(['fieldops.create', 'fieldops.update', 'fieldops.media', 'fieldops.ai']);

        $this->migration()->up();

        foreach (self::BROAD_ROLES as $roleName) {
            $this->assertTrue(Role::findByName($roleName)->hasPermissionTo('fieldops.view-all-clients'));
        }
        $technician = Role::findByName('technician');
        $this->assertNotNull($technician);
        foreach (self::TECHNICIAN_PERMISSIONS as $permission) {
            $this->assertTrue($technician->hasPermissionTo($permission));
        }
        $this->assertFalse($technician->hasPermissionTo('fieldops.delete-infrastructure'));

        // The pre-existing 036 grants must survive untouched.
        $this->assertTrue(Role::findByName('super_admin')->hasPermissionTo('fieldops.delete-infrastructure'));
        $this->assertTrue(Role::findByName('project_manager')->hasPermissionTo('fieldops.create'));
    }

    public function test_up_is_idempotent(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(
            1,
            Permission::where('name', 'fieldops.view-all-clients')->where('guard_name', 'web')->count(),
        );
        $this->assertSame(1, Role::where('name', 'technician')->count());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('fieldops.view-all-clients'));
    }

    public function test_down_does_not_revoke_or_delete_anything(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $this->assertDatabaseHas('permissions', ['name' => 'fieldops.view-all-clients', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'technician', 'guard_name' => 'web']);
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('fieldops.view-all-clients'));
        $this->assertTrue(Role::findByName('technician')->hasPermissionTo('fieldops.create'));
    }
}
