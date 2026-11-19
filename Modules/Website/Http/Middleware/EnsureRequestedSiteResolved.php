<?php

declare(strict_types=1);

namespace Modules\Website\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * CLA-705: fail closed when the caller names a site that does not resolve.
 *
 * ResolveRequestSite deliberately falls back to Claesen for an unknown or
 * suspended `?site=` (the public read endpoints must keep working). For the
 * consultation intake that fallback is wrong: the Electro Bertels landing's
 * proxy pins `?site=electro-bertels`, so a suspended or mistyped site would
 * silently file a Bertels visitor's personal data under Claesen and mail it
 * from Claesen's sender.
 *
 * Runs before the controller, so nothing is stored, queued or replayed from
 * the idempotency cache. A request without `?site=` is never touched. The
 * client-supplied value is not logged.
 */
class EnsureRequestedSiteResolved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('site');

        if (filled($requested) && app(OrganizationContext::class)->site()?->key !== $requested) {
            Log::warning('Website intake rejected: the requested site is not an active site.');

            return response()->json(['message' => 'Intake temporarily unavailable.'], 503);
        }

        return $next($request);
    }
}
