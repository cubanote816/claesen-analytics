<?php

return [
    'navigation_label' => 'Billing Control',
    'title'            => 'Billing Control',
    'alert_in_review'  => 'Alert placed in review.',
    'alert_reopened'   => 'Alert reopened.',
    'notifications' => [
        'confirmed_title' => 'Alert confirmed.',
        'confirmed_body'  => 'Take the required action in CAFCA, then click Resolve.',
        'dismissed_title' => 'Alert dismissed.',
        'dismissed_body'  => 'Use Reopen if this turns out to be incorrect.',
        'resolved_title'  => 'Alert resolved.',
        'resolved_body'   => 'This alert no longer counts towards the monthly close.',
        'guardian_title'  => 'Guardian complete — :count alert(s) found',
        'guardian_body'   => ':created created, :updated updated, :skipped skipped.',
    ],
    'actions' => [
        'run_guardian'         => 'Run Guardian',
        'run_guardian_heading' => 'Run Guardian?',
        'run_guardian_desc'    => 'Run after saving changes in CAFCA, or at the start of the month to analyse the previous period. Open alerts are updated with the latest data. Confirmed and resolved alerts are NOT modified.',
    ],
];
