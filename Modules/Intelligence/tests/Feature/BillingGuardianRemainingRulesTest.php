<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Tests\TestCase;

/**
 * BI-055 — detectProjectBillingGaps, detectCreditNotes, detectClosedProjectsWithBalance.
 * No Auditor Gate required — these are visibility/management rules.
 */
class BillingGuardianRemainingRulesTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyBillingGuardianService $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->guardian = app(MonthlyBillingGuardianService::class);
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Europe/Brussels'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeProject(string $id, bool $active = true): MirrorProject
    {
        return MirrorProject::create([
            'id'          => $id,
            'name'        => "Project {$id}",
            'relation_id' => null,
            'fl_active'   => $active,
            'state'       => 1,
        ]);
    }

    private function makeCost(string $projectId, string $date = '2026-06-05'): MirrorCost
    {
        static $seq = 0;
        $seq++;

        return MirrorCost::create([
            'id'         => "C{$seq}",
            'project_id' => $projectId,
            'art_id'     => null,
            'type'       => 'M',
            'cost_price' => 1000.0,
            'quantity'   => 1.0,
            'date'       => $date,
            'invoiced'   => false,
        ]);
    }

    private function makeInvoice(string $id, string $projectId, array $overrides = []): MirrorInvoice
    {
        return MirrorInvoice::create(array_merge([
            'id'              => $id,
            'project_id'      => $projectId,
            'relation_id'     => null,
            'total_price'     => 5000.0,
            'total_paid'      => 0.0,
            'date'            => '2026-05-15',
            'date_expiration' => '2026-06-15',
            'fl_paid'         => false,
        ], $overrides));
    }

    private function billingGaps(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectProjectBillingGaps');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    private function creditNotes(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectCreditNotes');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    private function closedWithBalance(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectClosedProjectsWithBalance');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    // =========================================================================
    // detectProjectBillingGaps
    // =========================================================================

    public function test_billing_gap_triggers_for_active_project_never_invoiced(): void
    {
        $this->makeProject('P20260001');
        $this->makeCost('P20260001'); // activity in period

        $alerts = $this->billingGaps();

        $this->assertCount(1, $alerts);
        $this->assertSame('project_billing_gap', $alerts[0]['alert_type']);
        $this->assertSame('medium', $alerts[0]['severity']);
        $this->assertTrue($alerts[0]['evidence_json']['never_invoiced']);
    }

    public function test_billing_gap_triggers_when_last_invoice_older_than_threshold(): void
    {
        $this->makeProject('P20260001');
        $this->makeCost('P20260001');
        // Last invoice 45 days ago > 30-day threshold
        $this->makeInvoice('F001', 'P20260001', ['date' => '2026-04-29', 'fl_paid' => true]);

        $alerts = $this->billingGaps();

        $this->assertCount(1, $alerts);
        $this->assertGreaterThan(30, $alerts[0]['evidence_json']['days_since_invoice']);
    }

    public function test_billing_gap_does_not_trigger_when_recently_invoiced(): void
    {
        $this->makeProject('P20260001');
        $this->makeCost('P20260001');
        // Invoice 5 days ago — within 30-day threshold
        $this->makeInvoice('F001', 'P20260001', ['date' => '2026-06-08']);

        $this->assertCount(0, $this->billingGaps());
    }

    public function test_billing_gap_does_not_trigger_without_activity_in_period(): void
    {
        $this->makeProject('P20260001');
        // Cost in May, not June
        $this->makeCost('P20260001', '2026-05-10');

        $this->assertCount(0, $this->billingGaps());
    }

    public function test_billing_gap_does_not_trigger_for_inactive_project(): void
    {
        $this->makeProject('P20260001', false); // fl_active = false
        $this->makeCost('P20260001');

        $this->assertCount(0, $this->billingGaps());
    }

    public function test_billing_gap_credit_note_does_not_count_as_invoice(): void
    {
        $this->makeProject('P20260001');
        $this->makeCost('P20260001');
        // Only a credit note exists — not a real invoice
        $this->makeInvoice('CN20260001', 'P20260001', ['date' => '2026-06-10']);

        $alerts = $this->billingGaps();

        $this->assertCount(1, $alerts);
        $this->assertTrue($alerts[0]['evidence_json']['never_invoiced']);
    }

    public function test_billing_gap_no_costs_returns_empty(): void
    {
        $this->assertCount(0, $this->billingGaps());
    }

    // =========================================================================
    // detectCreditNotes
    // =========================================================================

    public function test_credit_note_in_period_generates_low_alert(): void
    {
        $this->makeProject('P20260001');
        $this->makeInvoice('CN20260001', 'P20260001', ['date' => '2026-06-05']);

        $alerts = $this->creditNotes();

        $this->assertCount(1, $alerts);
        $this->assertSame('credit_note', $alerts[0]['alert_type']);
        $this->assertSame('low', $alerts[0]['severity']);
        $this->assertSame('CN20260001', $alerts[0]['invoice_id']);
    }

    public function test_credit_note_outside_period_not_included(): void
    {
        $this->makeProject('P20260001');
        $this->makeInvoice('CN20260001', 'P20260001', ['date' => '2026-05-15']); // May

        $this->assertCount(0, $this->creditNotes());
    }

    public function test_regular_invoice_not_returned_as_credit_note(): void
    {
        $this->makeProject('P20260001');
        $this->makeInvoice('F20260001', 'P20260001', ['date' => '2026-06-10']);

        $this->assertCount(0, $this->creditNotes());
    }

    public function test_multiple_credit_notes_each_generate_alert(): void
    {
        $this->makeProject('P20260001');
        $this->makeProject('P20260002');
        $this->makeInvoice('CN20260001', 'P20260001', ['date' => '2026-06-01']);
        $this->makeInvoice('CN20260002', 'P20260002', ['date' => '2026-06-10']);

        $this->assertCount(2, $this->creditNotes());
    }

    // =========================================================================
    // detectClosedProjectsWithBalance
    // =========================================================================

    public function test_closed_project_with_unpaid_invoice_triggers_high_alert(): void
    {
        $this->makeProject('P20260001', false); // inactive
        $this->makeInvoice('F001', 'P20260001', ['total_price' => 10000.0, 'fl_paid' => false]);

        $alerts = $this->closedWithBalance();

        $this->assertCount(1, $alerts);
        $this->assertSame('closed_with_balance', $alerts[0]['alert_type']);
        $this->assertSame('high', $alerts[0]['severity']);
        $this->assertSame('P20260001', $alerts[0]['project_id']);
        $this->assertSame(10000.0, $alerts[0]['amount_open']);
    }

    public function test_active_project_with_unpaid_invoice_does_not_trigger(): void
    {
        $this->makeProject('P20260001', true); // active
        $this->makeInvoice('F001', 'P20260001', ['fl_paid' => false]);

        $this->assertCount(0, $this->closedWithBalance());
    }

    public function test_closed_project_with_paid_invoice_does_not_trigger(): void
    {
        $this->makeProject('P20260001', false);
        $this->makeInvoice('F001', 'P20260001', ['total_price' => 5000.0, 'total_paid' => 5000.0, 'fl_paid' => true]);

        $this->assertCount(0, $this->closedWithBalance());
    }

    public function test_closed_project_below_min_amount_does_not_trigger(): void
    {
        $this->makeProject('P20260001', false);
        $this->makeInvoice('F001', 'P20260001', ['total_price' => 300.0]); // < 500

        $this->assertCount(0, $this->closedWithBalance());
    }

    public function test_closed_project_with_multiple_invoices_sums_balance(): void
    {
        $this->makeProject('P20260001', false);
        $this->makeInvoice('F001', 'P20260001', ['total_price' => 3000.0]);
        $this->makeInvoice('F002', 'P20260001', ['total_price' => 4000.0]);

        $alerts = $this->closedWithBalance();

        $this->assertCount(1, $alerts);
        $this->assertSame(7000.0, $alerts[0]['amount_open']);
        $this->assertSame(2, $alerts[0]['evidence_json']['invoice_count']);
    }

    public function test_credit_note_on_closed_project_does_not_trigger(): void
    {
        $this->makeProject('P20260001', false);
        $this->makeInvoice('CN20260001', 'P20260001', ['total_price' => 10000.0]); // CN% → excluded

        $this->assertCount(0, $this->closedWithBalance());
    }

    public function test_no_invoices_returns_empty(): void
    {
        $this->assertCount(0, $this->closedWithBalance());
    }
}
