<?php

return [
    'name'               => 'Safety',
    'disk'               => 'local',
    'per_page'           => 15,
    'per_page_max'       => 50,
    'compliance_days'    => 30,
    'pwa_url'             => env('SAFETY_PWA_URL', ''),
    'reminder_grace_days' => 7,
    'report_recipients'   => array_filter(
        array_map('trim', explode(',', env('SAFETY_REPORT_RECIPIENTS', '')))
    ),
];
