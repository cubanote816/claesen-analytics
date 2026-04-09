<?php

namespace Modules\Analytics\Services;

use Modules\Cafca\Models\Project;
use Modules\Cafca\Models\Employee;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\FollowupCost;
use Modules\Cafca\Models\LegacyMaterial;
use Modules\Analytics\Models\Mirror\MirrorProject;
use Modules\Analytics\Models\Mirror\MirrorEmployee;
use Modules\Analytics\Models\Mirror\MirrorLaborType;
use Modules\Analytics\Models\Mirror\MirrorLabor;
use Modules\Analytics\Models\Mirror\MirrorMaterial;
use Modules\Analytics\Models\Mirror\MirrorCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMirrorDataService
{
    /**
     * Synchronize all relevant tables for the Offer Simulator.
     */
    public function syncAll(bool $fullHistory = false): void
    {
        Log::info("Starting Mirror Sync Process...");

        $this->syncProjects($fullHistory);
        $this->syncEmployees();
        $this->syncLaborTypes();
        $this->syncLabor($fullHistory);
        $this->syncMaterials();
        $this->syncCosts($fullHistory);

        Log::info("Mirror Sync Process Completed.");
    }

    private function syncProjects(bool $fullHistory): void
    {
        $query = DB::connection('sqlsrv')->table('project');
        if (!$fullHistory) {
            $query->where('fl_active', 1);
        }

        $query->orderBy('id')->chunk(500, function ($projects) {
            foreach ($projects as $project) {
                // Mapping project.type to human-readable categories
                $categoryMap = [
                    0 => 'Industrie',
                    1 => 'Industrie',
                    2 => 'Openbare Verlichting',
                    3 => 'Openbare Verlichting',
                    4 => 'Sportverlichting',
                    5 => 'Sportverlichting',
                    6 => 'Masten',
                    7 => 'Industrie',
                    8 => 'Algemeen',
                ];

                $category = $categoryMap[$project->type] ?? 'Algemeen';

                // Fetch location from relation table
                $relation = DB::connection('sqlsrv')->table('relation')
                    ->where('id', $project->relation_id)
                    ->select('zipcode', 'city')
                    ->first();

                MirrorProject::updateOrCreate(
                    ['id' => trim($project->id)],
                    [
                        'name' => trim($project->name),
                        'relation_id' => $project->relation_id,
                        'category' => $category,
                        'zipcode' => trim($relation?->zipcode ?? ''),
                        'city' => trim($relation?->city ?? ''),
                        'fl_active' => $project->fl_active,
                        'last_modified_at' => $project->ts_modif ?? $project->ts_crea,
                    ]
                );
            }
        });
    }

    private function syncEmployees(): void
    {
        DB::connection('sqlsrv')->table('employee')->orderBy('id')->chunk(500, function ($employees) {
            foreach ($employees as $employee) {
                MirrorEmployee::updateOrCreate(
                    ['id' => $employee->id],
                    [
                        'name' => trim($employee->name),
                        'zipcode' => trim($employee->zip ?? ''),
                        'specialty' => trim($employee->specialty ?? ''),
                        'fl_active' => $employee->fl_active ?? true,
                    ]
                );
            }
        });
    }

    private function syncLaborTypes(): void
    {
        // Fetch unique labor type definitions from legacy labor table if exists
        // Actually, labor types are often in a separate table, but here we mirror the 'labor' table structure
        // Let's check 'labor' table in schema dump again.
        // It seems 'labor' table in Cafca is for the types themselves.
        DB::connection('sqlsrv')->table('labor')->orderBy('id')->chunk(500, function ($types) {
            foreach ($types as $type) {
                MirrorLaborType::updateOrCreate(
                    ['id' => $type->id],
                    [
                        'ref' => trim($type->ref),
                        'name' => trim($type->descr_l1),
                    ]
                );
            }
        });
    }

    private function syncLabor(bool $fullHistory): void
    {
        $query = DB::connection('sqlsrv')->table('followup_labor_analytical');
        if (!$fullHistory) {
            $query->where('date', '>=', now()->subMonths(6)); // Recent only if incremental
        }

        $query->orderBy('seqnr')->chunk(1000, function ($logs) {
            foreach ($logs as $log) {
                MirrorLabor::updateOrCreate(
                    ['id' => $log->seqnr], 
                    [
                        'project_id' => trim($log->project_id),
                        'employee_id' => $log->employee_id,
                        'labor_id' => $log->labor_id,
                        'hours' => $log->hours,
                        'date' => $log->date,
                    ]
                );
            }
        });
    }

    private function syncMaterials(): void
    {
        LegacyMaterial::where('fl_current', 1)->orderBy('id')->chunk(500, function ($materials) {
            foreach ($materials as $material) {
                MirrorMaterial::updateOrCreate(
                    ['id' => $material->id],
                    [
                        'ref' => trim($material->ref),
                        'description' => trim($material->descr_l1),
                        'cost_price' => $material->costprice,
                        'last_price_date' => $material->date,
                        'fl_active' => $material->fl_current,
                    ]
                );
            }
        });
    }

    private function syncCosts(bool $fullHistory): void
    {
        $db = DB::connection('sqlsrv')->table('followup_cost');
        if (!$fullHistory) {
            $db->where('date', '>=', now()->subMonths(6));
        }

        $db->orderBy('ts_crea')->chunk(1000, function ($costs) {
            foreach ($costs as $cost) {
                MirrorCost::updateOrCreate(
                    ['id' => $cost->id],
                    [
                        'project_id' => trim($cost->project_id),
                        'art_id' => $cost->art_id ?? null,
                        'descr' => trim($cost->descr ?? $cost->name ?? ''),
                        'type' => $cost->price_type, // Using price_type for MAMO mapping
                        'cost_price' => $cost->costprice,
                        'quantity' => $cost->quantity,
                        'extra_type' => property_exists($cost, 'extra_type') ? trim($cost->extra_type) : null,
                        'date' => $cost->date,
                    ]
                );
            }
        });
    }
}
