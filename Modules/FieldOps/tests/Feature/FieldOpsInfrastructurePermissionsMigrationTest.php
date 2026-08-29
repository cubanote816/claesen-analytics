<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-496: the permission backfill must be safe both on an upgrade (roles already
 * exist) and on a fresh install / migrate:fresh (roles table still empty, because
 * RolesAndPermissionsSeeder hasn't run yet) — see deploy.sh:53, which only runs
 * `migrate --force`, never a seeder. The migration itself already ran once (with
 * zero roles present) as part of RefreshDatabase's normal migration stack before
 * any test method here starts; re-invoking up() below exercises both branches
 * (idempotent no-op on the permissions, real grants once roles exist) without
 * needing to fake a full fresh-install migrate run.
 */
class FieldOpsInfrastructurePermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'fieldops.create',
        'fieldops.update',
        'fieldops.delete-infrastructure',
        'fieldops.media',
        'fieldops.ai',
    ];

    private function migration(): object
    {
        return require base_path('Modules/FieldOps/Database/Migrations/2026_08_28_036_add_fieldops_infrastructure_permissions.php');
    }

    public function test_it_grants_permissions_when_roles_already_exist(): void
    {
        foreach (['super_admin', 'admin', 'project_manager', 'technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->migration()->up();

        foreach (self::PERMISSIONS as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }

        $this->assertTrue(Role::findByName('super_admin')->hasPermissionTo('fieldops.delete-infrastructure'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('fieldops.delete-infrastructure'));
        $this->assertTrue(Role::findByName('project_manager')->hasPermissionTo('fieldops.create'));
        $this->assertTrue(Role::findByName('project_manager')->hasPermissionTo('fieldops.update'));
        $this->assertFalse(Role::findByName('project_manager')->hasPermissionTo('fieldops.delete-infrastructure'));
        $this->assertTrue(Role::findByName('technician')->hasPermissionTo('fieldops.media'));
        $this->assertFalse(Role::findByName('technician')->hasPermissionTo('fieldops.delete-infrastructure'));
    }

    public function test_it_creates_the_permissions_without_throwing_when_no_roles_exist(): void
    {
        // CLA-496 (backfill, migration 037): migrate:fresh now also runs 037 for real
        // before this test starts, which creates the technician role as part of its
        // own baseline backfill — clear that first to exercise the true "roles table
        // empty" branch this test is about, independent of 037's existence.
        Role::query()->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame(0, Role::count());

        $this->migration()->up();

        foreach (self::PERMISSIONS as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }

        // No RoleDoesNotExist was thrown, and no grant could have happened —
        // RolesAndPermissionsSeeder is what completes this on a fresh install.
        $this->assertSame(0, Role::query()->whereHas('permissions')->count());

        (new RolesAndPermissionsSeeder)->run();

        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('fieldops.delete-infrastructure'));
        $this->assertTrue(Role::findByName('technician')->hasPermissionTo('fieldops.create'));
    }

    public function test_up_is_idempotent(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, Permission::where('name', 'fieldops.create')->where('guard_name', 'web')->count());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('fieldops.create'));
    }

    public function test_down_removes_the_permissions(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        foreach (self::PERMISSIONS as $permission) {
            $this->assertDatabaseMissing('permissions', ['name' => $permission]);
        }
    }
}
