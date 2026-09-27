<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'v1/*', 'mailing/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('CLIENT_PORTAL_URL'),
        'https://www.claesen-verlichting.be',
        'https://claesen-verlichting.be',
        'https://lightcoral-whale-907350.hostingersite.com',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5180',
        'http://localhost:5186',
        'http://localhost:5190',
    ])),

    'allowed_origins_patterns' => array_values(array_filter([
        // Túnel público (cloudflared trycloudflare.com) para probar el dev
        // server en dispositivos fuera de la red local. Cualquiera puede
        // levantar un túnel gratuito bajo este dominio con un subdominio
        // aleatorio, así que este patrón nunca debe aplicar fuera de
        // `local` — combinado con `supports_credentials`, en cualquier
        // otro entorno sería CORS abierto a un origen no controlado por
        // nosotros. Quitar cuando ya no se necesite exponer el frontend
        // de dev.
        env('APP_ENV') === 'local'
            ? '#^https://[a-z0-9-]+\.trycloudflare\.com$#i'
            : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
