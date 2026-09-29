<?php

declare(strict_types=1);

namespace Modules\FieldOps\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;

/**
 * Query building and aggregation for the work-order reporting endpoints (CLA-578).
 *
 * Access scope: this service reproduces, in one place, the scope that
 * /maintenance-work-orders/assigned and /history already applied inline
 * (admins see everything, everyone else only their own assigned orders), so
 * the reporting and export endpoints can never expose a row that the history
 * endpoint would not. It deliberately does NOT go through
 * FieldOpsTenantService::scopeForUser(): that method has no case for work
 * orders, and scoping them by allowedClientIds() would hide a technician's own
 * assigned orders whenever they have no fo_client pivot rows.
 */
class WorkOrderReportingService
{
    public const BUCKET_OPEN = 'open';

    public const BUCKET_CLOSED = 'closed';

    public const BUCKET_ALL = 'all';

    /** Default reporting window when the caller gives no explicit range. */
    private const DEFAULT_WINDOW_DAYS = 90;

    private const CLOSED_STATUSES = [
        MaintenanceWorkOrderStatus::COMPLETED->value,
        MaintenanceWorkOrderStatus::CANCELLED->value,
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(User $user, array $filters = [], string $bucket = self::BUCKET_ALL): Builder
    {
        $query = FoMaintenanceWorkOrder::query();

        $this->applyAccessScope($query, $user);
        $this->applyBucket($query, $bucket);
        $this->applyFilters($query, $filters, $bucket);

        return $query;
    }

    /**
     * The pre-existing access rule, extracted verbatim so every endpoint shares it.
     */
    public function applyAccessScope(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        return $query->where('assigned_employee_id', $user->employee_id ?: '__unlinked__');
    }

    /**
     * Aggregates for the Reports screen. Bounded by an explicit range, or by
     * DEFAULT_WINDOW_DAYS so an unfiltered call can never scan the whole table.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stats(User $user, array $filters = []): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $filters['from'] = $from->toDateTimeString();
        $filters['to'] = $to->toDateTimeString();

        $closed = $this->query($user, $filters, self::BUCKET_CLOSED)
            ->with('maintenanceType')
            ->get([
                'id', 'fo_maintenance_type_id', 'status', 'assigned_at',
                'created_at', 'completed_at', 'cancelled_at', 'returned_at',
            ]);

        $completed = $closed->where('status', MaintenanceWorkOrderStatus::COMPLETED->value);
        $cancelled = $closed->where('status', MaintenanceWorkOrderStatus::CANCELLED->value);

        // Open work is "as of now", not "within the window": a report that hid
        // an overdue order because it was scheduled before the range would be
        // actively misleading.
        $open = $this->query($user, $this->filtersWithoutRange($filters), self::BUCKET_OPEN)
            ->get(['id', 'status', 'due_at']);

        return [
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'totals' => [
                'completed' => $completed->count(),
                'cancelled' => $cancelled->count(),
                'open' => $open->count(),
                'overdue' => $open->filter(
                    fn (FoMaintenanceWorkOrder $order): bool => $order->due_at !== null && $order->due_at->isPast()
                )->count(),
            ],
            'by_status' => $this->countByStatus($open, $closed),
            'by_type' => $this->countByType($completed),
            'completed_per_week' => $this->completedPerWeek($completed, $from, $to),
            'avg_lead_time_hours' => $this->averageLeadTimeHours($completed),
            'first_time_right_rate' => $this->firstTimeRightRate($completed),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function exportHeadings(): array
    {
        return [
            'work_order_id', 'status', 'maintenance_type', 'client', 'equipment_type',
            'equipment_id', 'serial_number', 'assigned_employee_id', 'assigned_at',
            'scheduled_for', 'due_at', 'started_at', 'submitted_at', 'returned_at',
            'completed_at', 'validated_at', 'cancelled_at', 'problem_description',
            'solution_applied',
        ];
    }

    /**
     * @return array<int, string|int|null>
     */
    public function exportRow(FoMaintenanceWorkOrder $order): array
    {
        return [
            $order->id,
            $order->status instanceof MaintenanceWorkOrderStatus ? $order->status->value : $order->status,
            $order->maintenanceType?->name,
            $order->client?->name,
            $this->equipmentLabel($order),
            $order->maintainable_id,
            $order->maintainable?->serial_number,
            $order->assigned_employee_id,
            $order->assigned_at?->toIso8601String(),
            $order->scheduled_for?->toIso8601String(),
            $order->due_at?->toIso8601String(),
            $order->started_at?->toIso8601String(),
            $order->submitted_at?->toIso8601String(),
            $order->returned_at?->toIso8601String(),
            $order->completed_at?->toIso8601String(),
            $order->validated_at?->toIso8601String(),
            $order->cancelled_at?->toIso8601String(),
            $order->problem_description,
            $order->solution_applied,
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function applyBucket(Builder $query, string $bucket): void
    {
        match ($bucket) {
            self::BUCKET_OPEN => $query->whereNotIn('status', self::CLOSED_STATUSES),
            self::BUCKET_CLOSED => $query->whereIn('status', self::CLOSED_STATUSES),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $bucket): void
    {
        if (isset($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('assigned_employee_id', (int) $filters['employee_id']);
        }

        if (isset($filters['maintenance_type_id'])) {
            $query->where('fo_maintenance_type_id', (int) $filters['maintenance_type_id']);
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (isset($filters['complex_id'])) {
            $this->applyComplexFilter($query, (int) $filters['complex_id']);
        }

        $this->applyDateRange($query, $filters, $bucket);
    }

    /**
     * The date axis depends on the bucket: closed work is reported by when it
     * was closed, open work by when it is scheduled. Mixing the two would make
     * "last 30 days" mean two different things on the same screen.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, array $filters, string $bucket): void
    {
        $from = isset($filters['from']) ? CarbonImmutable::parse((string) $filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? CarbonImmutable::parse((string) $filters['to'])->endOfDay() : null;

        if ($from === null && $to === null) {
            return;
        }

        if ($bucket === self::BUCKET_CLOSED) {
            if ($from !== null) {
                $query->whereRaw('COALESCE(completed_at, cancelled_at) >= ?', [$from]);
            }
            if ($to !== null) {
                $query->whereRaw('COALESCE(completed_at, cancelled_at) <= ?', [$to]);
            }

            return;
        }

        if ($from !== null) {
            $query->where('scheduled_for', '>=', $from);
        }
        if ($to !== null) {
            $query->where('scheduled_for', '<=', $to);
        }
    }

    private function applyComplexFilter(Builder $query, int $complexId): void
    {
        $query->whereHasMorph(
            'maintainable',
            [Luminaire::class, ElectricalBoard::class],
            function (Builder $morphQuery, string $type) use ($complexId): void {
                if ($type === Luminaire::class) {
                    $morphQuery->whereHas(
                        'luminaireFrame.structures.terrains.complex',
                        fn (Builder $builder) => $builder->where('fo_complexes.id', $complexId)
                    );

                    return;
                }

                $morphQuery->where(function (Builder $builder) use ($complexId): void {
                    $builder
                        ->whereHas('complexes', fn (Builder $related) => $related->where('fo_complexes.id', $complexId))
                        ->orWhereHas('terrains.complex', fn (Builder $related) => $related->where('fo_complexes.id', $complexId))
                        ->orWhereHas('structures.terrains.complex', fn (Builder $related) => $related->where('fo_complexes.id', $complexId));
                });
            }
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $filters): array
    {
        $to = isset($filters['to'])
            ? CarbonImmutable::parse((string) $filters['to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $from = isset($filters['from'])
            ? CarbonImmutable::parse((string) $filters['from'])->startOfDay()
            : $to->subDays(self::DEFAULT_WINDOW_DAYS)->startOfDay();

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filtersWithoutRange(array $filters): array
    {
        unset($filters['from'], $filters['to']);

        return $filters;
    }

    /**
     * @param  Collection<int, FoMaintenanceWorkOrder>  $open
     * @param  Collection<int, FoMaintenanceWorkOrder>  $closed
     * @return array<string, int>
     */
    private function countByStatus(Collection $open, Collection $closed): array
    {
        $counts = [];

        foreach (MaintenanceWorkOrderStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($open->concat($closed) as $order) {
            $value = $order->status instanceof MaintenanceWorkOrderStatus
                ? $order->status->value
                : (string) $order->status;

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, FoMaintenanceWorkOrder>  $completed
     * @return array<int, array{maintenance_type_id: int|null, name: string|null, count: int}>
     */
    private function countByType(Collection $completed): array
    {
        return $completed
            ->groupBy('fo_maintenance_type_id')
            ->map(fn (Collection $group, $typeId): array => [
                'maintenance_type_id' => $typeId === '' ? null : (int) $typeId,
                'name' => $group->first()?->maintenanceType?->name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Every week in the range is present, including the empty ones — a chart
     * that silently drops empty weeks misrepresents a quiet period as a gap.
     *
     * @param  Collection<int, FoMaintenanceWorkOrder>  $completed
     * @return array<int, array{week: string, starts_on: string, count: int}>
     */
    private function completedPerWeek(Collection $completed, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $buckets = [];

        for ($cursor = $from->startOfWeek(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addWeek()) {
            $buckets[$cursor->format('o-\WW')] = [
                'week' => $cursor->format('o-\WW'),
                'starts_on' => $cursor->toDateString(),
                'count' => 0,
            ];
        }

        foreach ($completed as $order) {
            $closedAt = $order->completed_at;

            if ($closedAt === null) {
                continue;
            }

            $key = CarbonImmutable::parse($closedAt)->format('o-\WW');

            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * Hours from assignment (or creation, when an order was never formally
     * assigned) to completion.
     *
     * @param  Collection<int, FoMaintenanceWorkOrder>  $completed
     */
    private function averageLeadTimeHours(Collection $completed): ?float
    {
        $durations = $completed
            ->filter(fn (FoMaintenanceWorkOrder $order): bool => $order->completed_at !== null)
            ->map(function (FoMaintenanceWorkOrder $order): ?float {
                $start = $order->assigned_at ?? $order->created_at;

                return $start === null ? null : $start->floatDiffInHours($order->completed_at);
            })
            ->filter(fn (?float $hours): bool => $hours !== null);

        return $durations->isEmpty() ? null : round((float) $durations->avg(), 1);
    }

    /**
     * Share of completed work that was never sent back for correction.
     *
     * @param  Collection<int, FoMaintenanceWorkOrder>  $completed
     */
    private function firstTimeRightRate(Collection $completed): ?float
    {
        if ($completed->isEmpty()) {
            return null;
        }

        $clean = $completed->filter(fn (FoMaintenanceWorkOrder $order): bool => $order->returned_at === null)->count();

        return round($clean / $completed->count(), 4);
    }

    private function equipmentLabel(FoMaintenanceWorkOrder $order): string
    {
        return match ($order->maintainable_type) {
            Luminaire::class => 'luminaire',
            ElectricalBoard::class => 'electrical_board',
            default => (string) $order->maintainable_type,
        };
    }
}
