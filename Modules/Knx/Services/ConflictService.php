<?php

namespace Modules\Knx\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Knx\Exceptions\AddressInUseException;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxProject;

/**
 * Treating a conflict, and the two rules that make it safe.
 *
 * 1. **History is append-only.** Every status change writes a log row inside the
 *    same transaction, with the proposed address *at that moment*. That log is
 *    the ETS correction worklist — the whole point of the module — so it can
 *    never be a side effect that might be skipped.
 * 2. **A proposal may not collide with an address in use.** The client already
 *    warns before submitting, but the backend is the authority: `takenAddresses`
 *    is advisory, this is the rule.
 *
 * No state machine is enforced, on purpose. The contract says the transitions the
 * front understands are reversible ("open → in_review, open → ets_listed, open →
 * rejected, y reversibles") and that documenting one is optional; inventing a
 * strict machine here would make the office unable to undo a mis-click. What the
 * backend guarantees is that *every* change is recorded.
 */
class ConflictService
{
    /**
     * Statuses in which a conflict still holds a claim on its proposed address.
     * Once it is closed or rejected, the address is free again.
     */
    private const RELEASED_STATUSES = ['closed', 'rejected'];

    /**
     * @return Collection<int, KnxConflict>
     */
    public function list(?string $status, ?string $projectCode, ?string $term): Collection
    {
        return KnxConflict::query()
            ->with(['project', 'reportedBy', 'logs'])
            ->when($projectCode !== null, fn (Builder $query) => $query->whereHas(
                'project',
                fn (Builder $inner) => $inner->where('code', $projectCode),
            ))
            ->when($status !== null && $status !== 'all', function (Builder $query) use ($status): void {
                // `active` is the office's working set, not a stored status.
                $status === 'active'
                    ? $query->open()
                    : $query->where('status', $status);
            })
            ->when($term !== null && trim($term) !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('address', 'like', $like)
                    ->orWhere('proposal', 'like', $like)
                    ->orWhere('device_existing', 'like', $like)
                    ->orWhere('device_field', 'like', $like)
                    ->orWhere('note', 'like', $like));
            })
            // Severity first (critical → info), then most recent: the contract's
            // order, and the order the office triages in.
            ->ordered()
            ->get();
    }

    /**
     * The addresses a proposal may not use: every apparatus of the project, plus
     * the proposals of conflicts that still hold one.
     *
     * @return list<string>
     */
    public function takenAddressesFor(KnxProject $project, ?KnxConflict $except = null): array
    {
        ['devices' => $devices, 'proposals' => $proposals] = $this->addressClaimsFor($project);

        return $this->mergeClaims($devices, $proposals, $except?->getKey());
    }

    /**
     * The same answer for a whole list, with two queries per project instead of
     * two per conflict.
     *
     * @param  Collection<int, KnxConflict>  $conflicts
     * @return array<int, list<string>> keyed by conflict id
     */
    public function takenAddressesForList(Collection $conflicts): array
    {
        $taken = [];

        foreach ($conflicts->groupBy('project_id') as $group) {
            $project = $group->first()->project;

            if ($project === null) {
                continue;
            }

            ['devices' => $devices, 'proposals' => $proposals] = $this->addressClaimsFor($project);

            foreach ($group as $conflict) {
                $taken[$conflict->getKey()] = $this->mergeClaims($devices, $proposals, $conflict->getKey());
            }
        }

        return $taken;
    }

    /**
     * @return array{devices: Collection<int, string>, proposals: Collection<int, string>}
     */
    private function addressClaimsFor(KnxProject $project): array
    {
        return [
            'devices' => KnxDevice::query()
                ->where('project_id', $project->id)
                ->pluck('address'),
            // Keyed by conflict id so a conflict can be left out of its own list
            // without a second query per row.
            'proposals' => KnxConflict::query()
                ->where('project_id', $project->id)
                ->whereNotIn('status', self::RELEASED_STATUSES)
                ->whereNotNull('proposal')
                ->pluck('proposal', 'id'),
        ];
    }

    /**
     * @param  Collection<int, string>  $devices
     * @param  Collection<int, string>  $proposals  keyed by conflict id
     * @return list<string>
     */
    private function mergeClaims(Collection $devices, Collection $proposals, ?int $exceptId): array
    {
        $others = $proposals->reject(fn (string $address, int|string $id): bool => $exceptId !== null && (int) $id === $exceptId);

        return $devices
            ->merge($others->values())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Applies a patch: the proposed address and/or a new status.
     *
     * The proposal is only validated when it actually *changes* — a conflict that
     * already coincides with a device address (the fixture has one) must still be
     * able to move through the workflow.
     */
    public function update(KnxConflict $conflict, ?string $status, ?string $proposal): KnxConflict
    {
        return DB::transaction(function () use ($conflict, $status, $proposal): KnxConflict {
            $project = $conflict->project;
            $addressChanged = $proposal !== null && $proposal !== $conflict->address;

            if ($addressChanged && $project !== null) {
                if (in_array($proposal, $this->takenAddressesFor($project, $conflict), true)) {
                    throw new AddressInUseException($proposal);
                }
            }

            $statusChanged = $status !== null && $status !== $conflict->status;

            if ($statusChanged) {
                // The log is the ETS worklist line: it records the address that was
                // in play when the status moved.
                KnxConflictLog::create([
                    'conflict_id' => $conflict->id,
                    'at' => now(),
                    'action' => $status,
                    'address' => $proposal ?? $conflict->proposal,
                ]);
            }

            $conflict->fill(array_filter([
                'status' => $status,
                'proposal' => $proposal,
            ], static fn ($value): bool => $value !== null))->save();

            return $conflict->refresh()->load(['project', 'reportedBy', 'logs']);
        });
    }
}
