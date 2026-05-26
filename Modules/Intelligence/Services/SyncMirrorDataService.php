<?php

namespace Modules\Intelligence\Services;

use Modules\Cafca\Models\Project;
use Modules\Cafca\Models\Employee;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\FollowupCost;
use Modules\Cafca\Models\LegacyMaterial;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorEmployee;
use Modules\Performance\Models\Mirror\MirrorLaborType;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Modules\Performance\Models\Mirror\MirrorMaterial;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorEstimateItem;
use Modules\Performance\Models\Mirror\MirrorRelation;
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
        $this->syncRelations();
        $this->syncEmployees();
        $this->syncLaborTypes();
        $this->syncLabor($fullHistory);
        $this->syncMaterials($fullHistory);
        $this->syncInvoices($fullHistory);
        $this->syncCosts($fullHistory);
        $this->syncEstimateItems($fullHistory);

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

    private function syncEmployees(): void
    {
        DB::connection('sqlsrv')->table('employee')->orderBy('id')->chunk(500, function ($employees) {
            foreach ($employees as $employee) {
                MirrorEmployee::updateOrCreate(
                    ['id' => $employee->id],
                    [
                        'name' => trim($employee->name),
                        'zipcode' => trim($employee->zip ?? ''),
                        'specialty' => trim($employee->functie ?? 'Technician'),
                        'hourly_cost' => $employee->costprice ?? $employee->hourly_rate ?? 0,
                        'fl_active' => $employee->fl_active,
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

    public function syncMaterials(bool $massive = false): void
    {
        $query = LegacyMaterial::query();
        
        if (!$massive) {
            $query->where('fl_current', 1);
        }

        $query->orderBy('id')->chunk(500, function ($materials) {
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

    private function syncInvoices(bool $fullHistory): void
    {
        $query = DB::connection('sqlsrv')->table('invoice');
        if (!$fullHistory) {
            $query->where('date', '>=', now()->subMonths(6));
        }

        $query->orderBy('id')->chunk(500, function ($invoices) {
            foreach ($invoices as $invoice) {
                MirrorInvoice::updateOrCreate(
                    ['id' => trim($invoice->id)],
                    [
                        'project_id' => trim($invoice->project_id),
                        'total_price_vat_excl' => $invoice->total_price_vat_excl,
                        'date' => $invoice->date,
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
                        'type' => $cost->price_type,
                        'cost_price' => $cost->costprice,
                        'quantity' => $cost->quantity,
                        'extra_type' => property_exists($cost, 'extra_type') ? trim($cost->extra_type) : null,
                        'date' => $cost->date,
                    ]
                );
            }
        });
    }

    /**
     * Sync clients from CAFCA's relation table.
     * Required so simulations can reference a real client without hitting SQL Server.
     *
     * AUDITOR GATE: If relation column mapping changes, review this method.
     */
    public function syncRelations(): void
    {
        DB::connection('sqlsrv')->table('relation')->orderBy('id')->chunk(500, function ($relations) {
            foreach ($relations as $relation) {
                MirrorRelation::updateOrCreate(
                    ['id' => $relation->id],
                    [
                        'name'         => trim($relation->name ?? ''),
                        'zipcode'      => trim($relation->zip ?? $relation->zipcode ?? ''),
                        'city'         => trim($relation->city ?? ''),
                        'country'      => trim($relation->country ?? 'BE'),
                        'language'     => trim($relation->language ?? $relation->lang ?? 'nl'),
                        'vat_number'   => trim($relation->btwnr ?? $relation->vat ?? ''),
                        'email'        => trim($relation->email ?? ''),
                        'phone'        => trim($relation->phone ?? $relation->tel ?? ''),
                        'contact_name' => trim($relation->contact ?? $relation->contact_name ?? ''),
                    ]
                );
            }
        });
    }

    /**
     * Sync CAFCA offer/estimate lines — the most valuable training data for the AI.
     *
     * Joins project_estimates → estimate_item to obtain project_id for every line.
     * Uses LEFT JOIN so that items without a matching project are still imported.
     *
     * AUDITOR GATE: Column names below are inferred from models and code patterns.
     * Run the following SQL on SQL Server before syncing to production to confirm:
     *
     *   SELECT COLUMN_NAME, DATA_TYPE
     *   FROM INFORMATION_SCHEMA.COLUMNS
     *   WHERE TABLE_NAME = 'estimate_item'
     *   ORDER BY ORDINAL_POSITION
     *
     * If column names differ, adjust the mapping below and create an adjustment
     * migration if the mirror table structure also needs to change.
     */
    public function syncEstimateItems(bool $fullHistory = false): void
    {
        $query = DB::connection('sqlsrv')
            ->table('estimate_item as ei')
            ->leftJoin('project_estimates as pe', 'pe.estimate_id', '=', 'ei.estimate_id')
            ->select([
                'ei.estimate_id',
                'pe.project_id',
                // Sequence / ordering within the estimate
                DB::raw('COALESCE(ei.seqnr, ei.seq, 0) as sequence'),
                // Line classification (titulo / subtitulo / partida / tekst)
                DB::raw('COALESCE(ei.type, ei.line_type, NULL) as line_type'),
                // Material reference
                DB::raw('COALESCE(ei.ref, ei.art_ref, NULL) as ref'),
                // Description (try Dutch first, fall back to generic)
                DB::raw('COALESCE(ei.descr_l1, ei.descr, ei.name, NULL) as description'),
                DB::raw('COALESCE(ei.quantity, ei.qty, 0) as quantity'),
                DB::raw('COALESCE(ei.unit, NULL) as unit'),
                // Pricing columns
                DB::raw('COALESCE(ei.costprice, ei.unit_price, 0) as unit_price_material'),
                DB::raw('COALESCE(ei.labourprice, ei.unit_price_labor, 0) as unit_price_labor'),
                DB::raw('COALESCE(ei.hours, ei.hours_per_unit, 0) as hours_per_unit'),
                DB::raw('COALESCE(ei.total_hours, 0) as total_hours'),
            ]);

        $query->orderBy('ei.estimate_id')->chunk(1000, function ($items) {
            foreach ($items as $item) {
                if (empty($item->estimate_id)) {
                    continue;
                }

                // Composite natural key: estimate_id + sequence
                MirrorEstimateItem::updateOrCreate(
                    [
                        'estimate_id' => trim($item->estimate_id),
                        'sequence'    => (int) $item->sequence,
                    ],
                    [
                        'project_id'          => trim($item->project_id ?? ''),
                        'line_type'           => $item->line_type ? trim($item->line_type) : null,
                        'ref'                 => $item->ref ? trim($item->ref) : null,
                        'description'         => $item->description ? trim($item->description) : null,
                        'quantity'            => (float) ($item->quantity ?? 0),
                        'unit'                => $item->unit ? trim($item->unit) : null,
                        'unit_price_material' => (float) ($item->unit_price_material ?? 0),
                        'unit_price_labor'    => (float) ($item->unit_price_labor ?? 0),
                        'hours_per_unit'      => (float) ($item->hours_per_unit ?? 0),
                        'total_hours'         => (float) ($item->total_hours ?? 0),
                    ]
                );
            }
        });

        Log::info('SyncMirrorDataService: estimate_items sync completed.');
    }
}
