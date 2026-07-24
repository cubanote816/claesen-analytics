<?php

return [
    'name' => 'FieldOps',
    'structure_proximity_warning_meters' => env('FIELDOPS_STRUCTURE_PROXIMITY_WARNING_METERS', 10),
    'field_app_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'client_portal_url' => env('CLIENT_PORTAL_URL', 'http://localhost:5174'),

    // Thresholds for `fieldops:check-request-alerts` (Fase 5 operational monitoring).
    'request_alerts' => [
        'first_response_hours' => env('FIELDOPS_REQUEST_ALERT_FIRST_RESPONSE_HOURS', 24),
        'confirmation_wait_days' => env('FIELDOPS_REQUEST_ALERT_CONFIRMATION_WAIT_DAYS', 7),
    ],
];
