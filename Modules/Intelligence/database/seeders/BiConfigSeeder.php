<?php

namespace Modules\Intelligence\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intelligence\Models\BiConfig;

class BiConfigSeeder extends Seeder
{
    // Uses firstOrCreate so re-running never overwrites management-edited values.

    public function run(): void
    {
        $entries = [
            [
                'config_key'   => 'project_type_labels',
                'config_value' => [
                    // project.type → category name. Source: syncProjects categoryMap.
                    // Management should confirm/rename via BiConfigPage.
                    '0' => 'Industrie',
                    '1' => 'Industrie',
                    '2' => 'Openbare Verlichting',
                    '3' => 'Openbare Verlichting',
                    '4' => 'Sportverlichting',
                    '5' => 'Sportverlichting',
                    '6' => 'Masten',
                    '7' => 'Industrie',
                    '8' => 'Algemeen',
                ],
                'label'       => 'Project type labels',
                'description' => 'Human-readable names for project.type (0-8) from CAFCA ERP. '
                               . 'Used throughout BI dashboards and offer simulator. '
                               . 'Initial values from sync code — confirm with management.',
            ],
            [
                'config_key'   => 'estimate_status_labels',
                'config_value' => [
                    // estimate.status → label. Counts from validation (6,676 estimates).
                    // status=2 has 0 records — excluded. management confirms meaning.
                    '0' => null,  // 27 records  — legacy/unclassified
                    '1' => null,  // 58 records  — likely: draft
                    '3' => null,  // 208 records — likely: sent to client (84% sent=true)
                    '4' => null,  // 6089 records — bulk active (45% sent)
                    '5' => null,  // 236 records — likely: internal review
                    '6' => null,  // 36 records  — likely: lost/rejected (31% sent)
                    '7' => null,  // 22 records  — likely: archived
                ],
                'label'       => 'Estimate status labels',
                'description' => 'Human-readable names for estimate.status codes from CAFCA. '
                               . 'Status=4 is 91% of all estimates. Status=3 best proxy for "sent to client". '
                               . 'Status=2 has 0 records — omitted. Management must fill all labels.',
            ],
            [
                'config_key'   => 'variant_margin_targets',
                'config_value' => [
                    // Offer simulator: 3 variants. Percentages (not decimal fractions).
                    // Historical CAFCA range: 15-35%. Adjust after real data analysis.
                    'economy'  => 20,
                    'standard' => 27,
                    'premium'  => 35,
                ],
                'label'       => 'Variant margin targets (%)',
                'description' => 'Target profit margins for the three offer simulator variants. '
                               . 'Economy / Standard / Premium. Values in percent (e.g. 27 = 27%). '
                               . 'Historical CAFCA range is 15-35%. Adjust after reviewing rpt_project_results.',
            ],
            [
                'config_key'   => 'labor_sync_schedule',
                'config_value' => [
                    // followup_labor_analytical is blocked during active CAFCA use.
                    // Set start/end to restrict sync to off-hours (e.g. 22:00-06:00).
                    // null = no restriction (sync runs any time).
                    'start' => null,
                    'end'   => null,
                ],
                'label'       => 'Labor sync schedule',
                'description' => 'Safe time window (HH:MM) for syncing followup_labor_analytical. '
                               . 'This table is locked during active CAFCA use. '
                               . 'Leave null to allow sync at any time (not recommended in production).',
            ],
            [
                'config_key'   => 'billing_guardian_rules',
                'config_value' => [
                    // Thresholds for Monthly Billing Guardian alert detection.
                    // days_without_invoice: flag projects with no invoice for N days.
                    'days_without_invoice'            => 30,
                    // min_amount: minimum open balance (€) for overdue_receivable alerts.
                    'min_amount'                      => 500,
                    // min_cost_amount: minimum unbilled cost (€) per project/month for alert.
                    'min_cost_amount'                 => 500,
                    // include_projects_without_contract: alert even if no contract_price set.
                    'include_projects_without_contract' => false,
                ],
                'label'       => 'Monthly Billing Guardian rules',
                'description' => 'Configurable thresholds for the Monthly Billing Guardian. '
                               . 'days_without_invoice: alert after N days with no invoice on active project (default 30). '
                               . 'min_amount: skip overdue invoices below this € threshold (default €500). '
                               . 'min_cost_amount: skip unbilled cost alerts below this € threshold (default €500). '
                               . 'include_projects_without_contract: if false, skip projects with no contract_price.',
            ],
        ];

        foreach ($entries as $entry) {
            BiConfig::firstOrCreate(
                ['config_key' => $entry['config_key']],
                [
                    'config_value' => $entry['config_value'],
                    'label'        => $entry['label'],
                    'description'  => $entry['description'] ?? null,
                ]
            );
        }
    }
}
