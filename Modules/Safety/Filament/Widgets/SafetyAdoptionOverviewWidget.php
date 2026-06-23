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
            Stat::make('Adopción MAU (Cierre de Ayer)', "{$adoptionRate30}%")
                ->description("{$active30Target} activos / {$enabledTarget} habilitados")
                ->descriptionIcon($trend30 >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend30 >= 0 ? 'success' : 'danger')
                ->chart([$adoptionRate30Prev, $adoptionRate30]),

            Stat::make('Inspecciones Completadas (Ayer)', $inspectionsTarget)
                ->description('Envíos exitosos confirmados en backend')
                ->color('primary'),

            Stat::make('Incidentes Reportados (Ayer)', $incidentsTarget)
                ->description('Reportes de incidentes confirmados')
                ->color('danger'),

            Stat::make('Fricción Técnica (Ayer)', $frictionTarget)
                ->description('Fallos de subida o conflictos de red')
                ->color($frictionTarget > 0 ? 'warning' : 'success'),
        ];
    }
}
