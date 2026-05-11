<?php

return [
    'navigation' => 'Inspections',
    'model_label' => 'Inspection',
    'plural_model_label' => 'Inspections',
    'columns' => [
        'project_id' => 'Project ID',
        'inspector' => 'Inspector',
        'checklist' => 'Checklist',
        'date' => 'Date',
    ],
    'filters' => [
        'has_nok' => 'Has defects (NOK)',
        'from' => 'From',
        'until' => 'Until',
    ],
    'actions' => [
        'regenerate_pdf' => 'Regenerate PDF',
        'download_pdf' => 'Download PDF',
        'regenerate_success' => 'PDF regeneration started in background.',
    ],
    'widgets' => [
        'latest_inspections' => 'Recent Safety Inspections',
        'stats' => [
            'this_month' => 'Inspections this month',
            'trend' => ':trend vs last month',
            'nok_this_month' => 'Non-compliant (NOK) this month',
            'nok_hint' => 'Points requiring immediate attention',
            'pdf_reports' => 'PDF Reports',
            'pdf_hint' => 'Automatically generated reports',
        ],
    ],
];
