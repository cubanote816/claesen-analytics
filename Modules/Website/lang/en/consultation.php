<?php

declare(strict_types=1);

// CLA-705: acknowledgement e-mail sent to the visitor after the website contact form.
return [
    'confirmation' => [
        'subject' => 'We have received your message (:reference)',
        'greeting' => 'Dear :name,',
        'received' => 'Thank you for your message. It has reached us and there is nothing else you need to do.',
        'reference' => 'Your reference number is :reference. Please mention it if you contact us about this request.',
        'next' => 'A member of our team will review your request and get back to you as soon as possible.',
        'urgent' => 'Urgent? Call us on :phone.',
        'your_message' => 'Your message',
        'reply' => 'Want to add something? Simply reply to this e-mail.',
        'sign_off' => 'Kind regards,',
    ],
];
