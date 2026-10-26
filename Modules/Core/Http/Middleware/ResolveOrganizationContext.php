<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * F1/P4 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Built ahead of P3 (site_id on Website's shared domain) because it doesn't
 * depend on it: OrganizationContext already resolves directly from
 * users.organization_id (phase P2), never touches site_id. Documented as an
 * intentional out-of-order build in the ADR's phase table.
 *
 * Purely eager-resolves OrganizationContext for the authenticated user —
 * nothing here restricts, redirects, or 404s. Same pattern as
 * UpdateUserActivity (appended to the `web` group, after session/auth are
 * resolved) — covers both ordinary panel requests and Livewire's own AJAX
 * round-trips, since both are full requests through the `web` group.
 *
 * A guest request is a no-op: OrganizationContext::resolve() already
 * returns null for Auth::user() === null, so there's nothing this
 * middleware needs to guard against beyond calling it.
 *
 * CORRECTION (F2/CLA-465, found while building AssignCorrelationId): the
 * "covers both ordinary panel requests..." claim above was never actually
 * true for the real admin/bertels panels — both PanelProviders build their
 * OWN explicit ->middleware([...]) array rather than referencing the `web`
 * group alias, so bootstrap/app.php's $middleware->web(append: [...]) never
 * reached them. Proven with a real request (UpdateUserActivity's own
 * Cache::put() side effect was absent after hitting a panel route before
 * this fix). This class is now ALSO registered directly in both
 * AdminPanelProvider and BertelsPanelProvider's ->middleware([...]) arrays —
 * that registration, not this one, is what actually executes it on panel
 * requests. This entry in bootstrap/app.php's `web` group is kept for any
 * plain (non-panel) route under that group (e.g. routes/web.php).
 */
class ResolveOrganizationContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            app(OrganizationContext::class)->resolve();
        }

        return $next($request);
    }
}
