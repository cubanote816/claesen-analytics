<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Server-side marker of how the current web session was signed in.
 *
 * Only the Microsoft callback writes it (right after Auth::login() and the
 * session regenerate); every other sign-in and every logout clears it through
 * Modules\Core\Listeners\ClearAuthenticatedViaMarker. It lives in the session
 * store only, so request input, cookies and headers can never set it. The
 * panels use it to skip the app MFA set-up requirement for Microsoft sessions,
 * since Entra ID already provides the second factor there.
 */
final class AuthenticatedVia
{
    public const SESSION_KEY = 'core.auth_via';

    public const MICROSOFT = 'microsoft';

    public static function markMicrosoft(Session $session): void
    {
        $session->put(self::SESSION_KEY, self::MICROSOFT);
    }

    public static function isMicrosoft(Session $session): bool
    {
        return $session->get(self::SESSION_KEY) === self::MICROSOFT;
    }

    public static function clear(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
