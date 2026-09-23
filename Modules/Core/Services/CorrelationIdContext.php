<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Str;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Registered `scoped` (Modules\Core\Providers\CoreServiceProvider), never
 * `singleton` — same D6 reasoning as OrganizationContext: a queue worker
 * resets scoped bindings between jobs (Illuminate\Queue\Worker::daemon()),
 * so one job's correlation id never leaks into the next.
 *
 * id() lazily generates a UUID on first access rather than requiring a
 * caller to always set() one first — a console command or a job with no
 * HTTP request in front of it still gets one consistent value for
 * everything it logs during that single invocation, instead of null.
 */
class CorrelationIdContext
{
    private ?string $id = null;

    public function set(string $id): void
    {
        $this->id = $id;
    }

    public function id(): string
    {
        return $this->id ??= (string) Str::uuid();
    }
}
