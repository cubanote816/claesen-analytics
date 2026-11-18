<?php

return [
    // Notification titles, keyed by `kind` (KNX-54). The summary is built from the
    // row's own data at the call site; the title is the line the inbox shows.
    'device_registered' => 'New device registered',
    'board_created' => 'New distribution board reported',
    'module_added' => 'New module reported',
    'module_changed' => 'Module changed',
    'address_conflict' => 'Address conflict reported',
    'intent_changed' => 'Intent changed',
    'issue_reported' => 'Incident reported',
    'malfunction_reported' => 'Malfunction reported',
    'malfunction_status_changed' => 'Malfunction updated',
    'visit_closed' => 'Visit closed',
];
