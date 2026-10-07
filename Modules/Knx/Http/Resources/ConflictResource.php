<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;

/**
 * `Conflict` of docs/BACKEND-API.md §3 — the heart of the Conflictencentrum.
 *
 * Two fields are computed rather than stored, because both are questions about
 * the rest of the project:
 *   - `takenAddresses` — the addresses that cannot be proposed (see
 *     ConflictService::takenAddressesFor()). It is passed in by the controller so
 *     a list of N conflicts does not run N queries per project;
 *   - `history` — the append-only log, which is what ends up as the ETS
 *     correction worklist.
 *
 * @property-read KnxConflict $resource
 */
class ConflictResource extends KnxResource
{
    /**
     * @param  list<string>  $takenAddresses
     */
    public function __construct(KnxConflict $resource, private readonly array $takenAddresses = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'severity' => $this->resource->severity,
            'type' => $this->resource->type,
            'address' => $this->resource->address,
            'projectCode' => $this->resource->project?->code,
            'projectName' => $this->resource->project?->name,
            'deviceExisting' => $this->resource->device_existing,
            'deviceField' => $this->resource->device_field,
            'reportedBy' => $this->resource->reportedBy?->shortName(),
            'reportedAt' => $this->resource->reported_at->toIso8601ZuluString(),
            'note' => $this->resource->note,
            'photoUrl' => $this->photoUrl(),
            'status' => $this->resource->status,
            'proposal' => $this->resource->proposal,
            'takenAddresses' => array_values($this->takenAddresses),
            'history' => $this->resource->logs->map(fn (KnxConflictLog $log): array => array_filter([
                'at' => $log->at->toIso8601ZuluString(),
                'action' => $log->action,
                // Optional in the contract: only the ETS worklist lines carry one.
                'address' => $log->address,
            ], static fn ($value): bool => $value !== null))->values()->all(),
        ];
    }

    private function photoUrl(): ?string
    {
        $path = $this->resource->photo_path;

        if ($path === null || $path === '') {
            return null;
        }

        // Provisional: the office app only needs *a* URL today, and the seeded
        // fixture has no photo. The real media pipeline (private originals,
        // conversions, signed temporary URLs) is K8's problem for documents and
        // will be reused here.
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
