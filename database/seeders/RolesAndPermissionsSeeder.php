<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Modules\Core\Models\User;

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
        ];
 
        foreach ($roles as $roleName => $sort) {
            Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['sort' => $sort]
            );
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
