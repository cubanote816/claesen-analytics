<?php

return [
    'name' => 'Core',

    // Email domain required for Azure OAuth login. Only employees with this domain
    // can be provisioned as backoffice users. Overridable via COMPANY_EMAIL_DOMAIN.
    'company_email_domain' => env('COMPANY_EMAIL_DOMAIN', 'claesen-verlichting.be'),
];
