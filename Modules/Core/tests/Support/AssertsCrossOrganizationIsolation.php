<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Modules\Core\Models\User;

/**
 * F1/CLA-457 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Reusable isolation assertions against the two enforcement mechanisms that
 * are actually active in this repo today (with organizations.enforce on):
 *
 *  - Module-level (P5b, RequireOrganization / `organization:{slug}` route
 *    middleware) — an entire module belongs to one organization; any route in
 *    it rejects a user from any other organization, regardless of which row
 *    (or no row at all) the request touches. Covers list/view/create/update/
 *    delete via plain HTTP verb + URI, and "direct URL" access is exactly
 *    what these assertions exercise (no UI navigation involved).
 *
 *  - Panel-level (P5a, User::canAccessPanel()) — an entire Filament panel
 *    belongs to one organization (`admin` = Claesen) or one role
 *    (`bertels` = super_admin only, CLA-549 spike).
 *
 * Row-level isolation (a user IN the owning organization but not entitled to
 * a specific row) is a different, already-covered concern — FieldOps tenant
 * scoping (FieldOpsTenantService, CLA-266/369/375) and Website's future
 * site_id scoping (F3) — deliberately out of scope for this trait, which
 * isolates the organization-boundary variable specifically.
 */
trait AssertsCrossOrganizationIsolation
{
    /**
     * Asserts that $outsider — authenticated, but not a member of the
     * organization that owns this module/route — is rejected with 403,
     * regardless of the module's own ability/tenant checks (the harness
     * grants no permissions to $outsider on purpose: an outsider must be
     * rejected before any ability check even runs, per RequireOrganization's
     * position in the middleware stack).
     */
    protected function assertModuleRouteRejectsOutsider(User $outsider, string $method, string $uri, array $data = []): void
    {
        $response = $this->actingAs($outsider)->json($method, $uri, $data);

        $response->assertForbidden();
    }

    /**
     * Asserts that $owner — a member of the organization that owns this
     * module/route, with whatever abilities $abilities grants — is NOT
     * rejected on organization grounds. Deliberately does not assert a
     * specific success status: the module's own business-logic validation
     * (e.g. a 422 on an incomplete payload) is not this trait's concern,
     * only that the response isn't the 403 an outsider would get.
     *
     * @param  string[]  $abilities
     */
    protected function assertModuleRouteAllowsOwner(User $owner, string $method, string $uri, array $data = [], array $abilities = []): void
    {
        if ($abilities !== [] && method_exists($this, 'grantAbilities')) {
            $this->grantAbilities($owner, $abilities);
        }

        $response = $this->actingAs($owner)->json($method, $uri, $data);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            "Expected the organization's own member not to be rejected by organization enforcement on {$method} {$uri}, got 403."
        );
    }

    /**
     * Manipulated-parameter variant: hits the SAME uri template with an id
     * belonging to a row that exists in a different organization's data,
     * confirming the module-level gate rejects on the requester's own
     * organization membership before any route-model binding is even
     * resolved against that foreign row.
     */
    protected function assertModuleRouteRejectsOutsiderRegardlessOfId(User $outsider, string $method, string $uriTemplate, int|string $foreignId, array $data = []): void
    {
        $uri = str_replace('{id}', (string) $foreignId, $uriTemplate);

        $this->assertModuleRouteRejectsOutsider($outsider, $method, $uri, $data);
    }

    protected function assertPanelRejectsOutsider(User $outsider, string $panelPath): void
    {
        $response = $this->actingAs($outsider)->get($panelPath);

        $response->assertForbidden();
    }

    protected function assertPanelAllowsOwner(User $owner, string $panelPath): void
    {
        $response = $this->actingAs($owner)->get($panelPath);

        $response->assertOk();
    }
}
