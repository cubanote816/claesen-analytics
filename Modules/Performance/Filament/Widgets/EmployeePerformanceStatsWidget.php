<?php
 
namespace Modules\Performance\Filament\Widgets;
 
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Performance\Services\EmployeePerformanceService;
use Modules\Cafca\Models\Employee;
use Illuminate\Support\Carbon;
 
class EmployeePerformanceStatsWidget extends BaseWidget
{
    protected static bool $isLazy = true;

    public ?string $employeeId = null;
 
    protected function getStats(): array
    {
        if (!$this->employeeId) {
            return [];
        }
 
        $employee = Employee::find($this->employeeId);
        if (!$employee) {
            return [];
        }
 
        $service = app(EmployeePerformanceService::class);
        $daily = $service->getDailyStats($employee, now());
        $weekly = $service->getWeeklyStats($employee, now());
        $profile = $service->getPerformanceProfile($employee);
 
        return [
            Stat::make(__('performance::dashboard.achievement_daily'), round($daily['achievement_rate'], 1) . '%')
                ->description($daily['hours'] . ' ' . __('performance::dashboard.hours_logged'))
                ->descriptionIcon($daily['achievement_rate'] >= 100 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($daily['achievement_rate'] >= 100 ? 'success' : 'warning'),
 
            Stat::make(__('performance::dashboard.weekly_total'), $weekly['hours'] . 'h')
                ->description(__('performance::dashboard.compliance') . ': ' . round($weekly['achievement_rate'], 1) . '%')
                ->color('info'),
 
            Stat::make(__('performance::dashboard.ai_archetype'), $profile['archetype_label'] ?? 'Unknown')
                ->description($profile['manager_insight'] ?? '')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('primary'),
        ];
    }

    public function getLoadingIndicator(): \Illuminate\Contracts\View\View
    {
        return view('performance::filament.widgets.skeletons.stats');
    }
}
