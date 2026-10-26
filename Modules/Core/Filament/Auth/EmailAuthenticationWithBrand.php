<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Auth;

use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Modules\Core\Notifications\VerifyEmailAuthenticationWithBrand;

/**
 * Filament's email MFA, sending the code under the user's own company.
 *
 * The only thing this changes is which notification carries the code. Filament's
 * own sets no `from`, so the message inherited the global mailer identity — which
 * on this instance resolves to "Laravel" at Claesen's address, for both companies.
 * Everything else (code generation, hashing, expiry, the session keys) stays the
 * parent's; overriding behaviour here would be re-implementing a security flow to
 * fix a display name.
 *
 * Used by both panels deliberately: which company the mail belongs to comes from
 * the user's organization, not from the panel, so one subclass is correct for both.
 */
class EmailAuthenticationWithBrand extends EmailAuthentication
{
    protected string $codeNotification = VerifyEmailAuthenticationWithBrand::class;
}
