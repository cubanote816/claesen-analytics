<?php

declare(strict_types=1);

namespace Modules\Analytics\Services;

use Carbon\CarbonInterface;
use Modules\Analytics\Enums\AppSource;
use Modules\Analytics\Enums\EventName;
use Modules\Analytics\Jobs\RecordAppEventJob;
use Modules\Core\Models\User;

/**
 * Single entry point for recording an app_events row, used by the HTTP
 * ingestion endpoint today and available for direct backend calls later
 * (e.g. a Filament observer emitting resource_created/resource_updated).
 * Persistence always goes through the queue so callers never block on it.
 */
class EventTracker
{
    public function track(
        EventName $eventName,
        AppSource $app,
        string $sessionId,
        ?User $user = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $properties = [],
        ?int $durationMs = null,
        ?CarbonInterface $occurredAt = null,
    ): void {
        RecordAppEventJob::dispatch([
            'event_name' => $eventName,
            'app' => $app,
            'user_id' => $user?->id,
            'employee_id' => $user?->employee_id,
            'session_id' => $sessionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'role_snapshot' => $user ? $user->getRoleNames()->values()->all() : null,
            'properties' => $properties,
            'duration_ms' => $durationMs,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
