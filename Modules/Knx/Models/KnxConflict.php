<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Database\Factories\KnxConflictFactory;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * A conflict between the ETS plan and what is on site.
 *
 * The eight statuses are the office workflow the app implements: the outcome of
 * treating a conflict is an ETS correction **worklist** (`ets_listed` →
 * `applied` → `downloaded` → `verified` → `closed`), never an automatic ETS
 * write. `rejected` is the escape hatch.
 *
 * `lifecycle` separates two questions that share the row (KNX-56):
 *   - `ets` — the plan/site mismatch above, free and reversible on purpose;
 *   - `malfunction` — the physical installation misbehaving, with its own validated
 *     cycle (`open → diagnosing → resolved → verified`, `rejected` as the exit).
 * The ETS workflow's "invented strict machine" argument does not apply here: a
 * malfunction has a real progression, and a wrong jump would erase the diagnosis.
 */
class KnxConflict extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const STATUSES = [
        'open',
        'in_review',
        'ets_listed',
        'applied',
        'downloaded',
        'verified',
        'closed',
        'rejected',
    ];

    /** Still on the office's plate (`openConflicts` in the dashboard). */
    public const OPEN_STATUSES = ['open', 'in_review'];

    public const LIFECYCLE_ETS = 'ets';

    public const LIFECYCLE_MALFUNCTION = 'malfunction';

    public const LIFECYCLES = [self::LIFECYCLE_ETS, self::LIFECYCLE_MALFUNCTION];

    /** The states a malfunction may be in; `rejected` closes it. */
    public const MALFUNCTION_STATUSES = ['open', 'diagnosing', 'resolved', 'verified', 'rejected'];

    /**
     * Every status a row may carry, across both lifecycles. The ETS workflow keeps its
     * eight; `diagnosing`/`resolved` are malfunction-only and only reachable there.
     */
    public const ALL_STATUSES = [...self::STATUSES, 'diagnosing', 'resolved'];

    /**
     * The validated malfunction cycle. `rejected` is reachable from any live state and
     * is terminal; `verified` only still accepts the rejection so a mistake stays
     * undoable without erasing the step that followed it.
     */
    public const MALFUNCTION_TRANSITIONS = [
        'open' => ['diagnosing', 'rejected'],
        'diagnosing' => ['resolved', 'rejected'],
        'resolved' => ['verified', 'rejected'],
        'verified' => ['rejected'],
        'rejected' => [],
    ];

    public const TYPES = ['duplicate_address', 'missing_device', 'plan_mismatch', 'damaged', 'other', 'malfunction'];

    public const SEVERITIES = ['critical', 'warning', 'info'];

    /**
     * Severity order as the contract wants lists sorted: critical first. Used by
     * ORDER BY FIELD() so the database does the sorting, not PHP.
     */
    public const SEVERITY_ORDER = ['critical', 'warning', 'info'];

    protected $table = 'knx_conflicts';

    protected $fillable = [
        'organization_id',
        'project_id',
        'device_id',
        'client_id',
        'severity',
        'type',
        'lifecycle',
        'address',
        'device_existing',
        'device_field',
        'reported_by_employee_id',
        'reported_at',
        'note',
        'symptom',
        'photo_path',
        'status',
        'proposal',
    ];

    protected static function newFactory(): KnxConflictFactory
    {
        return KnxConflictFactory::new();
    }

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KnxDevice::class, 'device_id');
    }

    public function isMalfunction(): bool
    {
        return $this->lifecycle === self::LIFECYCLE_MALFUNCTION;
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'reported_by_employee_id');
    }

    /** The history the office reads as the ETS worklist. */
    public function logs(): HasMany
    {
        return $this->hasMany(KnxConflictLog::class, 'conflict_id')->orderBy('at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeLifecycle(Builder $query, ?string $lifecycle): Builder
    {
        return $lifecycle === null
            ? $query
            : $query->where('lifecycle', $lifecycle);
    }

    /**
     * The contract's list order: severity (critical → info), then most recent.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        $cases = collect(self::SEVERITY_ORDER)
            ->map(fn (string $severity, int $index): string => "WHEN ? THEN {$index}")
            ->implode(' ');

        return $query
            ->orderByRaw("CASE severity {$cases} ELSE 99 END", self::SEVERITY_ORDER)
            ->orderByDesc('reported_at');
    }
}
