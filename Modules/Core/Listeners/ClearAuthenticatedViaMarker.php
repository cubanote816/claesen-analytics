<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Modules\Core\Support\AuthenticatedVia;

/**
 * Any sign-in or logout drops the "signed in with Microsoft" marker, so a stale
 * marker can never carry over to a different sign-in. SessionGuard::login()
 * dispatches Login synchronously, before MicrosoftAuthController re-marks the
 * session, so the callback's own Login event cannot wipe the marker it sets
 * afterwards.
 */
final class ClearAuthenticatedViaMarker
{
    public function handle(Login|Logout $event): void
    {
        $request = request();

        if ($request->hasSession()) {
            AuthenticatedVia::clear($request->session());
        }
    }
}
