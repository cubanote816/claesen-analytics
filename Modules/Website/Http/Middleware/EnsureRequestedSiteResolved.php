<?php

declare(strict_types=1);

namespace Modules\Website\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Site;
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
 * the idempotency cache. A request without the `site` parameter is never
 * touched. The parameter counts as present even when blank (Laravel turns
 * `site=` into null), and the resolved site must be ACTIVE: the final Claesen
 * fallback of ResolveRequestSite does not check status. The client-supplied
 * value is not logged.
 */
class EnsureRequestedSiteResolved
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->query->has('site')) {
            return $next($request);
        }

        $requested = $request->query('site');
        $site = app(OrganizationContext::class)->site();

        if (
            $this->siteSelectorCount((string) $request->server('QUERY_STRING')) !== 1
            || ! is_string($requested)
            || $site?->key !== $requested
            || $site->status !== Site::STATUS_ACTIVE
        ) {
            Log::warning('Website intake rejected: the requested site is not an active site.');

            return response()->json(['message' => 'Intake temporarily unavailable.'], 503);
        }

        return $next($request);
    }

    /**
     * PHP keeps only the last of repeated `site` parameters, so an earlier
     * selector would be silently discarded. More than one selector is
     * ambiguous and fails closed.
     *
     * Each raw pair is parsed on its own with PHP's own parser, so the count
     * sees exactly the names PHP sees: it normalizes them (leading spaces
     * dropped, cut at NUL, `site[...]` as an array, CLA-706) and imitating
     * those rules by hand missed spellings.
     */
    private function siteSelectorCount(string $queryString): int
    {
        $count = 0;

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            parse_str($pair, $parsed);

            if (array_key_exists('site', $parsed)) {
                $count++;
            }
        }

        return $count;
    }
}
