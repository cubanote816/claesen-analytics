<?php

return [
    'name' => 'Core',

    // Email domain required for Azure OAuth login. Only employees with this domain
    // can be provisioned as backoffice users. Overridable via COMPANY_EMAIL_DOMAIN.
    'company_email_domain' => env('COMPANY_EMAIL_DOMAIN', 'claesen-verlichting.be'),

    // OAuth redirects are accepted only when their origin is explicitly listed.
    // CLIENT_PORTAL_URL remains empty until the production domain is confirmed.
    'frontend_redirect_urls' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('CLIENT_PORTAL_URL'),
        'https://service.claesen-verlichting.be/',
        'https://lightcoral-whale-907350.hostingersite.com/safety/',
        'http://localhost:5173/',
        'http://localhost:5174/',
    ])),

    /*
    |--------------------------------------------------------------------------
    | Security alert thresholds
    |--------------------------------------------------------------------------
    | Login failure auditing is persisted in core_auth_attempts. When the
    | counts below are crossed inside the rolling window, super_admin users
    | receive a database notification.
    */
    'security_alerts' => [
        'window_minutes' => env('CORE_SECURITY_ALERT_WINDOW_MINUTES', 15),
        'failed_login_threshold' => env('CORE_SECURITY_FAILED_LOGIN_THRESHOLD', 10),
        'throttled_login_threshold' => env('CORE_SECURITY_THROTTLED_LOGIN_THRESHOLD', 5),
        'repeated_identifier_threshold' => env('CORE_SECURITY_IDENTIFIER_THRESHOLD', 5),
        'repeated_ip_threshold' => env('CORE_SECURITY_IP_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure AD group -> local role mapping
    |--------------------------------------------------------------------------
    | CLA-532: built role-first then flipped so that unset AZURE_GROUP_* vars
    | (null) are removed by array_filter BEFORE they could collapse into a
    | single empty-string key (the previous runtime env() default array did
    | exactly that under config:cache, leaving every Azure user at 'viewer').
    | Evaluated at config-cache build time -> config:cache-safe.
    | Result shape: [ '<azure-group-guid>' => '<role>' ].
    */
    'azure_role_mapping' => array_flip(array_filter([
        'super_admin' => env('AZURE_GROUP_SUPER_ADMIN'),
        'admin' => env('AZURE_GROUP_ADMIN'),
        'financial_manager' => env('AZURE_GROUP_FINANCE'),
        'project_manager' => env('AZURE_GROUP_PM'),
    ])),
];
