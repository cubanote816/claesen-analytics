<?php

use Laravel\Sanctum\Sanctum;

$localStatefulDomains = [
    'localhost',
    'localhost:3000',
    'localhost:5173',
    'localhost:5174',
    'localhost:5186',
    '127.0.0.1',
    '127.0.0.1:8000',
    '::1',
    Sanctum::currentApplicationUrlWithPort(),
];

// Environment domains extend the local development defaults instead of replacing
// them. Replacing the list made a local SPA on :5186 stateless when an existing
// SANCTUM_STATEFUL_DOMAINS value was present.
$configuredStatefulDomains = explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''));
$statefulDomains = array_merge($localStatefulDomains, $configuredStatefulDomains);

$clientPortal = parse_url((string) env('CLIENT_PORTAL_URL', ''));
if (is_array($clientPortal) && isset($clientPortal['host'])) {
    $statefulDomains[] = $clientPortal['host'].(isset($clientPortal['port']) ? ':'.$clientPortal['port'] : '');
}

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => array_values(array_unique(array_filter(array_map('trim', $statefulDomains)))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ],

];
