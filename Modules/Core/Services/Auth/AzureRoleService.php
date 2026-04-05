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
        $roleMapping = config('core.azure_role_mapping', [
            // Example: 'azure-group-uuid' => 'financial_manager'
            env('AZURE_GROUP_SUPER_ADMIN') => 'super_admin',
            env('AZURE_GROUP_FINANCE') => 'financial_manager',
            env('AZURE_GROUP_PM') => 'project_manager',
        ]);

        $rolesToAssign = [];

        foreach ($azureGroups as $groupId) {
            if (isset($roleMapping[$groupId])) {
                $rolesToAssign[] = $roleMapping[$groupId];
            }
        }

        // Always ensure at least 'viewer' role if authenticated
        if (empty($rolesToAssign)) {
            $rolesToAssign[] = 'viewer';
        }

        // Sync roles (replaces existing)
        $user->syncRoles($rolesToAssign);
    }
}
