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
use Modules\Performance\Models\Mirror\MirrorEstimateCalc;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Modules\Performance\Models\Mirror\MirrorProjectResult;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Models\Mirror\MirrorWorkdoc;
use Carbon\Carbon;
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
        $this->syncEstimateCalc();
        $this->syncProjectLinks();
        $this->syncProjectResults();
        $this->syncWorkdocs();

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
                        'name'           => trim($project->name),
                        'relation_id'    => $project->relation_id,
                        'category'       => $category,
                        'zipcode'        => trim($relation?->zipcode ?? ''),
                        'city'           => trim($relation?->city ?? ''),
                        'fl_active'      => $project->fl_active,
                        'contract_price' => $project->contract_price,
                        'type'           => $project->type,
                        'state'          => $project->state,
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

    /**
     * Check whether the current time (Europe/Brussels) falls inside the configured
     * labor sync window. Returns true if no window is configured (null start/end).
     * Handles midnight-spanning windows (e.g. 22:00-06:00).
     */
    public function isLaborSyncAllowed(): bool
    {
        $schedule = app(BiConfigService::class)->get('labor_sync_schedule', []);
        $start    = $schedule['start'] ?? null;
        $end      = $schedule['end']   ?? null;

        if ($start === null || $end === null) {
            return true;
        }

        $now        = Carbon::now('Europe/Brussels');
        $nowMinutes = $now->hour * 60 + $now->minute;

        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $startMin  = $sh * 60 + $sm;
        $endMin    = $eh * 60 + $em;

        // Window spans midnight (e.g. 22:00-06:00): allowed outside the gap
        if ($startMin > $endMin) {
            return $nowMinutes >= $startMin || $nowMinutes < $endMin;
        }

        // Normal window (e.g. 01:00-05:00)
        return $nowMinutes >= $startMin && $nowMinutes < $endMin;
    }

    private function syncLabor(bool $fullHistory): void
    {
        // followup_labor_analytical can be locked during CAFCA production hours.
        // Wrap in try/catch so a temporary lock does not abort the entire syncAll.
        try {
            if (!$this->isLaborSyncAllowed()) {
                $schedule = app(BiConfigService::class)->get('labor_sync_schedule', []);
                Log::warning('syncLabor skipped — outside configured safe window.', [
                    'window_start' => $schedule['start'],
                    'window_end'   => $schedule['end'],
                    'now_brussels' => Carbon::now('Europe/Brussels')->format('H:i'),
                ]);
                return;
            }
            $query = DB::connection('sqlsrv')->table('followup_labor_analytical');
            if (!$fullHistory) {
                $query->where('date', '>=', now()->subMonths(6));
            }

            // PK in SQL Server is composite: (date, employee_id, seqnr).
            // seqnr is NOT globally unique (max ~10); encode composite string mirror ID.
            // Filter project_id in PHP — WHERE on project_id causes full-scan timeout.
            $query->orderBy('seqnr')->chunk(1000, function ($logs) {
                foreach ($logs as $log) {
                    if (empty(trim($log->project_id ?? ''))) {
                        continue;
                    }
                    $date = is_string($log->date) ? substr($log->date, 0, 10) : date('Y-m-d', strtotime($log->date));
                    $mirrorId = $date . '_' . $log->employee_id . '_' . $log->seqnr;
                    MirrorLabor::updateOrCreate(
                        ['id' => $mirrorId],
                        [
                            'project_id' => trim($log->project_id),
                            'employee_id' => $log->employee_id,
                            'labor_id' => $log->labor_id ?? null,
                            'hours' => $log->hours ?? 0,
                            'date' => $date,
                        ]
                    );
                }
            });
        } catch (\Exception $e) {
            Log::warning('syncLabor skipped — followup_labor_analytical unavailable: ' . $e->getMessage());
        }
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
                if (empty(trim($invoice->project_id ?? ''))) {
                    continue;
                }
                MirrorInvoice::updateOrCreate(
                    ['id' => trim($invoice->id)],
                    [
                        'project_id'           => trim($invoice->project_id),
                        'relation_id'          => $invoice->relation_id ?? null,
                        'total_price_vat_excl' => $invoice->total_price_vat_excl ?? 0,
                        'date'                 => $invoice->date,
                        'date_expiration'      => $invoice->date_expiration ?? null,
                        'fl_paid'              => (bool) ($invoice->fl_paid ?? false),
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
                if (empty(trim($cost->project_id ?? ''))) {
                    continue;
                }
                MirrorCost::updateOrCreate(
                    ['id' => $cost->id],
                    [
                        'project_id' => trim($cost->project_id),
                        'art_id'     => $cost->art_id ?? null,
                        'descr'      => trim($cost->descr ?? $cost->name ?? ''),
                        'type'       => $cost->price_type ?? null,
                        'cost_price' => $cost->costprice,
                        'quantity'   => $cost->quantity,
                        'extra_type' => property_exists($cost, 'extra_type') ? trim($cost->extra_type) : null,
                        'date'       => $cost->date,
                        'invoiced'   => (bool) $cost->already_invoiced,
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
        // Column mapping confirmed via INFORMATION_SCHEMA.COLUMNS audit (CLA-63):
        //   seq_nr          → sequence
        //   art_type        → line_type (smallint type code)
        //   COALESCE(title, descr) → description (title rows use title col, item rows use descr)
        //   costprice_material → unit_price_material
        //   labor_c_price   → unit_price_labor
        //   norm            → hours_per_unit
        $query = DB::connection('sqlsrv')
            ->table('estimate_item as ei')
            ->leftJoin('project_estimates as pe', 'pe.estimate_id', '=', 'ei.estimate_id')
            ->select([
                'ei.estimate_id',
                'pe.project_id',
                DB::raw('ei.seq_nr as sequence'),
                DB::raw('CAST(ei.art_type AS VARCHAR(10)) as line_type'),
                'ei.ref',
                DB::raw('COALESCE(ei.title, ei.descr, NULL) as description'),
                DB::raw('COALESCE(ei.quantity, 0) as quantity'),
                'ei.unit',
                DB::raw('COALESCE(ei.costprice_material, 0) as unit_price_material'),
                DB::raw('COALESCE(ei.labor_c_price, 0) as unit_price_labor'),
                DB::raw('COALESCE(ei.norm, 0) as hours_per_unit'),
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

    public function syncEstimateCalc(): void
    {
        DB::connection('sqlsrv')
            ->table('estimate_calculation')
            ->orderBy('estimate_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    if (empty(trim($row->estimate_id ?? ''))) {
                        continue;
                    }

                    $extraCosts = [
                        'transport'    => $row->extra_cost_transport ?? null,
                        'management'   => $row->extra_cost_management ?? null,
                        'insurance'    => $row->extra_cost_insurance ?? null,
                        'design'       => $row->extra_cost_design ?? null,
                        'company'      => $row->extra_cost_company ?? null,
                        'calculation'  => $row->extra_cost_calculation ?? null,
                        'building_site' => [
                            $row->extra_cost_building_site1 ?? null,
                            $row->extra_cost_building_site2 ?? null,
                            $row->extra_cost_building_site3 ?? null,
                            $row->extra_cost_building_site4 ?? null,
                        ],
                        'any' => [
                            $row->extra_cost_any1 ?? null,
                            $row->extra_cost_any2 ?? null,
                            $row->extra_cost_any3 ?? null,
                        ],
                        'factor_total' => [
                            ['value' => $row->factor_total_1 ?? null, 'descr' => $row->factor_total_1_descr ?? null],
                            ['value' => $row->factor_total_2 ?? null, 'descr' => $row->factor_total_2_descr ?? null],
                            ['value' => $row->factor_total_3 ?? null, 'descr' => $row->factor_total_3_descr ?? null],
                        ],
                    ];

                    MirrorEstimateCalc::updateOrCreate(
                        ['estimate_id' => trim($row->estimate_id)],
                        [
                            'factor_material'     => $row->factor_material ?? 0,
                            'factor_labor'        => $row->factor_labor ?? 0,
                            'factor_equipment'    => $row->factor_equipment ?? 0,
                            'factor_subcontract'  => $row->factor_subcontract ?? 0,
                            'factor_qty_labor'    => $row->factor_qty_labor ?? 0,
                            'factor_qty_material' => $row->factor_qty_material ?? 0,
                            'factor_unitprice'    => $row->factor_unitprice ?? 0,
                            'labor_c_price'       => $row->labor_c_price ?? null,
                            'additional_hours'    => $row->additional_hours ?? null,
                            'qty_employees'       => $row->qty_employees ?? null,
                            'extra_costs_json'    => $extraCosts,
                        ]
                    );
                }
            });

        Log::info('SyncMirrorDataService: estimate_calc sync completed.');
    }

    private function syncProjectLinks(): void
    {
        DB::connection('sqlsrv')
            ->table('project_estimates')
            ->orderBy('project_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    if (empty(trim($row->project_id ?? '')) || empty(trim($row->estimate_id ?? ''))) {
                        continue;
                    }

                    MirrorProjectLink::updateOrCreate(
                        [
                            'project_id'  => trim($row->project_id),
                            'estimate_id' => trim($row->estimate_id),
                        ],
                        ['link_type' => $row->type]
                    );
                }
            });

        Log::info('SyncMirrorDataService: project_links sync completed.');
    }

    public function syncProjectResults(): void
    {
        $now = now();

        DB::connection('sqlsrv')
            ->table('rpt_project_results')
            ->orderBy('project_id')
            ->chunk(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    if (empty(trim($row->project_id ?? ''))) {
                        continue;
                    }

                    MirrorProjectResult::updateOrCreate(
                        ['project_id' => trim($row->project_id)],
                        [
                            'project_name'             => trim($row->project_name ?? ''),
                            'relation_id'              => $row->project_relation_id ?? null,
                            'relation_name'            => trim($row->project_relation_name ?? ''),
                            'dossier'                  => trim($row->dossier ?? ''),
                            'costprice_material'       => $row->costprice_material ?? null,
                            'costprice_labor'          => $row->costprice_labor ?? null,
                            'costprice_equipment'      => $row->costprice_equipment ?? null,
                            'costprice_subcontract'    => $row->costprice_subcontract ?? null,
                            'costprice_extra'          => $row->costprice_extra ?? null,
                            'costprice_transport'      => $row->costprice_transport ?? null,
                            'costprice_total'          => $row->costprice_total ?? null,
                            'invoiced'                 => $row->invoiced ?? null,
                            'profit'                   => $row->profit ?? null,
                            'profit_percent'           => $row->profit_percent ?? null,
                            'profit_percent_estimates' => $row->profit_percent_estimates ?? null,
                            'total_estimates'          => $row->total_estimates ?? null,
                            'total_regie'              => $row->total_regie ?? null,
                            'hours_regie'              => $row->hours_regie ?? null,
                            'oh'                       => $row->oH ?? null,
                            'project_uren'             => $row->project_uren ?? null,
                            'voorz_uren'               => $row->voorz_uren ?? null,
                            'uren_projectleader'       => $row->uren_projectleader ?? null,
                            'current_costs_booked'     => (bool) ($row->current_costs_booked ?? false),
                            'synced_at'                => $now,
                        ]
                    );
                }
            });

        Log::info('SyncMirrorDataService: project_results sync completed.');
    }

    public function syncWorkdocs(): void
    {
        $now = now();

        DB::connection('sqlsrv')
            ->table('workdoc')
            ->whereNotNull('project_id')
            ->whereRaw("LEN(TRIM(project_id)) > 0")
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    MirrorWorkdoc::updateOrCreate(
                        ['id' => trim($row->id)],
                        [
                            'project_id'  => trim($row->project_id),
                            'relation_id' => $row->relation_id ?? null,
                            'name'        => trim($row->name ?? ''),
                            'date'        => $row->date ? date('Y-m-d', strtotime($row->date)) : null,
                            'status'      => $row->status ?? null,
                            'fl_invoice'  => (bool) $row->fl_invoice,
                            'fl_finished' => (bool) $row->fl_finished,
                            'fl_paid'     => (bool) $row->fl_paid,
                            'total_price' => $row->total_price ?? 0,
                            'total_paid'  => $row->total_paid ?? 0,
                            'synced_at'   => $now,
                        ]
                    );
                }
            });

        Log::info('SyncMirrorDataService: workdocs sync completed.');
    }
}
