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
        // (technician/project_manager included) is scoped to whichever FoClient
        // records get assigned to them via Users > fieldOpsClients in Filament.
        $viewAllClients = Permission::findOrCreate('fieldops.view-all-clients', 'web');
        foreach (['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer'] as $roleName) {
            Role::findByName($roleName, 'web')->givePermissionTo($viewAllClients);
        }

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
