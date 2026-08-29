<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
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

    /**
     * Production baseline gap found auditing CLA-496 (2026-08-29): `fieldops.view-all-
     * clients` (CLA-364) and the `technician`/`client` roles have only ever been
     * created by `RolesAndPermissionsSeeder`, never by a migration. `deploy.sh` only
     * runs `migrate --force`, never a seeder, so an environment whose last seeder run
     * predates CLA-364/the `technician`/`client` roles is left permanently missing
     * this baseline — `FieldOpsInfrastructurePolicy::update()`/`view()` both depend on
     * `fieldops.view-all-clients` via `FieldOpsTenantService::hasBroadAccess()`, so
     * without this row `super_admin`/`admin` silently lose all broad FieldOps access
     * (view AND write), scoped down to nothing unless a Complex is explicitly
     * assigned. Migration 2026_08_28_036 assumed this baseline already existed and
     * must not be modified (already applied in production) — this is a separate
     * backfill, not a fix to that one. `client` stays out of scope on purpose: its
     * absence is a decision pending a review of the Client Portal, not addressed here.
     */
    public function up(): void
    {
        $viewAllClients = Permission::findOrCreate('fieldops.view-all-clients', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::BROAD_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            // Fresh install / migrate:fresh: the role doesn't exist yet because
            // RolesAndPermissionsSeeder hasn't run. It grants this same permission
            // once it does — nothing to do here but skip without throwing.
            if ($role === null) {
                continue;
            }

            $role->givePermissionTo($viewAllClients);
        }

        $technician = Role::updateOrCreate(
            ['name' => 'technician', 'guard_name' => 'web'],
            ['sort' => 8],
        );

        foreach (self::TECHNICIAN_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Never fieldops.delete-infrastructure — technician keeps the CLA-496 matrix
        // (create/update/media/ai only), whether the role already existed or this
        // migration just created it.
        $technician->givePermissionTo(self::TECHNICIAN_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Deliberately non-destructive. This migration backfills a historical baseline
     * that may have existed already in some environments before this migration ever
     * ran (e.g. `fieldops.view-all-clients` created manually via the seeder, or a
     * `technician` role with grants applied by hand or by 036's own grant loop) — it
     * has no way to tell which of what it just touched was actually created by `up()`
     * versus already present. A destructive `down()` risks deleting a legitimate
     * permission still in use, or stripping capabilities from real technicians in
     * production. Rolling back this migration is a no-op by design; if this baseline
     * ever needs to be removed, do it with a deliberate, reviewed migration of its
     * own, not an automatic rollback of this one.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
