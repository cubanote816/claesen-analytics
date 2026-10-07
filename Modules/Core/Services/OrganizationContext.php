<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Organization resolution is resolve-only from the authenticated user, as it
 * always was. Site resolution (F3/CLA-471) is the first real consumer:
 * Modules\Core\Http\Middleware\ResolveRequestSite explicitly sets it for the
 * public Website API (no authenticated user exists there to derive an
 * organization from), and Modules\Core\Models\Concerns\BelongsToSite's
 * global scope reads siteId() to filter site-owned models when
 * config('organizations.enforce') is on.
 *
 * Registered `scoped`, never `singleton` (ADR decision D6): the queue
 * worker resets scoped bindings between jobs (Worker::runNextJob,
 * vendor/laravel/framework/.../Queue/Worker.php:246), so a singleton here
 * would leak one job's organization into the next job processed by the same
 * worker process. A job-level middleware/Queue::after defense is added when
 * phase P3 gives this class an actual consumer — not needed while it's inert.
 */
class OrganizationContext
{
    private bool $resolved = false;

    private ?Organization $organization = null;

    private bool $siteResolved = false;

    private ?Site $site = null;

    public function resolve(): ?Organization
    {
        if (! $this->resolved) {
            $this->organization = Auth::user()?->organization;
            $this->resolved = true;
        }

        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->resolve()?->id;
    }

    /**
     * Explicitly sets the site for this request/job — the only way a site
     * gets set outside an authenticated context (the public Website API has
     * no user to derive one from). Once set, it always wins over the
     * organization-derived fallback below, even to null.
     */
    public function setSite(?Site $site): void
    {
        $this->site = $site;
        $this->siteResolved = true;
    }

    /**
     * Falls back to the authenticated user's own organization's site when
     * nothing called setSite() — every organization has exactly one site
     * today (D1), so this is safe for authenticated, non-public contexts
     * (the admin panel) without needing its own resolution middleware.
     */
    public function site(): ?Site
    {
        if ($this->siteResolved) {
            return $this->site;
        }

        // CLA-598: inside a Filament panel the panel decides the site (ADR D1), not
        // the user's own organization — a super_admin working in the Bertels panel
        // must not silently keep Claesen's site. Never falls back to Claesen when a
        // mapped panel's site row does not exist yet.
        $panelId = Filament::getCurrentPanel()?->getId();

        if ($panelId !== null && config("organizations.panel_sites.{$panelId}") !== null) {
            return Site::forPanel($panelId);
        }

        return $this->resolve()?->sites()->first();
    }

    public function siteId(): ?int
    {
        return $this->site()?->id;
    }
}
