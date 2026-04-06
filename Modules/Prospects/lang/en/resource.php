<?php

return [
    'model_label' => 'Prospect',
    'plural_model_label' => 'Prospects',
    'navigation_group' => 'Growth & Acquisition',

    'sections' => [
        'club_info' => 'Club Information',
        'marketing_targets' => 'Marketing Targets',
        'sync_details' => 'Sync Details',
        'logs' => 'Logs / Events',
        'logs_desc' => 'Detailed view of events during execution.',
        'mailing_details' => 'Mailing Details',
        'mail_details' => 'Mailing Details',
        'snapshot' => 'Snapshot',
        'snapshot_desc' => 'Exact content of the sent email.',
        'campaign_summary' => 'Campaign Summary',
        'recipients' => 'Recipients',
    ],

    'fields' => [
        'name' => 'Club Name',
        'region' => 'Region',
        'federation' => 'Federation',
        'language' => 'Language',
        'contact_person' => 'Secretary / Contact Person',
        'channel' => 'Channel',
        'website' => 'Website',
        'vat_number' => 'VAT Number',
        'cafca_id' => 'CAFCA Relation ID',
        'locations' => 'Locations',
        'contact_type' => 'Contact Type',
        'email' => 'Email',
        'phone' => 'Phone Number',
        'address' => 'Address',
        'logo' => 'Logo',
        'has_email' => 'Has Email Address',
        'locations_count' => 'Number of Locations',
        'command' => 'Command',
        'status' => 'Status',
        'started_by' => 'Started by',
        'items' => 'Items',
        'started_at' => 'Started at',
        'finished_at' => 'Finished at',
        'total_items' => 'Total items processed',
        'prospect' => 'Prospect',
        'template' => 'Template / Campaign',
        'sent_at' => 'Sent at',
        'error_message' => 'Error Message',
        'subject' => 'Subject',
        'body' => 'Content',
        'type_sport' => 'Sport Type',
        'description' => 'Description / Objective',
        'total_count' => 'Total',
        'success_count' => 'Success',
        'failed_count' => 'Failed',
        'skipped_count' => 'Skipped',
        'unsubscribed_at' => 'Subscription Status',
    ],

    'options' => [
        'contact_types' => [
            'headquarters' => 'Headquarters',
            'stadium' => 'Stadium',
            'venue_name' => 'Venue Name',
            'club_house' => 'Clubhouse',
            'contact_person' => 'Contact Person',
            'other' => 'Other',
        ],
        'languages' => [
            'nl' => 'Dutch',
            'fr' => 'French',
            'en' => 'English',
        ],
        'status' => [
            'running' => 'Running...',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'sent' => 'Sent',
            'skipped' => 'Skipped (No email)',
            'active' => 'Active',
            'unsubscribed' => 'Unsubscribed',
            'all' => 'All',
        ],
        'sport_types' => [
            'football_club' => 'Football',
            'athletics_club' => 'Athletics',
            'tennis_padel_club' => 'Tennis & Padel',
            'hockey_club' => 'Hockey',
        ],
    ],

    'actions' => [
        'execute_campaign' => [
            'label' => 'Start Mailing Campaign',
            'form' => [
                'template' => 'Choose Email Template',
                'description' => 'Campaign Description',
                'description_placeholder' => 'e.g.: Fair follow-up or New catalog introduction...',
            ],
        ],
        'sync_master' => [
            'label' => 'Sync All (Master)',
        ],
        'individual_sync' => [
            'label' => 'Individual Sync',
            'rbfa' => 'Sync RBFA (Football)',
            'lbfa' => 'Sync LBFA (Athletics FR)',
            'val' => 'Sync VAL (Athletics NL)',
            'hockey' => 'Sync Hockey (VHL/LFH)',
            'tpv' => 'Sync Tennis & Padel (TPV)',
            'aft' => 'Sync AFT (Tennis FR)',
        ],
        'apply_filters' => 'Apply Filters',
        'mark_failed' => [
            'label' => 'Mark as Failed',
        ],
        'mark_completed' => [
            'label' => 'Mark as Completed',
        ],
    ],

    'notifications' => [
        'no_prospects_selected' => [
            'title' => 'No prospects selected',
            'body' => 'Please select at least one prospect to start the mailing.',
        ],
        'campaign_started' => [
            'title' => 'Campaign Started',
            'body' => 'Emails are being sent in the background using the selected template.',
        ],
        'partial_skip' => [
            'title' => 'Campaign Started',
            'body' => 'Attention: :unsubscribed of the :total selected prospects are unsubscribed and will be skipped.',
        ],
        'master_sync_started' => [
            'title' => 'Master Sync Started',
            'body' => 'All federations are now being synchronized sequentially in the background.',
        ],
        'sync_started' => [
            'title' => 'Synchronization Started',
            'body' => 'Target task :command is running in the background.',
        ],
        'manually_failed' => [
            'title' => 'Status updated to Failed',
        ],
        'manually_completed' => [
            'title' => 'Status updated to Completed',
        ],
        'no_emails_found' => [
            'title' => 'No email addresses found',
            'body' => 'The selected prospects do not have any locations with registered email addresses.',
        ],
        'error_mailer' => 'Error sending campaign via mailer service.',
    ],

    'sync_history' => [
        'model_label' => 'Synchronization',
        'plural_model_label' => 'Sync Management',
        'logs' => [
            'master_requested' => 'Master synchronization requested via panel.',
            'manually_failed' => 'The synchronization was manually marked as FAILED by an administrator.',
            'manually_completed' => 'The synchronization was manually marked as COMPLETED by an administrator.',
        ],
    ],

    'mail_log' => [
        'model_label' => 'Recipient Log',
        'plural_model_label' => 'Recipients',
    ],

    'mail_campaign' => [
        'model_label' => 'Mailing Campaign',
        'plural_model_label' => 'Mail History',
    ],

    'defaults' => [
        'region' => 'Flanders',
    ],
];
