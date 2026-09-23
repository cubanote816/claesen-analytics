<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * F4/CLA-476 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Exportaciones limitadas por rol..." / on-demand GDPR erasure — both are
 * sensitive bulk-PII actions, restricted to super_admin/admin only (the
 * same two roles CLA-496 already trusted with FieldOps infrastructure
 * writes). Same migration-based backfill pattern as CLA-496's
 * 2026_08_28_036 — deploy.sh only runs `migrate --force`, never a seeder,
 * so a permission a fresh RolesAndPermissionsSeeder run would grant must
 * also be backfilled here for an existing installation.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'website.export-consultation-requests',
        'website.erase-consultation-pii',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            // Fresh install / migrate:fresh: the role doesn't exist yet
            // because RolesAndPermissionsSeeder hasn't run. It grants these
            // same permissions once it does — nothing to do here but skip
            // without throwing (same guard as CLA-496's migration).
            if ($role === null) {
                continue;
            }

            $role->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::where('name', $permission)->where('guard_name', 'web')->first()?->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
