<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxProject;

/**
 * The project's activity feed (§4.3), built from the rows the domain already
 * keeps instead of a separate event log:
 *
 *   - `conflict_reported`  ← the conflicts of the project
 *   - `devices_registered` ← field devices, grouped per person and day (one entry
 *                            per batch, with `count`, which is what the UI shows:
 *                            "6 apparaten geregistreerd door J. Van Dyck")
 *   - `plan_uploaded`      ← uploaded documents
 *
 * `photos_uploaded` is deliberately absent: nothing in this domain models photos
 * as events (the project only carries a photo *counter*), and inventing an entry
 * would be worse than not showing it. It becomes possible when Veld's photo
 * uploads get their own table.
 *
 * The entries are structured (kind + params) and never translated here: the front
 * composes the sentence, as the contract requires.
 */
class ProjectActivityService
{
    private const LIMIT = 50;

    /**
     * @return list<array<string, mixed>>
     */
    public function forProject(KnxProject $project): array
    {
        $entries = collect()
            ->merge($this->conflictEntries($project))
            ->merge($this->deviceEntries($project))
            ->merge($this->documentEntries($project))
            ->sortByDesc('at')
            ->take(self::LIMIT)
            ->map(function (array $entry): array {
                $entry['at'] = $entry['at']->toIso8601ZuluString();

                return $entry;
            })
            ->values()
            ->all();

        return $entries;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function conflictEntries(KnxProject $project): Collection
    {
        return KnxConflict::query()
            ->where('project_id', $project->id)
            ->with('reportedBy')
            ->get()
            ->map(fn (KnxConflict $conflict): array => array_filter([
                'at' => $conflict->reported_at,
                'kind' => 'conflict_reported',
                'conflictType' => $conflict->type,
                'address' => $conflict->address,
                'who' => $conflict->reportedBy?->shortName(),
            ], static fn ($value): bool => $value !== null));
    }

    /**
     * One entry per (person, day): the office reads it as a batch, and a per-device
     * entry would drown the feed on a 60-device project.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function deviceEntries(KnxProject $project): Collection
    {
        return KnxDevice::query()
            ->where('project_id', $project->id)
            ->where('source', KnxDevice::SOURCE_FIELD)
            ->whereNotNull('registered_at')
            ->with('registeredBy')
            ->get()
            ->groupBy(fn (KnxDevice $device): string => $device->registered_at->toDateString()
                .'|'.($device->registered_by_employee_id ?? '0'))
            ->map(fn (Collection $group): array => array_filter([
                'at' => $group->max('registered_at'),
                'kind' => 'devices_registered',
                'count' => $group->count(),
                'who' => $group->first()->registeredBy?->shortName(),
            ], static fn ($value): bool => $value !== null))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function documentEntries(KnxProject $project): Collection
    {
        return KnxDocument::query()
            ->where('project_id', $project->id)
            ->with('uploadedBy')
            ->get()
            ->map(fn (KnxDocument $document): array => array_filter([
                // `uploaded_at` is a date in the domain; the feed needs an instant.
                'at' => $document->uploaded_at->startOfDay(),
                'kind' => 'plan_uploaded',
                'who' => $document->uploadedBy?->shortName(),
            ], static fn ($value): bool => $value !== null));
    }
}
