<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorEstimateCalc;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Modules\Performance\Models\Mirror\MirrorProjectResult;
use Modules\Performance\Models\Mirror\MirrorWorkdoc;
use Tests\TestCase;

class MirrorModelsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // BI-010 — projects: contract_price, type, state
    // -------------------------------------------------------------------------

    public function test_mirror_project_stores_sprint1_columns(): void
    {
        MirrorProject::create([
            'id'             => 'P20260001',
            'name'           => 'Test Project',
            'contract_price' => 125000.50,
            'type'           => 4,
            'state'          => 2,
        ]);

        $project = MirrorProject::find('P20260001');

        $this->assertSame('125000.50', $project->contract_price);
        $this->assertSame(4, $project->type);
        $this->assertSame(2, $project->state);
    }

    public function test_mirror_project_sprint1_columns_are_nullable(): void
    {
        MirrorProject::create(['id' => 'P20260002', 'name' => 'No contract']);

        $project = MirrorProject::find('P20260002');

        $this->assertNull($project->contract_price);
        $this->assertNull($project->type);
        $this->assertNull($project->state);
    }

    // -------------------------------------------------------------------------
    // BI-011 — costs: invoiced boolean
    // -------------------------------------------------------------------------

    public function test_mirror_cost_invoiced_defaults_to_false_and_casts_boolean(): void
    {
        MirrorCost::create([
            'id'         => '100001',
            'project_id' => 'P20260001',
            'cost_price' => 100,
            'quantity'   => 2,
            'date'       => '2026-06-01',
        ]);

        $cost = MirrorCost::find('100001');
        $this->assertFalse($cost->invoiced);

        $cost->update(['invoiced' => true]);
        $this->assertTrue($cost->fresh()->invoiced);
    }

    // -------------------------------------------------------------------------
    // BI-012 — invoices: relation_id, date_expiration, fl_paid
    // -------------------------------------------------------------------------

    public function test_mirror_invoice_stores_sprint1_columns(): void
    {
        MirrorInvoice::create([
            'id'                   => 'F20260001',
            'project_id'           => 'P20260001',
            'relation_id'          => 1769,
            'total_price_vat_excl' => 5000,
            'date'                 => '2026-05-01',
            'date_expiration'      => '2026-05-31',
            'fl_paid'              => false,
        ]);

        $invoice = MirrorInvoice::find('F20260001');

        $this->assertSame(1769, $invoice->relation_id);
        $this->assertSame('2026-05-31', $invoice->date_expiration->toDateString());
        $this->assertFalse($invoice->fl_paid);
    }

    // -------------------------------------------------------------------------
    // BI-013 — estimate_calc: MAMO factors + extra costs JSON
    // -------------------------------------------------------------------------

    public function test_mirror_estimate_calc_stores_factors_and_json(): void
    {
        MirrorEstimateCalc::create([
            'estimate_id'        => 'E20260001',
            'factor_material'    => 24.5690,
            'factor_labor'       => 63.0290,
            'factor_equipment'   => 15.3700,
            'factor_subcontract' => 15.2020,
            'extra_costs_json'   => ['transport' => 350.0, 'management' => null],
        ]);

        $calc = MirrorEstimateCalc::find('E20260001');

        $this->assertSame('24.5690', $calc->factor_material);
        $this->assertSame('63.0290', $calc->factor_labor);
        $this->assertIsArray($calc->extra_costs_json);
        // MySQL JSON round-trip may return int or float — compare loosely
        $this->assertEquals(350, $calc->extra_costs_json['transport']);
    }

    // -------------------------------------------------------------------------
    // BI-014 — project_links: composite key upsert
    // -------------------------------------------------------------------------

    public function test_mirror_project_link_upserts_on_composite_key(): void
    {
        MirrorProjectLink::updateOrCreate(
            ['project_id' => 'P20260001', 'estimate_id' => 'E20260001'],
            ['link_type' => 1]
        );
        MirrorProjectLink::updateOrCreate(
            ['project_id' => 'P20260001', 'estimate_id' => 'E20260001'],
            ['link_type' => 3]
        );

        $this->assertSame(1, MirrorProjectLink::count());
        $this->assertSame(3, MirrorProjectLink::first()->link_type);
    }

    // -------------------------------------------------------------------------
    // BI-015 — project_results: high profit_percent + amount precision
    // -------------------------------------------------------------------------

    public function test_mirror_project_result_accepts_extreme_profit_percent(): void
    {
        // Real case P20180031 NMBS: cost €920, invoiced €110,005 → 11,852%
        MirrorProjectResult::create([
            'project_id'     => 'P20180031',
            'project_name'   => 'NMBS - perronverlichting',
            'costprice_total' => 920.35,
            'invoiced'       => 110005.08,
            'profit'         => 109084.73,
            'profit_percent' => 11852.5268,
        ]);

        $result = MirrorProjectResult::find('P20180031');

        $this->assertSame('11852.5268', $result->profit_percent);
        $this->assertSame('110005.0800', $result->invoiced);
    }

    // -------------------------------------------------------------------------
    // CLA-158 — MirrorCost::priceTypeLabel confirmed from CAFCASYSTEM code=3
    // -------------------------------------------------------------------------

    public function test_price_type_1_returns_materiaal(): void
    {
        $this->assertSame('Materiaal', MirrorCost::priceTypeLabel(1));
        $this->assertSame('Materiaal', MirrorCost::priceTypeLabel('1'));
    }

    public function test_price_type_2_returns_arbeid(): void
    {
        $this->assertSame('Arbeid', MirrorCost::priceTypeLabel(2));
    }

    public function test_price_type_3_returns_materieel(): void
    {
        $this->assertSame('Materieel', MirrorCost::priceTypeLabel(3));
    }

    public function test_price_type_4_returns_onderaanneming(): void
    {
        $this->assertSame('Onderaanneming', MirrorCost::priceTypeLabel(4));
    }

    public function test_price_type_5_returns_element(): void
    {
        $this->assertSame('Element', MirrorCost::priceTypeLabel(5));
    }

    public function test_price_type_0_returns_overige_vrij_provisional(): void
    {
        $this->assertSame('Overige (vrij)', MirrorCost::priceTypeLabel(0));
        $this->assertTrue(MirrorCost::priceTypeIsProvisional(0));
        $this->assertTrue(MirrorCost::priceTypeIsProvisional('0'));
    }

    public function test_price_type_null_returns_onbekend(): void
    {
        $this->assertSame('Onbekend', MirrorCost::priceTypeLabel(null));
        $this->assertFalse(MirrorCost::priceTypeIsProvisional(null));
    }

    public function test_unknown_price_type_99_returns_cafca_type_prefix(): void
    {
        $label = MirrorCost::priceTypeLabel(99);
        $this->assertStringContainsString('CAFCA type', $label);
        $this->assertStringContainsString('99', $label);
        $this->assertFalse(MirrorCost::priceTypeIsProvisional(99));
    }

    // -------------------------------------------------------------------------
    // CLA-157 — MirrorProject type/state label + color accessors
    // -------------------------------------------------------------------------

    public function test_type_4_returns_sportverlichting(): void
    {
        $p = MirrorProject::create(['id' => 'PX001', 'name' => 'T', 'type' => 4]);
        $this->assertSame('Sportverlichting', $p->type_label);
        $this->assertSame('green', $p->type_color);
    }

    public function test_type_5_returns_sportverlichting(): void
    {
        $p = MirrorProject::create(['id' => 'PX002', 'name' => 'T', 'type' => 5]);
        $this->assertSame('Sportverlichting', $p->type_label);
    }

    public function test_type_7_returns_industrie(): void
    {
        $p = MirrorProject::create(['id' => 'PX003', 'name' => 'T', 'type' => 7]);
        $this->assertSame('Industrie', $p->type_label);
        $this->assertSame('gray', $p->type_color);
    }

    public function test_type_2_returns_openbare_verlichting_blue(): void
    {
        $p = MirrorProject::create(['id' => 'PX004', 'name' => 'T', 'type' => 2]);
        $this->assertSame('Openbare Verlichting', $p->type_label);
        $this->assertSame('blue', $p->type_color);
    }

    public function test_unknown_type_returns_onbekend_with_code(): void
    {
        $p = MirrorProject::create(['id' => 'PX005', 'name' => 'T', 'type' => 99]);
        $this->assertStringContainsString('Onbekend', $p->type_label);
        $this->assertStringContainsString('99', $p->type_label);
    }

    public function test_state_11_returns_werken_in_uitvoering_green(): void
    {
        $p = MirrorProject::create(['id' => 'PX006', 'name' => 'T', 'state' => 11]);
        $this->assertSame('Werken in uitvoering', $p->state_label);
        $this->assertSame('green', $p->state_color);
    }

    public function test_state_16_returns_on_hold_orange(): void
    {
        $p = MirrorProject::create(['id' => 'PX007', 'name' => 'T', 'state' => 16]);
        $this->assertSame('On Hold', $p->state_label);
        $this->assertSame('orange', $p->state_color);
    }

    public function test_state_19_returns_geannuleerd_red(): void
    {
        $p = MirrorProject::create(['id' => 'PX008', 'name' => 'T', 'state' => 19]);
        $this->assertSame('Geannuleerd', $p->state_label);
        $this->assertSame('red', $p->state_color);
    }

    public function test_state_18_returns_archief_gray(): void
    {
        $p = MirrorProject::create(['id' => 'PX009', 'name' => 'T', 'state' => 18]);
        $this->assertSame('Archief', $p->state_label);
        $this->assertSame('gray', $p->state_color);
    }

    public function test_unknown_state_returns_onbekend_with_code(): void
    {
        $p = MirrorProject::create(['id' => 'PX010', 'name' => 'T', 'state' => 42]);
        $this->assertStringContainsString('Onbekend', $p->state_label);
        $this->assertStringContainsString('42', $p->state_label);
    }

    // -------------------------------------------------------------------------
    // BI-016 — workdocs: flags + amounts
    // -------------------------------------------------------------------------

    public function test_mirror_workdoc_stores_flags_and_amounts(): void
    {
        MirrorWorkdoc::create([
            'id'          => 'WO20260001',
            'project_id'  => 'P20260001',
            'relation_id' => 1677,
            'date'        => '2026-06-01',
            'status'      => 12,
            'fl_invoice'  => false,
            'fl_finished' => true,
            'fl_paid'     => false,
            'total_price' => 3202850.59,
            'total_paid'  => 0,
        ]);

        $workdoc = MirrorWorkdoc::find('WO20260001');

        $this->assertSame(12, $workdoc->status);
        $this->assertFalse($workdoc->fl_invoice);
        $this->assertTrue($workdoc->fl_finished);
        $this->assertSame('3202850.5900', $workdoc->total_price);
        $this->assertSame('0.0000', $workdoc->total_paid);
    }
}
