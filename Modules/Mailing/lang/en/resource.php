<?php

return [
    'model_label' => 'Email Template',
    'plural_model_label' => 'Email Templates',
    'navigation_group' => 'Growth & Acquisition',

    'campaign' => [
        'model_label'        => 'Campaign',
        'plural_model_label' => 'Campaigns',
    ],

    'fields' => [
        'name'             => 'Template Name',
        'subject'          => 'Subject',
        'subject_helper'   => 'You may override the subject for this campaign.',
        'body'             => 'Body Content',
        'category'         => 'Category',
        'variables'        => 'Variables',
        'variable_key'     => 'Key',
        'variable_label'   => 'Label',
        'variable_example' => 'Example',
        'version'          => 'Version',
        'description'      => 'Campaign Name / Description',
        'template'         => 'Template',
        'status'           => 'Status',
        'total_count'      => 'Total',
        'success_count'    => 'Sent',
        'failed_count'     => 'Failed',
        'skipped_count'    => 'Skipped',
        'started_by'       => 'Started By',
        'started_at'       => 'Started At',
        'approved_by'      => 'Approved By',
        'approved_at'      => 'Approved At',
        'is_active'        => 'Is Active',
        'created_at'       => 'Created At',
        'updated_at'       => 'Updated At',
    ],

    'sections' => [
        'template_details'  => 'Template Details',
        'variables'         => 'Template Variables',
        'variables_desc'    => 'Define the dynamic variables available in this template (e.g. {{ name }}, {{ regio }}).',
        'content'           => 'Email Content',
        'content_desc'      => 'Manage the email subject and message content.',
        'campaign_details'  => 'Campaign Details',
        'campaign_summary'  => 'Campaign Summary',
        'approval'          => 'Approval',
        'snapshot'          => 'Content Snapshot',
        'snapshot_desc'     => 'Subject and body as used in this campaign.',
    ],

    'actions' => [
        'add_variable'   => 'Add variable',
        'submit_review'  => 'Submit for Review',
        'approve'        => 'Approve',
        'cancel'         => 'Cancel Campaign',
        'cancel_confirm' => 'This action cannot be undone. The campaign will be permanently cancelled.',
    ],

    'options' => [
        'placeholders' => [
            'prospect_name' => 'Name of the club',
            'contact_person' => 'Contact person (secretary)',
            'address' => 'Full address',
        ],
    ],

    'notifications' => [
        'template_saved'   => 'Template successfully saved.',
        'submitted_review' => 'Campaign submitted for review.',
        'approved'         => 'Campaign approved and ready to send.',
        'cancelled'        => 'Campaign has been cancelled.',
    ],

    'metrics' => [
        'sent'              => 'Sent',
        'delivered'         => 'Delivered',
        'opens'             => 'Unique Opens',
        'opens_note'        => '* Indicative — Apple MPP may inflate this value',
        'clicks'            => 'Unique Clicks',
        'hard_bounces'      => 'Hard Bounces',
        'soft_bounces'      => 'Soft Bounces',
        'complained'        => 'Spam Complaints',
        'unsubscribed'      => 'Unsubscribed',
        'alert_high_bounce' => '⚠ Above 5% threshold',
        'alert_high_spam'   => '⚠ Above 0.08% threshold',
    ],
];
