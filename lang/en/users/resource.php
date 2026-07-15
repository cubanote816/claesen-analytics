<?php

return [
    'navigation_group' => 'User Management',
    'model_label' => 'User',
    'plural_model_label' => 'Users',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'email_placeholder' => 'Will be filled when employee is selected',
        'employee' => 'Employee',
        'employee_hint' => 'Only employees with a @:domain address can access the backoffice via Azure.',
        'password' => 'Password',
        'roles' => 'Roles',
        'is_active' => 'Active account',
        'is_active_hint' => 'Deactivating blocks all logins immediately.',
        'status' => 'Status',
        'last_active_at' => 'Last Active',
        'last_login_at' => 'Last Login',
        'last_login_app_source' => 'App Origin',
        'last_login_channel' => 'Channel',
        'unknown_source' => 'Unknown',
        'unknown_channel' => 'Unknown',
        'online' => 'Online',
        'offline' => 'Offline',
    ],
];
