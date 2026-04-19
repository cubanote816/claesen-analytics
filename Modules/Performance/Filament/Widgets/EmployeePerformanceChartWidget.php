<?php
 
namespace Modules\Performance\Filament\Widgets;
 
use Filament\Widgets\ChartWidget;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\Employee;
use Illuminate\Support\Carbon;
 
class EmployeePerformanceChartWidget extends ChartWidget
{
    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return ! request()->routeIs('filament.admin.pages.dashboard');
    }

    public ?string $employeeId = null;
 
    protected ?string $heading = 'Horas Reales vs Target (Legacy)';
 
    public function getHeading(): string
    {
        return app()->getLocale() === 'nl' ? 'Gerealiseerde uren vs Doelstelling' : 'Real Hours vs Target';
    }
 
    protected function getData(): array
    {
        if (!$this->employeeId) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }
 
        $employee = Employee::find($this->employeeId);
        if (!$employee) {
            return ['datasets' => [], 'labels' => []];
        }
 
        $targetDaily = ($employee->uren_per_week ?? 0) / 5;
        
        // Last 7 working days
        $days = [];
        $realHours = [];
        $targetHours = [];
        $labels = [];
 
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D d/m');
            
            $hours = Labor::where('employee_id', $this->employeeId)
                ->whereDate('date', $date->toDateString())
                ->sum('hours');
                
            $realHours[] = (float) $hours;
            $targetHours[] = (float) $targetDaily;
        }
 
        return [
            'datasets' => [
                [
                    'label' => 'Real',
                    'data' => $realHours,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
                [
                    'label' => 'Target',
                    'data' => $targetHours,
                    'backgroundColor' => '#94a3b8',
                    'borderColor' => '#94a3b8',
                    'borderDash' => [5, 5],
                    'type' => 'line',
                ],
            ],
            'labels' => $labels,
        ];
    }
 
    protected function getType(): string
    {
        return 'bar';
    }

    public function getLoadingIndicator(): \Illuminate\Contracts\View\View
    {
        return view('performance::filament.widgets.skeletons.chart');
    }
}
