<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Services\CorrelationIdContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Same pattern/placement as ResolveOrganizationContext (appended to the
 * `web` group, after session/auth are resolved) — covers ordinary panel
 * requests and Livewire's own AJAX round-trips. Reuses an incoming
 * X-Correlation-Id header when a caller/proxy already sets one, otherwise
 * generates a fresh UUID per request.
 *
 * Deliberately sets the context BEFORE calling $next($request) and never
 * touches the response afterward — verified in a real request against a
 * Filament panel route (not just the middleware in isolation) that several
 * of this panel's own middlewares (EnsurePasswordIsSet, the MFA
 * setup-required redirect, etc.) redirect via a thrown exception/abort
 * rather than a clean return, which skips any code a middleware places
 * AFTER its own $next() call — the exact same reason
 * ResolveOrganizationContext's side effect also runs before $next(), not
 * after. Any activity logged further down the stack — including from
 * exception-handling code itself — still gets the correlation id this way.
 */
class AssignCorrelationId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Correlation-Id') ?: (string) Str::uuid();

        app(CorrelationIdContext::class)->set($id);

        return $next($request);
    }
}
