<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Employee\Repositories\ProjectRepository;
use Modules\Employee\Repositories\TimeEntryRepository;

class ProjectService
{
    public function __construct(
        protected ProjectRepository $projectRepo,
        protected TimeEntryRepository $timeEntryRepo,
    ) {}

    public function getActiveProjectsWithWorkedHoursCurrentAndPreviousMonth(): Collection
    {
        return $this->getProjectsWithWorkedHoursForPeriod(
            Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
            Carbon::now()->endOfMonth()->format('Y-m-d')
        );
    }

    public function getProjectsWithWorkedHoursForPeriod(string $startDate, string $endDate): Collection
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        if ($end->lessThan($start)) {
            throw new \InvalidArgumentException('end_date must be after start_date');
        }

        $workedHours = $this->timeEntryRepo->getProjectWorkedHoursInRange(
            $start->toDateString(),
            $end->toDateString()
        )->filter(fn($item) => $item->total_hours > 0);

        if ($workedHours->isEmpty()) {
            return new Collection();
        }

        return $this->projectRepo->getProjectsWithInvoiceInfo(
            $workedHours->pluck('project_id')->toArray(),
            $start->toDateString(),
            $end->toDateString()
        );
    }

    public function getProjectDetailsWithWorkersForPeriod(string $projectId, string $startDate, string $endDate): array
    {
        try {
            $start   = Carbon::parse($startDate)->startOfDay();
            $end     = Carbon::parse($endDate)->endOfDay();
            $project = $this->projectRepo->find($projectId);

            if (!$project) {
                return ['success' => false, 'data' => null, 'message' => "Project '{$projectId}' not found"];
            }

            $workers  = $this->getDetailedWorkersWithLaborHours($projectId, $start, $end);
            $invoices = $project->invoices()
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get();

            $invoicesData = $invoices->map(fn($i) => [
                'id'             => $i->id,
                'total_price'    => (float) $i->total_price,
                'total_paid'     => (float) $i->total_paid,
                'pending_amount' => (float) $i->balance,
            ])->toArray();

            return [
                'success' => true,
                'data'    => [
                    'id'             => $project->id,
                    'name'           => $project->name,
                    'fl_active'      => $project->fl_active,
                    'contract_price' => (float) ($project->contract_price ?? 0),
                    'total_invoiced' => array_sum(array_column($invoicesData, 'total_price')),
                    'total_paid'     => array_sum(array_column($invoicesData, 'total_paid')),
                    'total_pending'  => array_sum(array_column($invoicesData, 'pending_amount')),
                    'invoices'       => $invoicesData,
                    'workers'        => $workers,
                ],
                'message' => 'Project details retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('ProjectService: getProjectDetailsWithWorkersForPeriod failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }
    }

    public function getProjectDetailsWithWorkersCurrentAndPreviousMonth(string $projectId): array
    {
        return $this->getProjectDetailsWithWorkersForPeriod(
            $projectId,
            Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
            Carbon::now()->endOfMonth()->format('Y-m-d')
        );
    }

    public function getProjectById(string $projectId)
    {
        return $this->projectRepo->find($projectId);
    }

    private function getDetailedWorkersWithLaborHours(string $projectId, Carbon $startDate, Carbon $endDate): array
    {
        $entries         = $this->timeEntryRepo->getDetailedTimeEntriesForProject(
            $projectId,
            $startDate->toDateString(),
            $endDate->toDateString()
        );
        $employeeEntries = $entries->groupBy('employee_id');

        $workers = [];
        foreach ($employeeEntries as $empId => $empEntries) {
            $name           = $empEntries->first()->employee_name ?? 'Unknown';
            $laden          = round($empEntries->where('labor_descr', 'Laden')->sum('hours'), 2);
            $werf           = round($empEntries->where('labor_descr', 'Werf')->sum('hours'), 2);
            $transport      = round($empEntries->where('labor_descr', 'Mobiliteit')->sum('hours'), 2);
            $totalHours     = round($empEntries->sum('hours'), 2);
            $daysWorked     = $empEntries->pluck('entry_date')->map(fn($d) => (string) $d)->unique()->count();
            $avgPerDay      = $daysWorked > 0 ? round($totalHours / $daysWorked, 2) : 0;
            $totalDistance  = round($empEntries->sum('distance'), 2);

            if ($totalHours > 0) {
                $workers[] = [
                    'employee_id'         => $empId,
                    'employee_name'       => $name,
                    'total_hours'         => $totalHours,
                    'days_worked'         => $daysWorked,
                    'hours_average_per_day' => $avgPerDay,
                    'labor_hours'         => ['laden_hours' => $laden, 'werf_hours' => $werf, 'transport_hours' => $transport],
                    'distance'            => $totalDistance,
                ];
            }
        }

        usort($workers, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);
        return $workers;
    }
}
