<?php

return [
    'name' => 'Core',

    // Email domain required for Azure OAuth login. Only employees with this domain
    // can be provisioned as backoffice users. Overridable via COMPANY_EMAIL_DOMAIN.
    'company_email_domain' => env('COMPANY_EMAIL_DOMAIN', 'claesen-verlichting.be'),

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
];
