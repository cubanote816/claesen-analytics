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
];
