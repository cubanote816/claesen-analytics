<?php

return [
    'navigation_label'   => 'Projects',
    'model_label'        => 'Project',
    'plural_model_label' => 'Projects',
    'columns' => [
        'name'                => 'Project Name',
        'manager'             => 'Project Manager',
        'worked_total'        => 'Worked (Total)',
        'pending_balance'     => 'Pending Balance',
        'status'              => 'Status',
    ],
    'filters' => [
        'only_active'         => 'Only Active',
        'worked_this_month'   => 'Worked this month',
        'pending_collections' => 'Pending Collections',
    ],
];
