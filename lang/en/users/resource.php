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
        'account_type' => 'Account type',
        'internal_account' => 'Internal employee',
        'client_account' => 'Client contact',
        'clients' => 'Authorized clients',
        'clients_hint' => 'Client contacts can only access FieldOps data belonging to these clients.',
        'password' => 'Password',
        'roles' => 'Roles',
        'is_active' => 'Active account',
        'is_active_hint' => 'Deactivating blocks all logins immediately.',
        'status' => 'Status',
        'last_active_at' => 'Last Active',
        'online' => 'Online',
        'offline' => 'Offline',
    ],
];
