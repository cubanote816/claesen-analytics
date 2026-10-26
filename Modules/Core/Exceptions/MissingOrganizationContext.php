<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

/**
 * F1/P5 (F3/CLA-471) of the multi-organization program —
 * docs/ai/adr-multi-organization.md, decision D4's own pseudocode for
 * BelongsToSite's global scope.
 *
 * Raised when config('organizations.enforce') is on and a query against a
 * BelongsToSite model runs with no site resolved in
 * Modules\Core\Services\OrganizationContext — a bug (a code path that
 * queries a site-owned model without going through a site-resolving
 * middleware or an authenticated, organization-bound user), never a
 * legitimate "show nothing" case. A silent empty-result scope would hide
 * exactly that bug; this fails loud instead.
 */
class MissingOrganizationContext extends \RuntimeException
{
    public function __construct(string $modelClass)
    {
        parent::__construct(
            'organizations.enforce is on but no site is resolved in the current context '
            ."for [{$modelClass}]. A site-owned model was queried outside a site-resolving "
            .'middleware (e.g. Modules\\Core\\Http\\Middleware\\ResolveRequestSite) or an '
            .'organization-bound authenticated context.'
        );
    }
}
