<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending throttle
    |--------------------------------------------------------------------------
    | Delay in milliseconds between individual sends inside ExecuteCampaignJob.
    | Keeps Microsoft Graph rate limits happy without blocking a full worker.
    */
    'send_delay_ms' => env('MAILING_SEND_DELAY_MS', 2000),

    /*
    |--------------------------------------------------------------------------
    | Run time budget
    |--------------------------------------------------------------------------
    | Seconds an inline ExecuteCampaignJob run may keep starting new sends before it
    | re-dispatches itself with the same dispatch key. Must stay below the queue
    | job $timeout (85 s) and the redis retry_after (90 s). Worst case per run:
    | 20 budget + 30 Graph request + 15 OAuth + 2 delay + startup < 85 < 90.
    */
    'run_budget_seconds' => env('MAILING_RUN_BUDGET_SECONDS', 20),

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph transient retries (CLA-681)
    |--------------------------------------------------------------------------
    | Only HTTP 429/503 are retried; 502/504 and connection timeouts never are
    | (Graph may have accepted the mail — a retry could deliver it twice).
    | graph_retry_attempts: retries after the first try (0 disables).
    | graph_retry_backoff_seconds: wait before retry 1, 2, ... when Graph sends no
    | Retry-After header (a Retry-After is honoured, capped at 10 s). A retry whose
    | wait would pass the run budget above is skipped.
    */
    'graph_retry_attempts'        => (int) env('MAILING_GRAPH_RETRY_ATTEMPTS', 2),
    'graph_retry_backoff_seconds' => [1, 3],
    // Runs without a send budget (existing campaigns, A/B): no retry starts after this many
    // seconds from the run start, so retries cannot add up past the job timeout (85 s).
    'graph_retry_window_seconds'  => 20,

    /*
    |--------------------------------------------------------------------------
    | Bounce thresholds
    |--------------------------------------------------------------------------
    | soft_limit: number of soft bounces before auto-suppression.
    | hard_bounce_alert: ratio that triggers a deliverability alert (5 %).
    */
    'bounce_soft_limit'  => 3,
    'hard_bounce_alert'  => 0.05,

    /*
    |--------------------------------------------------------------------------
    | Spam complaint alert threshold
    |--------------------------------------------------------------------------
    | Gmail/Yahoo flag senders above 0.08 % complaint rate.
    | At 0.08 % an alert is shown in the Filament dashboard.
    */
    'spam_rate_alert' => 0.0008,

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    */
    'unsubscribe_domain' => env('MAILING_UNSUBSCRIBE_DOMAIN', 'claesen-verlichting.be'),
    'tracking_domain'    => env('MAILING_TRACKING_DOMAIN', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Default sender
    |--------------------------------------------------------------------------
    | Campaign sender (CLA-656). Falls back to the global mail sender until
    | MAILING_FROM_ADDRESS / MAILING_FROM_NAME are set. reply_to falls back to
    | mail.from.address at runtime (ProspectCampaignMail).
    */
    'from_address' => env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'info@claesen-verlichting.be')),
    'from_name'    => env('MAILING_FROM_NAME', env('MAIL_FROM_NAME', 'Claesen Verlichting')),
    'reply_to'     => env('MAILING_REPLY_TO'),

    /*
    |--------------------------------------------------------------------------
    | NDR bounce inbox
    |--------------------------------------------------------------------------
    | Mailbox that receives Non-Delivery Reports (NDRs). NDRs return to the
    | sender, so it defaults to the campaign sender (CLA-656).
    | The Graph app must have Mail.Read permission for this mailbox.
    | Processed messages are marked as read (not deleted).
    |
    | MAI-029: add X-Mailing-Token header to outgoing emails for exact NDR
    | correlation with mailing_messages. Until then, correlation is by email only.
    */
    'ndr_inbox'      => env('MAILING_NDR_INBOX', env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'info@claesen-verlichting.be'))),
    // Non-bounce messages found in the NDR inbox are moved here (left unread) so they
    // never starve the batch of unread inbox messages.
    'ndr_unprocessed_folder' => env('MAILING_NDR_UNPROCESSED_FOLDER', 'Mailing - not processed'),
    'ndr_batch_size' => env('MAILING_NDR_BATCH_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Contact preference categories
    |--------------------------------------------------------------------------
    | Defines the categories a prospect can opt out of individually.
    | Keys are persisted in mailing_contact_preferences.category.
    | Labels and descriptions are locale-keyed for the preferences page.
    */
    /*
    |--------------------------------------------------------------------------
    | A/B test minimum sample size
    |--------------------------------------------------------------------------
    | Minimum number of sent messages per variant before selecting a winner.
    | If below this, a warning is logged but selection proceeds (MVP behaviour).
    */
    'ab_min_sample' => 5,

    /*
    |--------------------------------------------------------------------------
    | Deliverability alert window
    |--------------------------------------------------------------------------
    | How many days back to evaluate completed campaigns for threshold violations.
    */
    'alert_check_days' => 7,

    'preference_categories' => [
        'newsletter' => [
            'label' => [
                'nl' => 'Nieuwsbrief',
                'en' => 'Newsletter',
            ],
            'description' => [
                'nl' => 'Nieuws over onze projecten, producten en diensten.',
                'en' => 'News about our projects, products and services.',
            ],
        ],
        'offers' => [
            'label' => [
                'nl' => 'Aanbiedingen',
                'en' => 'Offers',
            ],
            'description' => [
                'nl' => 'Speciale aanbiedingen en seizoenspromoties.',
                'en' => 'Special offers and seasonal promotions.',
            ],
        ],
        'events' => [
            'label' => [
                'nl' => 'Evenementen',
                'en' => 'Events',
            ],
            'description' => [
                'nl' => 'Uitnodigingen voor beurzen en evenementen.',
                'en' => 'Invitations to trade fairs and events.',
            ],
        ],
    ],

];
