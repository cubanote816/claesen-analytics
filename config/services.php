<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_geocoding' => [
        'key' => env('GOOGLE_GEOCODING_API_KEY'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'url' => env('GEMINI_API_URL'),
        // CLA-407 — org policy blocks simple API keys for Gemini; auth is via
        // a dedicated service account's JSON credentials instead (OAuth2
        // JWT-bearer flow), see GoogleServiceAccountAuthService.
        'service_account_path' => env(
            'GEMINI_SERVICE_ACCOUNT_PATH',
            storage_path('app/private/credentials/gemini-service-account.json')
        ),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'vision_model' => env('ANTHROPIC_VISION_MODEL', 'claude-sonnet-5'),
    ],

    // CLA-440 — frame-type illustration generation (CLA-390 Fase 3) moved
    // from Gemini 2.5 Flash Image to OpenAI: real side-by-side comparison
    // showed Gemini's output far below usable catalog quality, while
    // gpt-image-2 also gives native alpha transparency (no chroma-key
    // post-processing needed, unlike Gemini). See OpenAiImageGenerationService.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'low'),
        // "low" measured at 25-28s for the real 7-image payload (6 catalog
        // references + technician photo) vs 56-61s at "medium" — the public
        // proxy in front of this API (sbapu03, backend.claesen-verlichting.be
        // /api/ block) has proxy_read_timeout 60s, so "medium" left no safety
        // margin and one real run already exceeded it.
        'name_model' => env('OPENAI_NAME_MODEL', 'gpt-5.4-mini-2026-03-17'),
    ],

    'github' => [
        'webhook_pat' => env('GITHUB_WEBHOOK_PAT'),
        'portfolio_repo' => env('GITHUB_PORTFOLIO_REPO', 'totti/claesen-astro'),
        'action_webhook_url' => env('GITHUB_ACTION_WEBHOOK_URL'),
        'action_token' => env('GITHUB_ACTION_TOKEN'),
    ],

    'azure' => [
        'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/microsoft/callback',
        'public_redirect' => env('MICROSOFT_AUTH_PUBLIC_REDIRECT'),
        'tenant' => env('MICROSOFT_GRAPH_TENANT_ID'),
        'proxy' => env('PROXY'),
    ],

];
