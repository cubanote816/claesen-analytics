<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * F1/P5b of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Route middleware `organization:{slug}` (ADR §6, P5b: "rutas de módulos
 * Claesen"). Gated by config('organizations.enforce') (D4) — with the flag
 * off (the default in every environment today) this is a no-op, so it
 * changes nothing for Claesen.
 *
 * This is the layer P5a's panel-level gate (User::canAccessPanel()) does
 * not cover: the modules this is applied to are consumed directly by
 * Sanctum-token apps (Claesen-Sport, Safety PWA), never through a Filament
 * panel session, so canAccessPanel() is never in their request path.
 *
 * The route already requires auth:sanctum ahead of this middleware in
 * every group it's applied to, so $request->user() is never null here in
 * practice — but a user with organization_id still null (unassigned,
 * allowed until P7's NOT NULL) fails closed rather than silently passing.
 */
class RequireOrganization
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! config('organizations.enforce')) {
            return $next($request);
        }

        $organizationId = Organization::query()->where('slug', $slug)->value('id')
            ?? throw new \RuntimeException("RequireOrganization: no organization with slug [{$slug}] exists.");

        abort_unless($request->user()?->organization_id === $organizationId, 403);

        return $next($request);
    }
}
