<?php

return [
    'navigation' => 'Inspecties',
    'model_label' => 'Inspectie',
    'plural_model_label' => 'Inspecties',
    'columns' => [
        'project_id'      => 'Project ID',
        'inspector'       => 'Inspecteur',
        'checklist'       => 'Checklist',
        'date'            => 'Datum',
        'type'            => 'Type',
        'reporter'        => 'Inspecteur / Melder',
        'involved_worker' => 'Betrokken Medewerker',
        'present_workers' => 'Aanwezige medewerkers',
        'completed_at'    => 'Voltooid op',
        'question'        => 'Vraag',
        'remark'          => 'Opmerking',
    ],
    'filters' => [
        'has_nok' => 'Heeft defecten (NOK)',
        'from' => 'Van',
        'until' => 'Tot',
    ],
    'actions' => [
        'regenerate_pdf'      => 'PDF regenereren',
        'download_pdf'        => 'PDF',
        'regenerate_success'  => 'PDF regeneratie gestart in de achtergrond.',
        'regenerate_error'    => 'Fout bij genereren PDF',
        'archive'             => 'Archiveren',
        'archive_heading'     => 'Inspectie archiveren?',
        'archive_description' => "De inspectie wordt verborgen, maar antwoorden, foto's en PDF blijven bewaard.",
        'archive_confirm'     => 'Archiveren',
        'group_label'         => 'Acties',
    ],
    'sections' => [
        'details' => 'Inspectie Details',
        'answers' => 'Antwoorden',
    ],
    'types' => [
        'inspection' => 'Werkplekinspectie',
        'incident'   => 'Incidentrapport',
    ],
    'pdf_status' => [
        'generated'     => 'Gegenereerd',
        'not_generated' => 'Niet gegenereerd',
    ],
    'statuses' => [
        'ok'  => 'Akkoord (OK)',
        'nok' => 'Niet Akkoord (NOK)',
        'na'  => 'N/A',
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
        'adoption' => [
            'mau_title'          => 'Adoptie MAU (Gisteren)',
            'mau_desc'           => ':active actief / :total ingeschakeld',
            'inspections_title'  => 'Inspecties Voltooid (Gisteren)',
            'inspections_desc'   => 'Succesvolle indieningen bevestigd',
            'incidents_title'    => 'Incidenten Gemeld (Gisteren)',
            'incidents_desc'     => 'Bevestigde incidentmeldingen',
            'friction_title'     => 'Technische Fricties (Gisteren)',
            'friction_desc'      => 'Upload-fouten of netwerkconflicten',
        ],
    ],
];
