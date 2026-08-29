<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create standard roles with logical sorting
        $roles = [
            'super_admin' => 1,
            'admin' => 2,
            'project_manager' => 3,
            'financial_manager' => 4,
            'hr_manager' => 5,
            'viewer' => 6,
            'client' => 7,
            'technician' => 8,
        ];

        foreach ($roles as $roleName => $sort) {
            Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['sort' => $sort]
            );
        }

        // CLA-364: broad (unscoped) FieldOps data access is the exception, not the
        // default — only these roles keep seeing every client's data. Everyone else
        // (technician included) is scoped to whichever FoClient records get
        // assigned to them via Users > fieldOpsClients in Filament.
        // CLA-377: project_manager was moved back into the unscoped group (business
        // decision) so PMs see every client without needing an explicit
        // fieldOpsClients assignment.
        $viewAllClients = Permission::findOrCreate('fieldops.view-all-clients', 'web');
        foreach (['super_admin', 'admin', 'project_manager', 'financial_manager', 'hr_manager', 'viewer'] as $roleName) {
            Role::findByName($roleName, 'web')->givePermissionTo($viewAllClients);
        }

        // CLA-496: write capabilities on FieldOps infrastructure (Complex/Terrain/
        // Structure/LuminaireFrame/Luminaire/ElectricalBoard) are separate from
        // fieldops.view-all-clients above — a role can see everything and still not
        // be allowed to mutate it (financial_manager/hr_manager/viewer). Mirrors the
        // grants already applied by migration 2026_08_28_036 for a fresh install
        // where that migration ran before these roles existed.
        $infrastructurePermissions = [
            'fieldops.create',
            'fieldops.update',
            'fieldops.delete-infrastructure',
            'fieldops.media',
            'fieldops.ai',
        ];
        foreach ($infrastructurePermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findByName('super_admin', 'web')->givePermissionTo($infrastructurePermissions);
        Role::findByName('admin', 'web')->givePermissionTo($infrastructurePermissions);

        $scopedWritePermissions = ['fieldops.create', 'fieldops.update', 'fieldops.media', 'fieldops.ai'];
        Role::findByName('project_manager', 'web')->givePermissionTo($scopedWritePermissions);
        Role::findByName('technician', 'web')->givePermissionTo($scopedWritePermissions);

        $superAdminRole = Role::findByName('super_admin');

        // Create a Super Admin User
        $user = User::firstOrCreate([
            'email' => 'admin@claesen-analytics.com',
        ], [
            'name' => 'Super Admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($superAdminRole);
    }
}
