<?php

return [
    'model_label' => 'Email Template',
    'plural_model_label' => 'Email Templates',
    'navigation_group' => 'Growth & Acquisition',

    'fields' => [
        'name'             => 'Template Name',
        'subject'          => 'Subject',
        'body'             => 'Body Content',
        'category'         => 'Category',
        'variables'        => 'Variables',
        'variable_key'     => 'Key',
        'variable_label'   => 'Label',
        'variable_example' => 'Example',
        'version'          => 'Version',
        'is_active'        => 'Is Active',
        'created_at'       => 'Created At',
        'updated_at'       => 'Updated At',
    ],

    'sections' => [
        'template_details' => 'Template Details',
        'variables'        => 'Template Variables',
        'variables_desc'   => 'Define the dynamic variables available in this template (e.g. {{ name }}, {{ regio }}).',
        'content'          => 'Email Content',
        'content_desc'     => 'Manage the email subject and message content.',
    ],

    'actions' => [
        'add_variable' => 'Add variable',
    ],

    'options' => [
        'placeholders' => [
            'prospect_name' => 'Name of the club',
            'contact_person' => 'Contact person (secretary)',
            'address' => 'Full address',
        ],
    ],

    'notifications' => [
        'template_saved' => 'Template successfully saved.',
    ],
];
