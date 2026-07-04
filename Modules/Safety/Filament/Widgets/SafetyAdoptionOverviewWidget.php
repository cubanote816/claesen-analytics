<?php

namespace Modules\Safety\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Modules\Safety\Models\SafetyAdoptionDailyRollup;
use Modules\Safety\Models\SafetyEnabledUserSnapshot;
use Illuminate\Support\Facades\Gate;

class SafetyAdoptionOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '300s';

    protected static ?int $sort = 1;

    protected string $view = 'safety::filament.widgets.safety-adoption-overview-widget';

    /**
     * Days looked back from yesterday. '1' keeps the original "yesterday only" behaviour.
     */
    public string $period = '1';

    public static function canView(): bool
    {
        // Solo super_admin tiene visibilidad corporativa confirmada en el discovery
        return auth()->user()->hasRole('super_admin');
    }

    protected function getPeriodOptions(): array
    {
        return [
            '1'  => __('safety::inspections.widgets.periods.yesterday'),
            '7'  => __('safety::inspections.widgets.periods.7'),
            '30' => __('safety::inspections.widgets.periods.30'),
            '90' => __('safety::inspections.widgets.periods.90'),
        ];
    }

    protected function getPeriodLabel(): string
    {
        return $this->getPeriodOptions()[$this->period] ?? $this->getPeriodOptions()['1'];
    }

    /**
     * Sum of a daily-rollup metric across an inclusive date range.
     */
    private function sumMetric(string $metric, string $from, string $to): float
    {
        return (float) SafetyAdoptionDailyRollup::where('metric_name', $metric)
            ->whereBetween('date', [$from, $to])
            ->sum('value');
    }

    protected function getStats(): array
    {
        // Las métricas se cierran de madrugada. El dashboard nunca muestra el día en curso.
        $days = max(1, (int) $this->period);
        $endDate = Carbon::yesterday();
        $startDate = $endDate->copy()->subDays($days - 1);
        $previousEndDate = $startDate->copy()->subDay();
        $previousStartDate = $previousEndDate->copy()->subDays($days - 1);

        $period = $this->getPeriodLabel();

        // MAU adoption is a rate, not additive: always read the latest snapshot in range.
        $enabledTarget = SafetyEnabledUserSnapshot::where('date', $endDate->toDateString())->value('total_enabled_users') ?? 0;
        $enabledPrevious = SafetyEnabledUserSnapshot::where('date', $previousEndDate->toDateString())->value('total_enabled_users') ?? 0;

        $active30Target = SafetyAdoptionDailyRollup::where('date', $endDate->toDateString())->where('metric_name', 'active_users_30d')->value('value') ?? 0;
        $active30Previous = SafetyAdoptionDailyRollup::where('date', $previousEndDate->toDateString())->where('metric_name', 'active_users_30d')->value('value') ?? 0;

        $adoptionRate30 = $enabledTarget > 0 ? round(($active30Target / $enabledTarget) * 100, 1) : 0;
        $adoptionRate30Prev = $enabledPrevious > 0 ? round(($active30Previous / $enabledPrevious) * 100, 1) : 0;
        $trend30 = $adoptionRate30 - $adoptionRate30Prev;

        $adoptionChart = SafetyAdoptionDailyRollup::where('metric_name', 'active_users_30d')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->pluck('value')
            ->map(fn ($value) => (float) $value)
            ->toArray();

        // Additive counts: sum across the selected range.
        $inspectionsTarget = $this->sumMetric('inspections_completed', $startDate->toDateString(), $endDate->toDateString());
        $incidentsTarget = $this->sumMetric('incidents_reported', $startDate->toDateString(), $endDate->toDateString());
        $frictionTarget = $this->sumMetric('friction_events_count', $startDate->toDateString(), $endDate->toDateString());

        return [
            Stat::make(__('safety::inspections.widgets.adoption.mau_title', ['period' => $period]), "{$adoptionRate30}%")
                ->description(__('safety::inspections.widgets.adoption.mau_desc', ['active' => $active30Target, 'total' => $enabledTarget]))
                ->descriptionIcon($trend30 >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend30 >= 0 ? 'success' : 'danger')
                ->chart($adoptionChart ?: [$adoptionRate30Prev, $adoptionRate30]),

            Stat::make(__('safety::inspections.widgets.adoption.inspections_title', ['period' => $period]), $inspectionsTarget)
                ->description(__('safety::inspections.widgets.adoption.inspections_desc'))
                ->color('primary'),

            Stat::make(__('safety::inspections.widgets.adoption.incidents_title', ['period' => $period]), $incidentsTarget)
                ->description(__('safety::inspections.widgets.adoption.incidents_desc'))
                ->color('danger'),

            Stat::make(__('safety::inspections.widgets.adoption.friction_title', ['period' => $period]), $frictionTarget)
                ->description(__('safety::inspections.widgets.adoption.friction_desc'))
                ->color($frictionTarget > 0 ? 'warning' : 'success'),
        ];
    }
}
