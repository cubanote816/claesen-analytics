<?php

namespace Modules\Core\Services\Auth;

use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class AzureRoleService
{
    /**
     * Map Azure AD groups to local roles.
     * 
     * @param User $user
     * @param array $azureGroups Array of Group IDs from Azure
     * @return void
     */
    public function syncRolesFromAzure(User $user, array $azureGroups = []): void
    {
        // If the user already has roles, do not modify anything (respect local DB as source of truth)
        // This prevents Azure from overriding manual role assignments like 'super_admin'.
        if ($user->roles()->exists()) {
            return;
        }

        $rolesToAssign = [];

        $roleMapping = config('core.azure_role_mapping', [
            // Example: 'azure-group-uuid' => 'financial_manager'
            env('AZURE_GROUP_SUPER_ADMIN') => 'super_admin',
            env('AZURE_GROUP_ADMIN') => 'admin',
            env('AZURE_GROUP_FINANCE') => 'financial_manager',
            env('AZURE_GROUP_PM') => 'project_manager',
        ]);

        foreach ($azureGroups as $groupId) {
            if (isset($roleMapping[$groupId]) && !in_array($roleMapping[$groupId], $rolesToAssign)) {
                $rolesToAssign[] = $roleMapping[$groupId];
            }
        }

        // Always ensure at least 'viewer' role if authenticated and has no roles
        if (empty($rolesToAssign)) {
            $rolesToAssign[] = 'viewer';
        }

        // Sync roles (replaces existing, but only for users who have NO roles)
        $user->syncRoles($rolesToAssign);
    }
}
