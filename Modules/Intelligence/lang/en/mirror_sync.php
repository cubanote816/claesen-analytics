<?php

return [
    'navigation_label' => 'Mirror Sync Status',
    'title' => 'Mirror Sync Status',
    'description' => 'The mirror keeps a local copy of the CAFCA ERP data used across the panel (Safety, Performance, FieldOps). It refreshes automatically every day at 04:00, and can also be refreshed manually here.',
    'labels' => [
        'last_sync' => 'Last successful sync',
        'never_synced' => 'Never synced',
        'triggered_by_scheduled' => 'Automatic (daily schedule)',
        'triggered_by_manual' => 'Manual, by :name',
        'duration' => 'Duration',
        'recent_runs' => 'Recent runs',
        'error' => 'Error',
    ],
    'status' => [
        'running' => 'Running',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'actions' => [
        'refresh_now' => 'Refresh now',
        'sync_employees' => 'Sync employees',
        'sync_clients' => 'Sync clients',
        'sync_complexes' => 'Sync complexes',
        'sync_all_manual' => 'Sync all (employees + clients + complexes)',
    ],
    'tasks' => [
        'employees' => 'Employees',
        'clients' => 'Clients',
        'complexes' => 'Complexes',
        'all' => 'Employees, clients and complexes',
    ],
    'notifications' => [
        'queued_title' => 'Sync queued',
        'queued_body' => 'The refresh is running in the background — this page updates automatically.',
        'already_running_title' => 'Sync already running',
        'already_running_body' => 'A sync is already in progress. Wait for it to finish before starting another one.',
        'failed_title' => 'Could not queue sync',
        'manual_sync_done_title' => 'Sync complete',
        'manual_sync_done_body' => ':task synced successfully.',
        'manual_sync_failed_title' => 'Sync failed',
    ],
];
