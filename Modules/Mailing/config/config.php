<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sending throttle
    |--------------------------------------------------------------------------
    | Delay in milliseconds between individual sends inside ExecuteCampaignJob.
    | Keeps Microsoft Graph rate limits happy without blocking a full worker.
    */
    'send_delay_ms' => env('MAILING_SEND_DELAY_MS', 500),

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
    */
    'from_address' => env('MAIL_FROM_ADDRESS', 'info@claesen-verlichting.be'),
    'from_name'    => env('MAIL_FROM_NAME', 'Claesen Verlichting'),

    /*
    |--------------------------------------------------------------------------
    | NDR bounce inbox
    |--------------------------------------------------------------------------
    | Dedicated mailbox that receives Non-Delivery Reports (NDRs).
    | The Graph app must have Mail.Read permission for this mailbox.
    | Processed messages are marked as read (not deleted).
    |
    | MAI-029: add X-Mailing-Token header to outgoing emails for exact NDR
    | correlation with mailing_messages. Until then, correlation is by email only.
    */
    'ndr_inbox'      => env('MAILING_NDR_INBOX', 'bounces@claesen-verlichting.be'),
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
