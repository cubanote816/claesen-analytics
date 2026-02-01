<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\Cafca\Labor;
use Livewire\Component;
use Carbon\Carbon;

class EmployeeProjectTimeline extends Component
{
    public Employee $record;
    public $fromDate;
    public $toDate;

    // Stats for Dashboard
    public $totalHours = 0;
    public $distribution = [
        'effective' => 0,
        'loading' => 0,
        'transport' => 0,
    ];

    public function mount(Employee $record)
    {
        $this->record = $record;
        // Default to this month
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');

        $this->calculateStats();
    }

    public function updated($property)
    {
        if (in_array($property, ['fromDate', 'toDate'])) {
            $this->calculateStats();
        }
    }

    public function setPeriod(string $period)
    {
        switch ($period) {
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
    }

    public function getActivePeriod(): ?string
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->endOfDay();

        if ($start->isStartOfMonth() && $end->isEndOfMonth() && $start->isCurrentMonth()) return 'this_month';
        if ($start->eq(now()->subQuarter()->startOfQuarter()) && $end->eq(now()->subQuarter()->endOfQuarter())) return 'last_quarter';
        if ($start->eq(now()->subMonths(6)->startOfMonth()) && $end->isToday()) return 'last_semester';
        if ($start->eq(now()->subYear()->startOfYear()) && $end->eq(now()->subYear()->endOfYear())) return 'previous_year';

        return null;
    }

    protected function calculateStats()
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->endOfDay();

        $logs = Labor::where('employee_id', $this->record->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $this->totalHours = $logs->sum('hours');

        // Logic for Distribution (Simplified Heuristic for demonstration)
        // In a real scenario, this would check 'type_id' or 'activity_id' from the legacy DB
        if ($this->totalHours > 0) {
            $this->distribution = [
                'effective' => round($this->totalHours * 0.7, 1),
                'loading' => round($this->totalHours * 0.15, 1),
                'transport' => round($this->totalHours * 0.15, 1),
            ];
        } else {
            $this->distribution = ['effective' => 0, 'loading' => 0, 'transport' => 0];
        }
    }

    public function render()
    {
        $start = Carbon::parse($this->fromDate)->startOfDay();
        $end = Carbon::parse($this->toDate)->endOfDay();

        $logs = Labor::where('employee_id', $this->record->id)
            ->whereBetween('date', [$start, $end])
            ->with('project')
            ->get();

        $timelineData = $logs->groupBy('project_id')
            ->map(function ($logs) {
                $project = $logs->first()->project;
                $projectHours = $logs->sum('hours');

                return [
                    'project_id' => $logs->first()->project_id,
                    'project_name' => $project ? $project->name : 'Unknown Project',
                    'project_code' => $project ? $project->id : '---',
                    'total_hours' => $projectHours,
                    'percentage' => $this->totalHours > 0 ? round(($projectHours / $this->totalHours) * 100) : 0,
                    'month_label' => $logs->max('date') ? str($logs->max('date')->format('M'))->upper() : '---',
                ];
            })
            ->sortByDesc('total_hours')
            ->values();

        return view('livewire.employee-project-timeline', [
            'timeline' => $timelineData,
        ]);
    }
}
