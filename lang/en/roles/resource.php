<?php

return [
    'navigation_group' => 'User Management',
    'model_label' => 'Role',
    'plural_model_label' => 'Roles',
    'fields' => [
        'name' => 'Name',
        'guard_name' => 'Guard Name',
        'permissions' => 'Permissions',
    ],
    'sections' => [
        'permissions' => 'Permissions',
        'permissions_description' => 'Manage user permissions',
    ],
];
