<?php

namespace Modules\Employee\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Models\Mirror\MirrorLabor;

class TimeEntryRepository
{
    public function getTimeEntries(string $employeeId, Carbon $startDate, ?Carbon $endDate = null): Collection
    {
        $query = MirrorLabor::where('employee_id', $employeeId)
            ->where('date', '>=', $startDate->toDateString());

        if ($endDate) {
            $query->where('date', '<=', $endDate->toDateString());
        }

        return $query->select([
            'employee_id',
            'date',
            'hours',
            'project_id',
            'labor_id',
            'total_costprice',
            'total_salesprice',
            'distance',
            'fl_approved',
            'labor_descr',
            'h_from_1',
            'h_to_1',
            'h_from_2',
            'h_to_2',
            'pauze',
            'fl_pauze',
            'productivity',
            'transport_costprice',
            'transport_salesprice',
        ])->get()->map(fn($e) => tap($e, fn($e) => $e->entry_date = $e->date));
    }

    public function getTimeEntriesForMultipleEmployees(array $employeeIds, Carbon $startDate, Carbon $endDate): Collection
    {
        return MirrorLabor::whereIn('employee_id', $employeeIds)
            ->where('date', '>=', $startDate->toDateString())
            ->where('date', '<=', $endDate->toDateString())
            ->select([
                'employee_id',
                'date',
                'hours',
                'project_id',
                'labor_id',
                'total_costprice',
                'total_salesprice',
                'distance',
                'fl_approved',
                'labor_descr',
                'h_from_1',
                'h_to_1',
                'h_from_2',
                'h_to_2',
                'pauze',
                'fl_pauze',
                'productivity',
                'transport_costprice',
                'transport_salesprice',
            ])->get()->map(fn($e) => tap($e, fn($e) => $e->entry_date = $e->date));
    }

    public function getProjectWorkedHoursInRange(string $startDate, string $endDate): Collection
    {
        return MirrorLabor::where('hours', '>', 0)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('project_id')
            ->selectRaw('project_id, SUM(hours) as total_hours')
            ->get();
    }

    public function getProjectWorkedHoursInMonth(string $yearMonth): Collection
    {
        $start = Carbon::parse($yearMonth)->startOfMonth()->toDateString();
        $end   = Carbon::parse($yearMonth)->endOfMonth()->toDateString();

        return $this->getProjectWorkedHoursInRange($start, $end);
    }

    /**
     * Get detailed time entries for a project with employee names.
     * Fetches MirrorLabor rows, then resolves names from local MySQL Employee table.
     */
    public function getDetailedTimeEntriesForProject(string $projectId, string $startDate, string $endDate): Collection
    {
        try {
            $rows = MirrorLabor::where('project_id', $projectId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('hours', '>', 0)
                ->select([
                    'employee_id',
                    'date',
                    'hours',
                    'labor_descr',
                    'distance',
                    'total_costprice',
                    'total_salesprice',
                    'fl_approved',
                ])
                ->get();

            // Resolve employee names from local canonical table (MySQL, no SQL Server call)
            $empIds   = $rows->pluck('employee_id')->unique()->values()->toArray();
            $empNames = Employee::whereIn('id', $empIds)->pluck('name', 'id');

            return $rows->map(function ($row) use ($empNames) {
                $row->employee_name = $empNames->get($row->employee_id, 'Unknown');
                $row->entry_date    = $row->date;
                return $row;
            });
        } catch (\Exception $e) {
            Log::error('TimeEntryRepository: getDetailedTimeEntriesForProject failed', [
                'project_id' => $projectId,
                'error'      => $e->getMessage(),
            ]);
            return collect();
        }
    }

    public function getTimeEntriesInDateRange(string $startDate, string $endDate): Collection
    {
        return MirrorLabor::whereBetween('date', [$startDate, $endDate])
            ->select([
                'employee_id',
                'date',
                'hours',
                'project_id',
                'labor_id',
                'labor_descr',
                'distance',
                'fl_approved',
                'total_costprice',
                'total_salesprice',
                'transport_costprice',
                'transport_salesprice',
                'pauze',
                'fl_pauze',
                'productivity',
            ])
            ->get()
            ->map(fn($e) => tap($e, fn($e) => $e->entry_date = $e->date));
    }

    public function getWorkersWithHoursForProjectInRange(string $projectId, string $startDate, string $endDate): Collection
    {
        $rows = MirrorLabor::where('project_id', $projectId)
            ->where('hours', '>', 0)
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(hours) as total_hours')
            ->get();

        $empIds   = $rows->pluck('employee_id')->unique()->values()->toArray();
        $empNames = Employee::whereIn('id', $empIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($empNames) {
            $row->employee_name = $empNames->get($row->employee_id, 'Unknown');
            return $row;
        });
    }
}
