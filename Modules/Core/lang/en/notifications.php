<?php

declare(strict_types=1);

// Copy for the core's outgoing mail. The language comes from the recipient
// (`User::preferredLocale()`), never from the panel or the app default: in the queue
// there is no request, so the default won the day and a Dutch user got English.
//
// Vocabulary follows the password screens in `auth.php` so the mail and the screen
// say the same thing about the same link.
return [
    'password_reset' => [
        'subject' => 'Reset your password — :company',
        'greeting' => 'Hello :name,',
        'line_request' => 'We received a request to reset your password. This link expires in 60 minutes.',
        'action' => 'Reset password',
        'line_ignore' => 'If you did not request this, you can safely ignore this email — your password will not change.',
        // Own greeting and salutation on purpose: Laravel's are its own templates —
        // the default greeting is "Hello!" (a key nobody translates) and the
        // salutation prints `config('app.name')`, which on a two-company instance
        // cannot be both companies' name.
        'salutation' => 'Kind regards,',
    ],
    // CLA-685: welcome email of an External worker; the 72 comes from
    // PasswordResetService::WELCOME_CODE_TTL_MINUTES.
    'account_activation' => [
        'subject' => 'Create your password — :company',
        'greeting' => 'Hello :name,',
        'line_intro' => 'An account has been created for you on the :company backoffice. Use the button below to choose your password.',
        'action' => 'Create password',
        'line_validity' => 'This link is valid for :hours hours and can only be used once.',
        'line_network' => 'The backoffice is only reachable from the private network: connect through the VPN or office network.',
        'line_expired' => 'If the link has expired, ask an administrator to resend the activation email.',
        'salutation' => 'Kind regards,',
    ],
    'sign_in_code' => [
        'greeting' => 'Hello :name,',
        'salutation' => 'Kind regards,',
    ],
];
