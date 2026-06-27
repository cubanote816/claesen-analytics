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

    public static function canView(): bool
    {
        // Solo super_admin tiene visibilidad corporativa confirmada en el discovery
        return auth()->user()->hasRole('super_admin');
    }

    protected function getStats(): array
    {
        // Las métricas se cierran de madrugada. El dashboard debe mostrar "ayer".
        $targetDate = Carbon::yesterday()->toDateString();
        $previousDate = Carbon::yesterday()->subDay()->toDateString();

        // Get denominators
        $enabledTarget = SafetyEnabledUserSnapshot::where('date', $targetDate)->value('total_enabled_users') ?? 0;
        $enabledPrevious = SafetyEnabledUserSnapshot::where('date', $previousDate)->value('total_enabled_users') ?? 0;

        // Active 30d
        $active30Target = SafetyAdoptionDailyRollup::where('date', $targetDate)->where('metric_name', 'active_users_30d')->value('value') ?? 0;
        $active30Previous = SafetyAdoptionDailyRollup::where('date', $previousDate)->where('metric_name', 'active_users_30d')->value('value') ?? 0;

        $adoptionRate30 = $enabledTarget > 0 ? round(($active30Target / $enabledTarget) * 100, 1) : 0;
        $adoptionRate30Prev = $enabledPrevious > 0 ? round(($active30Previous / $enabledPrevious) * 100, 1) : 0;
        $trend30 = $adoptionRate30 - $adoptionRate30Prev;

        // Inspections Completed (only target day)
        $inspectionsTarget = SafetyAdoptionDailyRollup::where('date', $targetDate)->where('metric_name', 'inspections_completed')->value('value') ?? 0;

        // Incidents Reported (only target day)
        $incidentsTarget = SafetyAdoptionDailyRollup::where('date', $targetDate)->where('metric_name', 'incidents_reported')->value('value') ?? 0;

        // Friction Events
        $frictionTarget = SafetyAdoptionDailyRollup::where('date', $targetDate)->where('metric_name', 'friction_events_count')->value('value') ?? 0;

        return [
            Stat::make(__('safety::inspections.widgets.adoption.mau_title'), "{$adoptionRate30}%")
                ->description(__('safety::inspections.widgets.adoption.mau_desc', ['active' => $active30Target, 'total' => $enabledTarget]))
                ->descriptionIcon($trend30 >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend30 >= 0 ? 'success' : 'danger')
                ->chart([$adoptionRate30Prev, $adoptionRate30]),

            Stat::make(__('safety::inspections.widgets.adoption.inspections_title'), $inspectionsTarget)
                ->description(__('safety::inspections.widgets.adoption.inspections_desc'))
                ->color('primary'),

            Stat::make(__('safety::inspections.widgets.adoption.incidents_title'), $incidentsTarget)
                ->description(__('safety::inspections.widgets.adoption.incidents_desc'))
                ->color('danger'),

            Stat::make(__('safety::inspections.widgets.adoption.friction_title'), $frictionTarget)
                ->description(__('safety::inspections.widgets.adoption.friction_desc'))
                ->color($frictionTarget > 0 ? 'warning' : 'success'),
        ];
    }
}
