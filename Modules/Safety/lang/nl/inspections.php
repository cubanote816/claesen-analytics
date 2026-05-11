<?php

return [
    'navigation' => 'Inspecties',
    'model_label' => 'Inspectie',
    'plural_model_label' => 'Inspecties',
    'columns' => [
        'project_id' => 'Project ID',
        'inspector' => 'Inspecteur',
        'checklist' => 'Checklist',
        'date' => 'Datum',
    ],
    'filters' => [
        'has_nok' => 'Heeft defecten (NOK)',
        'from' => 'Van',
        'until' => 'Tot',
    ],
    'actions' => [
        'regenerate_pdf' => 'PDF regenereren',
        'download_pdf' => 'Download PDF',
        'regenerate_success' => 'PDF regeneratie gestart in de achtergrond.',
    ],
    'widgets' => [
        'latest_inspections' => 'Recente Werkplekinspecties',
        'stats' => [
            'this_month' => 'Inspecties deze maand',
            'trend' => ':trend t.o.v. vorige maand',
            'nok_this_month' => 'Niet Akkoord (NOK) dit maand',
            'nok_hint' => 'Punten die directe aandacht vereisen',
            'pdf_reports' => 'PDF Rapporten',
            'pdf_hint' => 'Automatisch gegenereerde rapporten',
        ],
    ],
];
