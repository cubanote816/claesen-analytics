<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Organization;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Resolve-only: nothing in the application reads this yet. No middleware
 * wires it, no scope consults it, no route/job resolves it. It only exists
 * so phase P3 has a single, already-tested place to resolve "whose
 * organization is this request/job running as" instead of every consumer
 * reaching for `auth()->user()->organization_id` directly.
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
}
