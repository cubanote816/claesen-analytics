<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * The public Website API has no authenticated user, so OrganizationContext
 * has nothing to derive a site from on its own — this middleware is the
 * explicit resolver for that path (D4's "estructura → contexto →
 * autorización → enforcement", contexto step for the one part of the app
 * that isn't request-authenticated).
 *
 * Resolution order, each an "is this site allowed to be served" check, not
 * an authorization boundary — the data behind it is public by definition:
 *
 *  1. An explicit `?site={key}` query param — lets a known caller ask for a
 *     specific site's public data directly (useful for local/dev, and for
 *     any client that can't rely on Host matching a configured domain).
 *  2. The request's Host against `sites.domain` — the real production path,
 *     once a Bertels domain is configured there (F4/F5).
 *  3. Claesen's own site — the fallback. Every existing consumer (the Astro
 *     frontend at backend.claesen-verlichting.be) sends neither of the
 *     above, so this keeps their behaviour byte-for-byte unchanged.
 *
 * Only an active site is ever resolved — a suspended site (D7, the rollback
 * mechanism: Bertels gets suspended, never deleted) falls through to
 * Claesen exactly like an unrecognised one, rather than serving a org that
 * has been taken down.
 */
class ResolveRequestSite
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(OrganizationContext::class)->setSite($this->resolveSite($request));

        return $next($request);
    }

    private function resolveSite(Request $request): Site
    {
        if (filled($key = $request->query('site'))) {
            $bySlug = $this->activeSite('key', (string) $key);

            if ($bySlug !== null) {
                return $bySlug;
            }
        }

        $byDomain = $this->activeSite('domain', $request->getHost());

        if ($byDomain !== null) {
            return $byDomain;
        }

        return Site::query()->findOrFail(Site::claesenId());
    }

    private function activeSite(string $column, string $value): ?Site
    {
        return Site::query()
            ->where($column, $value)
            ->where('status', Site::STATUS_ACTIVE)
            ->first();
    }
}
