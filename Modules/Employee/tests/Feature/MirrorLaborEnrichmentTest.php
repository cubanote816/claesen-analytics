<?php

declare(strict_types=1);

namespace Modules\Employee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Tests\TestCase;

/**
 * Confirms that the enriched columns added in the 2026_06_29_100000 migration
 * persist correctly and that model casts work as expected.
 */
class MirrorLaborEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Persist and retrieve enriched fields
    // -------------------------------------------------------------------------

    public function test_can_store_and_retrieve_all_enriched_fields(): void
    {
        MirrorLabor::create([
            'id'                   => 'TEST001',
            'project_id'           => 'P20260001',
            'employee_id'          => 100,
            'labor_id'             => 5,
            'hours'                => 8.0,
            'date'                 => '2026-06-02',
            'labor_descr'          => 'Werf',
            'h_from_1'             => '07:00:00',
            'h_to_1'               => '15:00:00',
            'h_from_2'             => null,
            'h_to_2'               => null,
            'distance'             => 25.5,
            'fl_approved'          => true,
            'total_costprice'      => 320.50,
            'total_salesprice'     => 480.00,
            'pauze'                => 0.5,
            'fl_pauze'             => true,
            'productivity'         => 1.0,
            'transport_costprice'  => 15.0,
            'transport_salesprice' => 22.5,
        ]);

        $this->assertDatabaseHas('intelligence_mirror_labor', ['id' => 'TEST001']);

        $row = MirrorLabor::find('TEST001');
        $this->assertEquals(8.0,    $row->hours);
        $this->assertEquals('Werf', $row->labor_descr);
        $this->assertEquals(25.5,   $row->distance);
        $this->assertTrue($row->fl_approved);
        $this->assertEquals(320.50, $row->total_costprice);
        $this->assertEquals(480.00, $row->total_salesprice);
        $this->assertEquals(0.5,    $row->pauze);
        $this->assertTrue($row->fl_pauze);
        $this->assertEquals(1.0,    $row->productivity);
        $this->assertEquals(15.0,   $row->transport_costprice);
        $this->assertEquals(22.5,   $row->transport_salesprice);
    }

    // -------------------------------------------------------------------------
    // Nullable enriched fields default to null
    // -------------------------------------------------------------------------

    public function test_enriched_fields_are_nullable(): void
    {
        MirrorLabor::create([
            'id'          => 'TEST002',
            'project_id'  => 'P20260001',
            'employee_id' => 100,
            'hours'       => 4.0,
            'date'        => '2026-06-03',
        ]);

        $row = MirrorLabor::find('TEST002');
        $this->assertNull($row->labor_descr);
        $this->assertNull($row->h_from_1);
        $this->assertNull($row->h_to_1);
        $this->assertNull($row->distance);
        $this->assertNull($row->fl_approved);
        $this->assertNull($row->total_costprice);
        $this->assertNull($row->total_salesprice);
        $this->assertNull($row->transport_costprice);
        $this->assertNull($row->transport_salesprice);
    }

    // -------------------------------------------------------------------------
    // Boolean casts
    // -------------------------------------------------------------------------

    public function test_fl_approved_casts_to_bool(): void
    {
        MirrorLabor::create(['id' => 'T-TRUE',  'project_id' => 'P1', 'employee_id' => 1, 'hours' => 8, 'date' => '2026-06-01', 'fl_approved' => true]);
        MirrorLabor::create(['id' => 'T-FALSE', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 8, 'date' => '2026-06-02', 'fl_approved' => false]);

        $this->assertTrue(MirrorLabor::find('T-TRUE')->fl_approved);
        $this->assertFalse(MirrorLabor::find('T-FALSE')->fl_approved);
    }

    public function test_fl_pauze_casts_to_bool(): void
    {
        MirrorLabor::create(['id' => 'P-TRUE',  'project_id' => 'P1', 'employee_id' => 1, 'hours' => 8, 'date' => '2026-06-01', 'fl_pauze' => true]);
        MirrorLabor::create(['id' => 'P-FALSE', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 8, 'date' => '2026-06-02', 'fl_pauze' => false]);

        $this->assertTrue(MirrorLabor::find('P-TRUE')->fl_pauze);
        $this->assertFalse(MirrorLabor::find('P-FALSE')->fl_pauze);
    }

    // -------------------------------------------------------------------------
    // Date cast
    // -------------------------------------------------------------------------

    public function test_date_field_casts_to_carbon(): void
    {
        MirrorLabor::create(['id' => 'DATE001', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 8, 'date' => '2026-06-15']);

        $row = MirrorLabor::find('DATE001');
        $this->assertInstanceOf(\Carbon\Carbon::class, $row->date);
        $this->assertEquals('2026-06-15', $row->date->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // Float precision
    // -------------------------------------------------------------------------

    public function test_float_fields_preserve_decimal_precision(): void
    {
        MirrorLabor::create([
            'id'                   => 'FLOAT001',
            'project_id'           => 'P1',
            'employee_id'          => 1,
            'hours'                => 7.75,
            'date'                 => '2026-06-01',
            'distance'             => 123.456,
            'total_costprice'      => 1234.56,
            'total_salesprice'     => 2345.67,
            'transport_costprice'  => 45.67,
            'transport_salesprice' => 89.01,
        ]);

        $row = MirrorLabor::find('FLOAT001');
        $this->assertEquals(7.75,    $row->hours);
        $this->assertEquals(123.456, $row->distance);
        $this->assertEquals(1234.56, $row->total_costprice);
        $this->assertEquals(2345.67, $row->total_salesprice);
        $this->assertEquals(45.67,   $row->transport_costprice);
        $this->assertEquals(89.01,   $row->transport_salesprice);
    }

    // -------------------------------------------------------------------------
    // Labor type filtering (used by service layer)
    // -------------------------------------------------------------------------

    public function test_where_labor_descr_filters_correctly(): void
    {
        MirrorLabor::create(['id' => 'L1', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 4, 'date' => '2026-06-01', 'labor_descr' => 'Laden']);
        MirrorLabor::create(['id' => 'L2', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 3, 'date' => '2026-06-01', 'labor_descr' => 'Werf']);
        MirrorLabor::create(['id' => 'L3', 'project_id' => 'P1', 'employee_id' => 1, 'hours' => 1, 'date' => '2026-06-01', 'labor_descr' => 'Mobiliteit']);

        $all    = MirrorLabor::count();
        $laden  = MirrorLabor::where('labor_descr', 'Laden')->count();
        $werf   = MirrorLabor::where('labor_descr', 'Werf')->count();
        $mobil  = MirrorLabor::where('labor_descr', 'Mobiliteit')->count();

        $this->assertEquals(3, $all);
        $this->assertEquals(1, $laden);
        $this->assertEquals(1, $werf);
        $this->assertEquals(1, $mobil);
    }
}
