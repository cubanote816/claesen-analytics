<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Services\BiConfigService;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Tests\TestCase;

/**
 * BI-052 rule tests — detectMissingCustomerInvoices.
 * Threshold semantics (Auditor Gate case L): strict > min_activity_amount,
 * so activity at exactly the threshold does NOT trigger.
 */
class BillingGuardianMissingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyBillingGuardianService $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->guardian = app(MonthlyBillingGuardianService::class);
    }

    private function makeProject(string $id, ?float $contractPrice = 50000, bool $withEstimateLink = true): void
    {
        MirrorProject::create([
            'id'             => $id,
            'name'           => "Project {$id}",
            'relation_id'    => 999,
            'contract_price' => $contractPrice,
        ]);

        if ($withEstimateLink) {
            MirrorProjectLink::create([
                'project_id'  => $id,
                'estimate_id' => "E{$id}",
                'link_type'   => 1,
            ]);
        }
    }

    private function addCost(string $projectId, float $amount, string $date = '2026-05-15'): void
    {
        static $seq = 1;
        MirrorCost::create([
            'id'         => 'C' . str_pad((string) $seq++, 6, '0', STR_PAD_LEFT),
            'project_id' => $projectId,
            'cost_price' => $amount,
            'quantity'   => 1,
            'date'       => $date,
        ]);
    }

    private function addInvoice(string $id, string $projectId, string $date = '2026-05-20'): void
    {
        MirrorInvoice::create([
            'id'                   => $id,
            'project_id'           => $projectId,
            'total_price_vat_excl' => 1000,
            'date'                 => $date,
        ]);
    }

    private function detect(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectMissingCustomerInvoices');

        return $ref->invoke($this->guardian, 2026, 5);
    }

    // -------------------------------------------------------------------------
    // Trigger conditions
    // -------------------------------------------------------------------------

    public function test_triggers_when_activity_above_threshold_and_no_invoice(): void
    {
        $this->makeProject('PTRIG');
        $this->addCost('PTRIG', 2500.00);

        $alerts = $this->detect();

        $this->assertCount(1, $alerts);
        $this->assertSame('PTRIG', $alerts[0]['project_id']);
        $this->assertSame('high', $alerts[0]['severity']);
        $this->assertSame(2500.00, $alerts[0]['amount_activity_cost']);
        $this->assertSame(50000.0, $alerts[0]['amount_estimated']);
    }

    public function test_case_L_exactly_at_threshold_does_not_trigger(): void
    {
        // Auditor Gate case L: min_activity_amount = 500 (seeder default).
        // Strict > comparison: exactly €500.00 must NOT trigger.
        $this->makeProject('PEXACT');
        $this->addCost('PEXACT', 500.00);

        $this->assertCount(0, $this->detect());
    }

    public function test_one_cent_above_threshold_triggers(): void
    {
        $this->makeProject('PEDGE');
        $this->addCost('PEDGE', 500.01);

        $alerts = $this->detect();

        $this->assertCount(1, $alerts);
        $this->assertSame('PEDGE', $alerts[0]['project_id']);
    }

    public function test_below_threshold_does_not_trigger(): void
    {
        $this->makeProject('PSMALL');
        $this->addCost('PSMALL', 347.51); // real case P20250041

        $this->assertCount(0, $this->detect());
    }

    // -------------------------------------------------------------------------
    // Invoice exclusions
    // -------------------------------------------------------------------------

    public function test_does_not_trigger_when_invoiced_in_month(): void
    {
        $this->makeProject('PINV');
        $this->addCost('PINV', 9016.05); // real case P20260024 Balteau
        $this->addInvoice('F20260100', 'PINV');

        $this->assertCount(0, $this->detect());
    }

    public function test_credit_note_does_not_count_as_invoice(): void
    {
        $this->makeProject('PCN');
        $this->addCost('PCN', 3000.00);
        $this->addInvoice('CN20260005', 'PCN'); // credit note — not a real invoice

        $alerts = $this->detect();

        $this->assertCount(1, $alerts);
        $this->assertSame('PCN', $alerts[0]['project_id']);
    }

    public function test_invoice_outside_month_does_not_exclude(): void
    {
        $this->makeProject('POLD');
        $this->addCost('POLD', 4400.00);
        $this->addInvoice('F20260050', 'POLD', '2026-03-31'); // real case P20260018 pattern

        $alerts = $this->detect();

        $this->assertCount(1, $alerts);
        $this->assertSame('2026-03-31', $alerts[0]['evidence_json']['last_invoice_date']);
        $this->assertSame(61, $alerts[0]['evidence_json']['days_since_last_invoice']);
    }

    // -------------------------------------------------------------------------
    // Contract / estimate exclusions
    // -------------------------------------------------------------------------

    public function test_excluded_when_no_contract_and_no_estimate_link(): void
    {
        $this->makeProject('PINTERNAL', contractPrice: null, withEstimateLink: false);
        $this->addCost('PINTERNAL', 8000.00);

        $this->assertCount(0, $this->detect());
    }

    public function test_included_when_no_contract_but_estimate_link_exists(): void
    {
        // Real case P20260029 Derriks: NO contract but estimate linked → triggers
        $this->makeProject('PNOCONTRACT', contractPrice: null, withEstimateLink: true);
        $this->addCost('PNOCONTRACT', 5600.00);

        $alerts = $this->detect();

        $this->assertCount(1, $alerts);
        $this->assertNull($alerts[0]['amount_estimated']); // no contract → NULL, never invented
        $this->assertFalse($alerts[0]['evidence_json']['has_contract']);
        $this->assertTrue($alerts[0]['evidence_json']['has_estimate_link']);
    }

    public function test_config_can_include_projects_without_contract(): void
    {
        app(BiConfigService::class)->set('billing_guardian_rules', [
            'days_without_invoice'              => 30,
            'min_amount'                        => 500,
            'min_activity_amount'               => 500,
            'min_cost_amount'                   => 500,
            'include_projects_without_contract' => true,
        ]);

        $this->makeProject('PFORCE', contractPrice: null, withEstimateLink: false);
        $this->addCost('PFORCE', 8000.00);

        $this->assertCount(1, $this->detect());
    }
}
