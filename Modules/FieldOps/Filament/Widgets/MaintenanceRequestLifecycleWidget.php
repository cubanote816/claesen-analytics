<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestAlert;

/**
 * Fase 5 lifecycle metrics: reception, first response, assignment, resolution,
 * confirmation and reopening. Computed live over a rolling 30-day window -
 * request volume is low enough (client-reported tickets, not high-frequency
 * events) that a Safety-style daily rollup table would be premature.
 */
class MaintenanceRequestLifecycleWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $windowStart = now()->subDays(30);

        $received = FoMaintenanceRequest::query()->where('created_at', '>=', $windowStart)->count();

        $avgFirstResponseHours = (float) FoMaintenanceRequest::query()
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('acknowledged_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, acknowledged_at)')) ?: 0;

        $avgAssignmentHours = (float) FoMaintenanceRequest::query()
            ->join('fo_maintenance_work_orders', 'fo_maintenance_work_orders.id', '=', 'fo_maintenance_requests.work_order_id')
            ->where('fo_maintenance_requests.created_at', '>=', $windowStart)
            ->whereNotNull('fo_maintenance_work_orders.assigned_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, fo_maintenance_requests.created_at, fo_maintenance_work_orders.assigned_at)')) ?: 0;

        $avgResolutionDays = (float) FoMaintenanceRequest::query()
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('resolved_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, resolved_at) / 24')) ?: 0;

        $avgConfirmationDays = (float) FoMaintenanceRequest::query()
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('confirmed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, resolved_at, confirmed_at) / 24')) ?: 0;

        $reopenedCount = FoMaintenanceRequest::query()
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('reopened_at')
            ->count();
        $reopenRate = $received > 0 ? round(($reopenedCount / $received) * 100, 1) : 0.0;

        $openAlerts = FoMaintenanceRequestAlert::query()->whereNull('resolved_at')->count();

        return [
            Stat::make('Requests received (30d)', (string) $received)
                ->description('New client-reported requests'),

            Stat::make('Avg. first response', $this->formatHours($avgFirstResponseHours))
                ->description('Reception → backoffice acknowledgement'),

            Stat::make('Avg. time to assignment', $this->formatHours($avgAssignmentHours))
                ->description('Reception → work order assigned'),

            Stat::make('Avg. time to resolution', $this->formatDays($avgResolutionDays))
                ->description('Reception → resolved'),

            Stat::make('Avg. time to confirmation', $this->formatDays($avgConfirmationDays))
                ->description('Resolved → client confirmed'),

            Stat::make('Reopen rate', "{$reopenRate}%")
                ->description("{$reopenedCount} of {$received} reopened")
                ->color($reopenRate > 15 ? 'warning' : 'success'),

            Stat::make('Open alerts', (string) $openAlerts)
                ->description('No first response / awaiting confirmation')
                ->color($openAlerts > 0 ? 'danger' : 'success'),
        ];
    }

    private function formatHours(float $hours): string
    {
        return $hours <= 0 ? '—' : round($hours, 1).'h';
    }

    private function formatDays(float $days): string
    {
        return $days <= 0 ? '—' : round($days, 1).'d';
    }
}
