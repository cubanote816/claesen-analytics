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
    protected static ?string $pollingInterval = '300s';
    
    // Position it at the top or below existing stats
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        // Solo super_admin tiene visibilidad corporativa confirmada en el discovery
        return auth()->user()->hasRole('super_admin');
    }

    protected function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // Get denominators
        $enabledToday = SafetyEnabledUserSnapshot::where('date', $today)->value('total_enabled_users') ?? 0;
        $enabledYesterday = SafetyEnabledUserSnapshot::where('date', $yesterday)->value('total_enabled_users') ?? 0;

        // Active 30d
        $active30Today = SafetyAdoptionDailyRollup::where('date', $today)->where('metric_name', 'active_users_30d')->value('value') ?? 0;
        $active30Yesterday = SafetyAdoptionDailyRollup::where('date', $yesterday)->where('metric_name', 'active_users_30d')->value('value') ?? 0;

        $adoptionRate30 = $enabledToday > 0 ? round(($active30Today / $enabledToday) * 100, 1) : 0;
        $adoptionRate30Yest = $enabledYesterday > 0 ? round(($active30Yesterday / $enabledYesterday) * 100, 1) : 0;
        $trend30 = $adoptionRate30 - $adoptionRate30Yest;

        // Inspections Completed (last 7 days sum as an example, or just today)
        $inspectionsToday = SafetyAdoptionDailyRollup::where('date', $today)->where('metric_name', 'inspections_completed')->value('value') ?? 0;

        // Friction Events
        $frictionToday = SafetyAdoptionDailyRollup::where('date', $today)->where('metric_name', 'friction_events_count')->value('value') ?? 0;

        return [
            Stat::make('Adopción MAU (30 Días)', "{$adoptionRate30}%")
                ->description("{$active30Today} activos / {$enabledToday} habilitados")
                ->descriptionIcon($trend30 >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend30 >= 0 ? 'success' : 'danger')
                ->chart([$adoptionRate30Yest, $adoptionRate30]),

            Stat::make('Inspecciones Hoy', $inspectionsToday)
                ->description('Envíos exitosos confirmados en backend')
                ->color('primary'),

            Stat::make('Fricción Técnica Hoy', $frictionToday)
                ->description('Fallos de subida o conflictos de red')
                ->color($frictionToday > 0 ? 'warning' : 'success'),
        ];
    }
}
