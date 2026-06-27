<?php

return [
    'navigation' => 'Inspections',
    'model_label' => 'Inspection',
    'plural_model_label' => 'Inspections',
    'columns' => [
        'project_id'      => 'Project ID',
        'inspector'       => 'Inspector',
        'checklist'       => 'Checklist',
        'date'            => 'Date',
        'type'            => 'Type',
        'reporter'        => 'Inspector / Reporter',
        'involved_worker' => 'Involved Worker',
        'present_workers' => 'Present workers',
        'completed_at'    => 'Completed at',
        'question'        => 'Question',
        'remark'          => 'Remark',
    ],
    'filters' => [
        'has_nok' => 'Has defects (NOK)',
        'from' => 'From',
        'until' => 'Until',
    ],
    'actions' => [
        'regenerate_pdf'      => 'PDF',
        'download_pdf'        => 'PDF',
        'regenerate_success'  => 'PDF regeneration started in background.',
        'regenerate_error'    => 'Error generating PDF',
        'archive'             => 'Archive',
        'archive_heading'     => 'Archive inspection?',
        'archive_description' => "The inspection will be hidden, but answers, photos and PDF are preserved.",
        'archive_confirm'     => 'Archive',
        'group_label'         => 'Actions',
    ],
    'sections' => [
        'details' => 'Inspection Details',
        'answers' => 'Answers',
    ],
    'types' => [
        'inspection' => 'Site Inspection',
        'incident'   => 'Incident Report',
    ],
    'pdf_status' => [
        'generated'     => 'Generated',
        'not_generated' => 'Not generated',
    ],
    'statuses' => [
        'ok'  => 'Compliant (OK)',
        'nok' => 'Non-compliant (NOK)',
        'na'  => 'N/A',
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
        'adoption' => [
            'mau_title'          => 'MAU Adoption (Yesterday)',
            'mau_desc'           => ':active active / :total enabled',
            'inspections_title'  => 'Inspections Completed (Yesterday)',
            'inspections_desc'   => 'Successful submissions confirmed',
            'incidents_title'    => 'Incidents Reported (Yesterday)',
            'incidents_desc'     => 'Confirmed incident reports',
            'friction_title'     => 'Technical Friction (Yesterday)',
            'friction_desc'      => 'Upload failures or network conflicts',
        ],
    ],
];
