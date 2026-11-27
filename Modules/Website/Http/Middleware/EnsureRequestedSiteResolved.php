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
        // Judged on the raw query string too: PHP drops parameters past
        // max_input_vars, so a `site` it discarded would otherwise look absent
        // and fall back to Claesen.
        $selectors = $this->siteSelectorCount((string) $request->server('QUERY_STRING'));

        if ($selectors === 0 && ! $request->query->has('site')) {
            return $next($request);
        }

        $requested = $request->query('site');
        $site = app(OrganizationContext::class)->site();

        if (
            $selectors !== 1
            || ! $request->query->has('site')
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
     * Counted on the raw names with PHP's own normalization of a name that
     * starts with `site` (CLA-706): leading spaces dropped (`%20site`,
     * `+site`), cut at NUL (`site%00x`), and `site[...]` is the same
     * parameter as an array, however deep. Deliberately not parse_str():
     * PHP drops (and warns about) parameters past max_input_vars or
     * max_input_nesting_level, which would hide exactly the selectors this
     * counter must see.
     */
    private function siteSelectorCount(string $queryString): int
    {
        $count = 0;

        // Split on the separators PHP itself uses (arg_separator.input, `&` by
        // default) so one chunk never hides two selectors.
        $separators = (string) ini_get('arg_separator.input');
        $pattern = '/['.preg_quote($separators !== '' ? $separators : '&', '/').']/';

        foreach (preg_split($pattern, $queryString) ?: [] as $pair) {
            if ($pair === '') {
                continue;
            }

            $name = ltrim(urldecode(explode('=', $pair, 2)[0]), ' ');
            $name = explode("\0", $name, 2)[0];

            if ($name === 'site' || str_starts_with($name, 'site[')) {
                $count++;
            }
        }

        return $count;
    }
}
