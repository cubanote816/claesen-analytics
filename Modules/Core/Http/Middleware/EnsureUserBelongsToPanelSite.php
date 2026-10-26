<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Modules\Core\Models\Site;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLA-598 (ADR D1/D5): a panel manages one site; only that site's organization
 * (or super_admin, via the audited panel switch) may work inside it. Gated by
 * the same flag as every other enforcement layer (D4) — with it off nothing
 * changes, because users without a backfilled organization_id would otherwise
 * be locked out.
 */
class EnsureUserBelongsToPanelSite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('organizations.enforce')) {
            return $next($request);
        }

        $user = Filament::auth()->user();
        $site = Site::forPanel();

        if ($user !== null && $site !== null && ! $user->hasRole('super_admin')) {
            abort_unless($user->organization_id === $site->organization_id, 403);
        }

        return $next($request);
    }
}
