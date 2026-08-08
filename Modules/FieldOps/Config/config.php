<?php

return [
    'name' => 'FieldOps',
    'structure_proximity_warning_meters' => env('FIELDOPS_STRUCTURE_PROXIMITY_WARNING_METERS', 10),
    'field_app_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'client_portal_url' => env('CLIENT_PORTAL_URL', 'http://localhost:5174'),
    // No local default on purpose: unset in dev keeps the Safety role gate inert
    // (sameOrigin() against null never matches) until a real URL is configured.
    'safety_app_url' => env('SAFETY_URL'),

    // Fallback map center/zoom used by location pickers (Complex/Terrain/
    // Structure/ElectricalBoard) when no coordinates can be resolved from
    // the record itself or its hierarchy — defaults to Claesen's home area.
    'default_map' => [
        'lat' => env('FIELDOPS_DEFAULT_MAP_LAT', 51.1635),
        'lng' => env('FIELDOPS_DEFAULT_MAP_LNG', 5.1640),
        'zoom' => env('FIELDOPS_DEFAULT_MAP_ZOOM', 16),
    ],

    // Thresholds for `fieldops:check-request-alerts` (Fase 5 operational monitoring).
    'request_alerts' => [
        'first_response_hours' => env('FIELDOPS_REQUEST_ALERT_FIRST_RESPONSE_HOURS', 24),
        'confirmation_wait_days' => env('FIELDOPS_REQUEST_ALERT_CONFIRMATION_WAIT_DAYS', 7),
    ],
];
