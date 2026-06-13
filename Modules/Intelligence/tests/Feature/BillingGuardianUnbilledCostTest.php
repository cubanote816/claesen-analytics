<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorProject;
use Tests\TestCase;

/**
 * BI-054 rule tests — detectUnbilledFollowupCosts.
 *
 * Threshold (min_cost_amount) is evaluated at project level (sum of all
 * unbilled cost items in period). Strict >: exactly at threshold does NOT
 * trigger.
 */
class BillingGuardianUnbilledCostTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyBillingGuardianService $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->guardian = app(MonthlyBillingGuardianService::class);
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Europe/Brussels'));

        MirrorProject::create([
            'id'          => 'P20260001',
            'name'        => 'Sporthal Gent',
            'relation_id' => null,
            'state'       => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCost(string $id, array $overrides = []): MirrorCost
    {
        return MirrorCost::create(array_merge([
            'id'         => $id,
            'project_id' => 'P20260001',
            'art_id'     => null,
            'descr'      => 'Test cost item',
            'type'       => 'M',
            'cost_price' => 100.00,
            'quantity'   => 10.00,   // → 1000.00 total
            'date'       => '2026-06-05',
            'invoiced'   => false,
        ], $overrides));
    }

    private function runRule(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectUnbilledFollowupCosts');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    // -------------------------------------------------------------------------
    // Trigger conditions
    // -------------------------------------------------------------------------

    public function test_unbilled_cost_above_threshold_triggers(): void
    {
        $this->makeCost('C001'); // 100 * 10 = 1000 > 500

        $alerts = $this->runRule();

        $this->assertCount(1, $alerts);
        $this->assertSame('unbilled_followup_cost', $alerts[0]['alert_type']);
        $this->assertSame('P20260001', $alerts[0]['project_id']);
        $this->assertSame(1000.0, $alerts[0]['amount_activity_cost']);
    }

    public function test_severity_medium_below_10000(): void
    {
        $this->makeCost('C001'); // 1000

        $alerts = $this->runRule();

        $this->assertSame('medium', $alerts[0]['severity']);
    }

    public function test_severity_high_above_10000(): void
    {
        $this->makeCost('C001', ['cost_price' => 1200.00, 'quantity' => 10.00]); // 12000

        $alerts = $this->runRule();

        $this->assertSame('high', $alerts[0]['severity']);
    }

    public function test_multiple_items_are_summed_per_project(): void
    {
        // 3 items, each 300 → total 900 > 500
        $this->makeCost('C001', ['cost_price' => 150.00, 'quantity' => 2.0]); // 300
        $this->makeCost('C002', ['cost_price' => 100.00, 'quantity' => 3.0]); // 300
        $this->makeCost('C003', ['cost_price' => 300.00, 'quantity' => 1.0]); // 300

        $alerts = $this->runRule();

        $this->assertCount(1, $alerts);
        $this->assertSame(900.0, $alerts[0]['amount_activity_cost']);
        $this->assertSame(3, $alerts[0]['evidence_json']['count_items']);
        $this->assertSame(900.0, $alerts[0]['evidence_json']['total_amount']);
    }

    public function test_cost_types_are_aggregated_and_sorted(): void
    {
        $this->makeCost('C001', ['type' => 'M', 'cost_price' => 300.0, 'quantity' => 1.0]);
        $this->makeCost('C002', ['type' => 'A', 'cost_price' => 300.0, 'quantity' => 1.0]);
        $this->makeCost('C003', ['type' => 'E', 'cost_price' => 300.0, 'quantity' => 1.0]);

        $alerts   = $this->runRule();
        $types    = $alerts[0]['evidence_json']['cost_types'];

        $this->assertSame(['A', 'E', 'M'], $types);
    }

    public function test_duplicate_cost_types_are_deduplicated(): void
    {
        $this->makeCost('C001', ['type' => 'M', 'cost_price' => 300.0, 'quantity' => 1.0]);
        $this->makeCost('C002', ['type' => 'M', 'cost_price' => 300.0, 'quantity' => 1.0]);

        $alerts = $this->runRule();
        $types  = $alerts[0]['evidence_json']['cost_types'];

        $this->assertSame(['M'], $types);
    }

    // -------------------------------------------------------------------------
    // Threshold boundary (min_cost_amount = 500 from seeder)
    // -------------------------------------------------------------------------

    public function test_exactly_at_threshold_does_not_trigger(): void
    {
        $this->makeCost('C001', ['cost_price' => 50.00, 'quantity' => 10.00]); // exactly 500

        $this->assertCount(0, $this->runRule());
    }

    public function test_one_cent_above_threshold_triggers(): void
    {
        $this->makeCost('C001', ['cost_price' => 500.01, 'quantity' => 1.00]);

        $this->assertCount(1, $this->runRule());
    }

    public function test_below_threshold_does_not_trigger(): void
    {
        $this->makeCost('C001', ['cost_price' => 40.00, 'quantity' => 5.00]); // 200

        $this->assertCount(0, $this->runRule());
    }

    // -------------------------------------------------------------------------
    // Exclusions
    // -------------------------------------------------------------------------

    public function test_invoiced_cost_does_not_trigger(): void
    {
        $this->makeCost('C001', ['invoiced' => true]);

        $this->assertCount(0, $this->runRule());
    }

    public function test_cost_outside_period_does_not_trigger(): void
    {
        $this->makeCost('C001', ['date' => '2026-05-15']); // May, not June

        $this->assertCount(0, $this->runRule());
    }

    public function test_no_costs_returns_empty(): void
    {
        $this->assertCount(0, $this->runRule());
    }

    // -------------------------------------------------------------------------
    // Multi-project
    // -------------------------------------------------------------------------

    public function test_two_projects_each_generate_independent_alert(): void
    {
        MirrorProject::create(['id' => 'P20260002', 'name' => 'Project 2', 'relation_id' => null, 'state' => 1]);

        $this->makeCost('C001', ['project_id' => 'P20260001', 'cost_price' => 600.0, 'quantity' => 1.0]);
        $this->makeCost('C002', ['project_id' => 'P20260002', 'cost_price' => 800.0, 'quantity' => 1.0]);

        $alerts = collect($this->runRule())->keyBy('project_id');

        $this->assertCount(2, $alerts);
        $this->assertSame(600.0, $alerts['P20260001']['amount_activity_cost']);
        $this->assertSame(800.0, $alerts['P20260002']['amount_activity_cost']);
    }

    public function test_project_below_threshold_excluded_while_other_triggers(): void
    {
        MirrorProject::create(['id' => 'P20260002', 'name' => 'Project 2', 'relation_id' => null, 'state' => 1]);

        $this->makeCost('C001', ['project_id' => 'P20260001', 'cost_price' => 100.0, 'quantity' => 1.0]); // 100 ≤ 500
        $this->makeCost('C002', ['project_id' => 'P20260002', 'cost_price' => 800.0, 'quantity' => 1.0]); // 800 > 500

        $alerts = $this->runRule();

        $this->assertCount(1, $alerts);
        $this->assertSame('P20260002', $alerts[0]['project_id']);
    }

    public function test_mixed_invoiced_and_uninvoiced_only_sums_uninvoiced(): void
    {
        // invoiced=false: 600. invoiced=true: 5000. Total uninvoiced 600 > 500 → triggers.
        $this->makeCost('C001', ['cost_price' => 600.0,  'quantity' => 1.0, 'invoiced' => false]);
        $this->makeCost('C002', ['cost_price' => 5000.0, 'quantity' => 1.0, 'invoiced' => true]);

        $alerts = $this->runRule();

        $this->assertCount(1, $alerts);
        $this->assertSame(600.0, $alerts[0]['amount_activity_cost']);
        $this->assertSame(1, $alerts[0]['evidence_json']['count_items']);
    }
}
