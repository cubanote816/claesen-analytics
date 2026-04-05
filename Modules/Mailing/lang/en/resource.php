<?php

return [
    'model_label' => 'Email Template',
    'plural_model_label' => 'Email Templates',
    'navigation_group' => 'Content & Website',

    'fields' => [
        'name' => 'Template Name',
        'subject' => 'Subject',
        'body' => 'Body Content',
        'is_active' => 'Is Active',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    'sections' => [
        'general' => 'General Information',
        'content' => 'Email Content',
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
