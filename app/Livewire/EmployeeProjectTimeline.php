<?php

namespace App\Livewire;

use Modules\Cafca\Models\Employee;
use Modules\Cafca\Models\Labor;
use Livewire\Component;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class EmployeeProjectTimeline extends Component implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    public Employee $record;
    public $fromDate;
    public $toDate;

    // Stats for Dashboard
    public $totalHours = 0;
    public $distribution = [
        'Werf' => 0,
        'Laden' => 0,
        'Mobiliteit' => 0,
    ];

    public $temporalDistribution = [];
    public $temporalType = 'daily';

    public function mount(Employee $record)
    {
        $this->record = $record;
        // Default to this month
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');

        $this->calculateStats();
    }

    public function customRangeAction(): Action
    {
        return Action::make('customRange')
            ->label('Active Range')
            ->modalHeading('Selecteer Periode (Aangepast)')
            ->modalDescription('Kies een specifieke start- en einddatum voor de analyse.')
            ->modalSubmitActionLabel('Toepassen')
            ->modalWidth('md')
            ->form([
                DatePicker::make('from')
                    ->label('Start Datum')
                    ->default($this->fromDate)
                    ->required(),
                DatePicker::make('to')
                    ->label('Eind Datum')
                    ->default($this->toDate)
                    ->afterOrEqual('from')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->fromDate = $data['from'];
                $this->toDate = $data['to'];
                $this->calculateStats();
                $this->resetPage();
            });
    }

    public function setPeriod(string $period)
    {
        switch ($period) {
            case 'last_month':
                $this->fromDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->toDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_month':
                $this->fromDate = now()->startOfMonth()->format('Y-m-d');
                $this->toDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_quarter':
                $this->fromDate = now()->subQuarter()->startOfQuarter()->format('Y-m-d');
                $this->toDate = now()->subQuarter()->endOfQuarter()->format('Y-m-d');
                break;
            case 'last_semester':
                $this->fromDate = now()->subMonths(6)->startOfMonth()->format('Y-m-d');
                $this->toDate = now()->format('Y-m-d');
                break;
            case 'previous_year':
                $this->fromDate = now()->subYear()->startOfYear()->format('Y-m-d');
                $this->toDate = now()->subYear()->endOfYear()->format('Y-m-d');
                break;
        }

        $this->calculateStats();
        $this->resetPage();
    }

    public function getActivePeriod(): ?string
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->endOfDay();

        if ($start->eq(now()->subMonth()->startOfMonth()) && $end->eq(now()->subMonth()->endOfMonth())) return 'last_month';
        if ($start->isStartOfMonth() && $end->isEndOfMonth() && $start->isCurrentMonth()) return 'this_month';
        if ($start->eq(now()->subQuarter()->startOfQuarter()) && $end->eq(now()->subQuarter()->endOfQuarter())) return 'last_quarter';
        if ($start->eq(now()->subMonths(6)->startOfMonth()) && $end->isToday()) return 'last_semester';
        if ($start->eq(now()->subYear()->startOfYear()) && $end->eq(now()->subYear()->endOfYear())) return 'previous_year';

        return null;
    }

    protected function calculateStats()
    {
        $start = Carbon::parse($this->fromDate);
        $end = Carbon::parse($this->toDate);

        $stats = app(\Modules\Performance\Services\EmployeePerformanceService::class)
            ->getStatsForPeriod($this->record, $start, $end);

        $this->totalHours = $stats['hours'];
        $this->distribution = $stats['categories'];
        $this->temporalDistribution = $stats['temporal_distribution'] ?? [];
        $this->temporalType = $stats['temporal_type'] ?? 'daily';
        
        $categories = ['Werf', 'Laden', 'Mobiliteit'];
        $temporalSeries = [];
        foreach ($categories as $cat) {
            $data = [];
            foreach ($this->temporalDistribution as $date => $catData) {
                $data[] = $catData[$cat] ?? 0;
            }
            $temporalSeries[] = [
                'name' => $cat,
                'data' => $data,
            ];
        }

        $temporalLabels = array_map(function($d) {
            return $this->temporalType === 'monthly'
                ? \Carbon\Carbon::parse($d)->format('M Y')
                : \Carbon\Carbon::parse($d)->format('d M');
        }, array_keys($this->temporalDistribution));

        // Refresh the chart on the client side
        $this->dispatch('statsUpdated', [
            'totalHours' => $this->totalHours,
            'distributionLabels' => array_keys($this->distribution),
            'distributionSeries' => array_values($this->distribution),
            'temporalLabels' => $temporalLabels,
            'temporalSeries' => $temporalSeries,
            'temporalTitle' => $this->temporalType === 'monthly' ? 'Monthly Trend' : 'Daily Trend',
        ]);
    }

    public function getChartDataProperty()
    {
        $categories = ['Werf', 'Laden', 'Mobiliteit'];
        $temporalSeries = [];
        foreach ($categories as $cat) {
            $data = [];
            foreach ($this->temporalDistribution as $date => $catData) {
                $data[] = $catData[$cat] ?? 0;
            }
            $temporalSeries[] = [
                'name' => $cat,
                'data' => $data,
            ];
        }

        $temporalLabels = array_map(function($d) {
            return $this->temporalType === 'monthly'
                ? \Carbon\Carbon::parse($d)->format('M Y')
                : \Carbon\Carbon::parse($d)->format('d M');
        }, array_keys($this->temporalDistribution));

        return [
            'totalHours' => $this->totalHours,
            'distributionLabels' => array_keys($this->distribution),
            'distributionSeries' => array_values($this->distribution),
            'temporalLabels' => $temporalLabels,
            'temporalSeries' => $temporalSeries,
            'temporalTitle' => $this->temporalType === 'monthly' ? 'Monthly Trend' : 'Daily Trend',
        ];
    }

    use \Livewire\WithPagination;

    public function render()
    {
        $start = Carbon::parse($this->fromDate);
        $end = Carbon::parse($this->toDate);

        $stats = app(\Modules\Performance\Services\EmployeePerformanceService::class)
            ->getStatsForPeriod($this->record, $start, $end);

        $timelineData = collect($stats['projects'])->map(function ($project) {
            return [
                'project_id' => $project['project_id'],
                'project_name' => $project['project_name'],
                'project_type' => $project['project_type_name'],
                'project_code' => $project['project_id'],
                'total_hours' => $project['total_hours'],
                'percentage' => $this->totalHours > 0 ? round(($project['total_hours'] / $this->totalHours) * 100) : 0,
                'categories' => $project['categories'],
                'month_label' => $project['last_active'] ? str($project['last_active']->format('M'))->upper() : '---',
            ];
        });

        // Manual Pagination for Collection
        $perPage = 6;
        $currentPage = $this->getPage();
        $currentItems = $timelineData->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $timelineData->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.employee-project-timeline', [
            'timeline' => $paginatedItems,
        ]);
    }
}
