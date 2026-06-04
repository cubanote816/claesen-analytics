<?php

/*
|--------------------------------------------------------------------------
| Static Site Auto-Publish Configuration
|--------------------------------------------------------------------------
|
| Controls the automatic rebuild pipeline from this Laravel backend to the
| Astro static frontend. When a content change is saved (projects, media,
| translations…), the backend queues a job that calls the frontend's
| /rebuild webhook after a debounce window.
|
| The backend is responsible for: detecting the change → marking state →
| queueing the job → signing and calling the webhook.
|
| The frontend is responsible for: receiving the webhook → building Astro →
| deploying atomically via releases + symlink.
|
| 202 from the webhook means "request accepted", NOT "site published".
| The real build status is available at STATIC_SITE_HEALTH_URL (/health).
|
| .env variables by environment:
|
|  ── development ───────────────────────────────────────────────────────
|   STATIC_SITE_REBUILD_ENABLED=true
|   STATIC_SITE_ENV=development
|   STATIC_SITE_WEBHOOK_URL=http://127.0.0.1:9000/rebuild
|   STATIC_SITE_HEALTH_URL=http://127.0.0.1:9000/health
|   STATIC_SITE_WEBHOOK_SECRET=dev-secret
|   STATIC_SITE_WEBHOOK_TIMEOUT=3
|   STATIC_SITE_REBUILD_DEBOUNCE_SECONDS=5
|   STATIC_SITE_SIGNATURE_TOLERANCE_SECONDS=300
|
|  ── staging ───────────────────────────────────────────────────────────
|   STATIC_SITE_REBUILD_ENABLED=true
|   STATIC_SITE_ENV=staging
|   STATIC_SITE_WEBHOOK_URL=http://192.168.60.20:9001/rebuild
|   STATIC_SITE_HEALTH_URL=http://192.168.60.20:9001/health
|   STATIC_SITE_WEBHOOK_SECRET=staging-secret-largo
|   STATIC_SITE_WEBHOOK_TIMEOUT=3
|   STATIC_SITE_REBUILD_DEBOUNCE_SECONDS=15
|   STATIC_SITE_SIGNATURE_TOLERANCE_SECONDS=300
|
|  ── production ────────────────────────────────────────────────────────
|   STATIC_SITE_REBUILD_ENABLED=true
|   STATIC_SITE_ENV=production
|   STATIC_SITE_WEBHOOK_URL=http://192.168.60.20:9000/rebuild
|   STATIC_SITE_HEALTH_URL=http://192.168.60.20:9000/health
|   STATIC_SITE_WEBHOOK_SECRET=production-secret-largo
|   STATIC_SITE_WEBHOOK_TIMEOUT=3
|   STATIC_SITE_REBUILD_DEBOUNCE_SECONDS=20
|   STATIC_SITE_SIGNATURE_TOLERANCE_SECONDS=300
|
*/

return [

    /*
     | Master switch. Set to false to disable all rebuild triggers without
     | removing the rest of the configuration.
     */
    'enabled' => env('STATIC_SITE_REBUILD_ENABLED', false),

    /*
     | Identifies which environment is sending the webhook payload.
     | Falls back to APP_ENV so development environments that don't set
     | STATIC_SITE_ENV explicitly still report the correct value.
     */
    'environment' => env(
        'STATIC_SITE_ENV',
        env('APP_ENV', 'production')
    ),

    /*
     | Full URL to the frontend's /rebuild endpoint.
     | Example: http://192.168.60.20:9000/rebuild
     */
    'webhook_url' => env('STATIC_SITE_WEBHOOK_URL'),

    /*
     | Shared secret used to sign HMAC-SHA256 webhook requests.
     | The frontend must hold the same value to verify signatures.
     */
    'webhook_secret' => env('STATIC_SITE_WEBHOOK_SECRET'),

    /*
     | HTTP connection timeout (seconds) for the webhook POST call.
     | Keep short — the frontend must respond 202 immediately and process async.
     */
    'webhook_timeout' => (int) env('STATIC_SITE_WEBHOOK_TIMEOUT', 3),

    /*
     | Full URL to the frontend's GET /health endpoint.
     | Used by the Filament publication widget to display real build status.
     | Kept separate from webhook_url so both can be configured independently
     | (e.g. different ports, different ingress rules).
     | Example: http://192.168.60.20:9000/health
     */
    'health_url' => env('STATIC_SITE_HEALTH_URL'),

    /*
     | Debounce window in seconds. Multiple content saves within this window
     | are collapsed into a single rebuild request. The last change wins:
     | each save resets the dispatch_key, invalidating the previous job.
     */
    'debounce_seconds' => (int) env('STATIC_SITE_REBUILD_DEBOUNCE_SECONDS', 20),

    /*
     | Maximum age (seconds) of a webhook timestamp that the frontend will
     | accept. Requests older than this are rejected as potential replays.
     | Default: 300 seconds (5 minutes).
     | Must match WEBHOOK_SIGNATURE_TOLERANCE in the frontend receiver.
     */
    'signature_tolerance_seconds' => (int) env('STATIC_SITE_SIGNATURE_TOLERANCE_SECONDS', 300),

];
