<?php

return [
    'name' => 'FieldOps',
    'structure_proximity_warning_meters' => env('FIELDOPS_STRUCTURE_PROXIMITY_WARNING_METERS', 10),
    'field_app_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'client_portal_url' => env('CLIENT_PORTAL_URL', 'http://localhost:5174'),
];
