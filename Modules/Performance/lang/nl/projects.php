<?php

return [
    'navigation_label'   => 'Projecten',
    'model_label'        => 'Project',
    'plural_model_label' => 'Projecten',
    'columns' => [
        'name'                => 'Project Naam',
        'manager'             => 'Projectleider',
        'worked_total'        => 'Gewerkt (Totaal)',
        'pending_balance'     => 'Openstaand',
        'status'              => 'Status',
    ],
    'filters' => [
        'only_active'         => 'Alleen Actief',
        'worked_this_month'   => 'Gewerkt deze maand',
        'pending_collections' => 'Facturatie Wachtend',
    ],
];
