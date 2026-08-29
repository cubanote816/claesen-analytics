<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'fieldops.create',
        'fieldops.update',
        'fieldops.delete-infrastructure',
        'fieldops.media',
        'fieldops.ai',
    ];

    /**
     * CLA-496: creates the write-capability permissions and grants them to the roles
     * already decided in the approved matrix. Must run safely on `migrate:fresh`
     * (roles table still empty, RolesAndPermissionsSeeder not run yet) as well as on
     * an existing installation (roles already present) — see the two dedicated tests
     * in FieldOpsInfrastructurePermissionsMigrationTest.
     */
    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $grants = [
            'super_admin' => self::PERMISSIONS,
            'admin' => self::PERMISSIONS,
            'project_manager' => ['fieldops.create', 'fieldops.update', 'fieldops.media', 'fieldops.ai'],
            'technician' => ['fieldops.create', 'fieldops.update', 'fieldops.media', 'fieldops.ai'],
        ];

        foreach ($grants as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            // Fresh install / migrate:fresh: the role doesn't exist yet because
            // RolesAndPermissionsSeeder hasn't run. It grants these same permissions
            // once it does — nothing to do here but skip without throwing.
            if ($role === null) {
                continue;
            }

            $role->givePermissionTo($permissions);
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
