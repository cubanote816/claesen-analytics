<?php

return [
    'label' => 'Employee',
    'plural_label' => 'Employees',
    'navigation_label' => 'Employees',
    'navigation_group' => 'Personnel',

    'navigation' => [
        'details' => 'Details',
        'edit' => 'Edit',
        'performance' => 'Performance',
    ],

    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'email' => 'Email',
        'mobile' => 'Mobile',
        'phone' => 'Phone',
        'is_active' => 'Status',
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
    ],

    'placeholders' => [
        'total_hours' => '--- h',
        'efficiency' => '--%',
        'projects_count' => '--',
        'ai_insights_loading' => 'AI analysis is running...',
        'project_timeline_loading' => 'Loading recent projects...',
    ],

    'sections' => [
        'business_card' => 'Business Card',
        'performance_dashboard' => 'Performance Dashboard',
        'ai_insights' => 'AI Insights',
        'project_timeline' => 'Project Timeline',
    ],

    'stats' => [
        'total_hours' => 'Total Hours',
        'efficiency' => 'Efficiency',
        'projects_count' => 'Active Projects',
    ],
];
