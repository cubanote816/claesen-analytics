<?php

return [
    'name' => 'Website',

    /*
    |--------------------------------------------------------------------------
    | Consultation notification recipient
    |--------------------------------------------------------------------------
    | CLA-532: internal address that receives the "new consultation request"
    | e-mail. Read via config() so it survives config:cache (was a hardcoded
    | address before). Empty / null => the notification is skipped and a
    | warning is logged; the consultation itself is still persisted (HTTP 201).
    */
    'consultation_notification_email' => env('WEBSITE_CONSULTATION_EMAIL'),
];
