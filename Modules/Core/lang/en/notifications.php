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
    'sign_in_code' => [
        'greeting' => 'Hello :name,',
        'salutation' => 'Kind regards,',
    ],
];
