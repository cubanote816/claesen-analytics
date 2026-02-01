<?php

return [
    'label' => 'Employee',
    'plural_label' => 'Employees',
    'navigation_label' => 'Employees',
    'navigation_group' => 'Personnel',
    'placeholders' => [
        'no_function' => 'No function specified',
        'total_hours' => '--- h',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'AI analysis in progress...',
        'project_timeline_loading' => 'Loading recent projects...',
        'last_6_months' => 'Last 6 months',
        'this_month' => 'This Month',
        'last_quarter' => 'Last Quarter',
        'last_semester' => 'Last Semester',
        'previous_year' => 'Previous Year',
        'from' => 'From',
        'to' => 'To',
    ],

    'navigation' => [
        'details' => 'Details',
        'edit' => 'Edit',
        'performance' => 'Performance',
    ],

    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'email' => 'E-mail',
        'mobile' => 'Mobile',
        'phone' => 'Phone',
        'is_active' => 'Status',
        'function_default' => 'General',
        'job_function' => 'Function',
        'avatar' => 'Avatar',
        'address' => 'Address',
        'street' => 'Street',
        'zip' => 'Zip Code',
        'city' => 'City',
        'country' => 'Country',
        'notes' => 'Notes',
        'personal_information' => 'Personal Information',
        'contact_details' => 'Contact Details',
    ],

    'status' => [
        'all' => 'All',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'actions' => [
        'performance' => 'Performance',
        'edit' => [
            'label' => 'Edit',
        ],
        'sync' => [
            'label' => 'Sync SAP/ERP',
            'notification' => [
                'success' => 'Sync completed: :created new, :updated updated.',
                'error' => 'An error occurred during synchronization.',
            ],
            'command' => [
                'description' => 'Synchronize technicians from Legacy SQL Server to local MySQL',
                'starting' => 'Starting technician synchronization...',
                'up_to_date' => 'All records are already up to date.',
                'success' => 'Synchronization completed successfully.',
                'failed' => 'Synchronization failed: :error',
                'table' => [
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'errors' => 'Errors',
                ],
            ],
        ],
        'analyze' => [
            'label' => 'Start AI Analysis',
            'confirm' => [
                'title' => 'Start new analysis?',
                'body' => 'This will re-fetch performance data for the last 6 months and analyze it with AI. This may take a moment.',
            ],
            'notification' => [
                'success' => 'Analysis started in background. Refresh the page in a few moments.',
            ],
        ],
    ],
    'sections' => [
        'business_card' => 'Business Card',
        'performance_dashboard' => 'Performance Dashboard',
        'performance_dashboard_desc' => 'Analysis of hours distribution and project activity over the selected period.',
        'ai_insights' => 'AI Insights & Archetype',
        'project_timeline' => 'Project Timeline',
        'project_timeline_title' => 'PROJECT TIMELINE',
        'hours_distribution' => 'HOURS DISTRIBUTION',
        'watchdog_alerts' => 'WATCHDOG ALERT',
        'watchdog_description' => 'Critical alerts for this profile.',
    ],

    'insights' => [
        'archetype' => 'Technician Archetype',
        'burnout_risk' => 'Burnout Risk',
        'efficiency_trend' => 'Efficiency Trend',
        'efficiency_trend' => 'Efficiency Trend',
        'current_project' => 'Active Projects (30d)',
        'manager_insight' => 'Manager Insight',
        'last_audited' => 'Last audited',
        'status' => [
            'stable' => 'Stable',
            'increasing' => 'Increasing',
            'decreasing' => 'Decreasing',
        ],
    ],

    'messages' => [
        'watchdog_warning' => 'Critical performance anomaly detected. Check project details immediately.',
    ],

    'stats' => [
        'total_hours' => 'Total Worked',
        'efficiency' => 'Efficiency',
        'projects_count' => 'Active Projects',
    ],
];
