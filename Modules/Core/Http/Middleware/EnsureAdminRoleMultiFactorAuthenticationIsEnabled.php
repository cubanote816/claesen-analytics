<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLA-464 (ADR D8) — MFA is required for super_admin/admin only, not every
 * role with panel access (financial_manager/hr_manager/viewer/
 * project_manager). Filament's own `requiresMultiFactorAuthentication()`
 * flag has no per-role variant: `Panel::isMultiFactorAuthenticationRequired()`
 * is evaluated once at boot (Panel\Concerns\HasComponents/HasRoutes, to
 * decide whether to register the setup-required route/middleware at all),
 * not per-request — a plain role-checking Closure passed there would always
 * see no authenticated user and never work.
 *
 * This middleware is registered as the panel's
 * multiFactorAuthenticationRequiredMiddlewareName() instead, so the panel
 * itself is configured with requiresMultiFactorAuthentication(true) (needed
 * for Filament to register the route/middleware at all) while the actual
 * per-request decision happens here: skip entirely for any role other than
 * super_admin/admin, otherwise delegate to Filament's own
 * EnsureMultiFactorAuthenticationIsEnabled — the real TOTP/email "is this
 * user's MFA set up" check is never reimplemented here.
 */
class EnsureAdminRoleMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user?->hasAnyRole(['super_admin', 'admin'])) {
            return $next($request);
        }

        return app(EnsureMultiFactorAuthenticationIsEnabled::class)->handle($request, $next);
    }
}
