<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;
use Modules\Core\Support\AuthenticatedVia;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLA-684 — every panel session that was not signed in through Microsoft must
 * have a second factor configured, whatever the user's role. CLA-464 (ADR D8)
 * first limited this to super_admin/admin; CLA-684 dropped that role gate after
 * a Viewer signed in to production with email + password and no second factor.
 *
 * Filament's own `requiresMultiFactorAuthentication()` flag cannot express the
 * Microsoft exemption: `Panel::isMultiFactorAuthenticationRequired()` is
 * evaluated once at boot (Panel\Concerns\HasComponents/HasRoutes, to decide
 * whether to register the setup-required route/middleware at all), not
 * per-request, so a Closure passed there never sees the session.
 *
 * This middleware is registered as the panel's
 * multiFactorAuthenticationRequiredMiddlewareName() instead, so the panel
 * itself is configured with requiresMultiFactorAuthentication(true) (needed
 * for Filament to register the route/middleware at all) while the per-request
 * decision happens here: skip Microsoft sessions, otherwise delegate to
 * Filament's own EnsureMultiFactorAuthenticationIsEnabled — the real TOTP/email
 * "is this user's MFA set up" check is never reimplemented here.
 *
 * Sessions signed in through the Microsoft callback are skipped
 * (AuthenticatedVia): Entra ID already enforces its own second factor there, so
 * asking for the app MFA on top is friction without security value. The marker
 * is server-side session state set only by that callback; an email + local
 * password sign-in — even for an account that also has a microsoft_id — never
 * carries it, so it keeps the set-up requirement and the Login page challenge.
 *
 * Password logins outside the Filament panels (API tokens, Sport, Safety, KNX)
 * never pass through here; they are tracked in CLA-691.
 */
class EnsureLocalPasswordMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('core.panel_mfa_enforced', true)) {
            return $next($request);
        }

        if ($request->hasSession() && AuthenticatedVia::isMicrosoft($request->session())) {
            return $next($request);
        }

        return app(EnsureMultiFactorAuthenticationIsEnabled::class)->handle($request, $next);
    }
}
