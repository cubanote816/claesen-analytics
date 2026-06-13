<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Tests\TestCase;

/**
 * BI-053 rule tests — detectOverdueReceivables + detectPartialPayments.
 * Threshold (Auditor Gate case L): strict > min_amount on the open balance.
 * The two rules are mutually exclusive on date_expiration vs today.
 */
class BillingGuardianOverdueTest extends TestCase
{
    use RefreshDatabase;

    private MonthlyBillingGuardianService $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->guardian = app(MonthlyBillingGuardianService::class);
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00', 'Europe/Brussels'));

        MirrorRelation::create(['id' => 100, 'name' => 'TC Tenkie']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeInvoice(string $id, array $overrides = []): MirrorInvoice
    {
        return MirrorInvoice::create(array_merge([
            'id'              => $id,
            'project_id'      => 'P20260001',
            'relation_id'     => 100,
            'total_price'     => 10000,
            'total_paid'      => 0,
            'date'            => '2026-04-01',
            'date_expiration' => '2026-05-01', // overdue vs test-now 2026-06-13
            'fl_paid'         => false,
        ], $overrides));
    }

    private function overdue(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectOverdueReceivables');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    private function partial(): array
    {
        $ref = new \ReflectionMethod($this->guardian, 'detectPartialPayments');

        return $ref->invoke($this->guardian, 2026, 6);
    }

    // -------------------------------------------------------------------------
    // Overdue — trigger conditions
    // -------------------------------------------------------------------------

    public function test_overdue_invoice_triggers_with_client_name(): void
    {
        $this->makeInvoice('F25260007', ['total_price' => 65867.48]);

        $alerts = $this->overdue();

        $this->assertCount(1, $alerts);
        $this->assertSame('overdue_receivable', $alerts[0]['alert_type']);
        $this->assertSame(65867.48, $alerts[0]['amount_open']);
        $this->assertSame('TC Tenkie', $alerts[0]['evidence_json']['client_name']);
        $this->assertSame('F25260007', $alerts[0]['invoice_id']);
    }

    public function test_severity_boundary_at_60_days(): void
    {
        // 60 days overdue → high (boundary: critical requires > 60)
        $this->makeInvoice('F60', ['date_expiration' => '2026-04-14']);
        // 61 days overdue → critical
        $this->makeInvoice('F61', ['date_expiration' => '2026-04-13']);

        $alerts = collect($this->overdue())->keyBy('invoice_id');

        $this->assertSame(60, $alerts['F60']['evidence_json']['days_overdue']);
        $this->assertSame('high', $alerts['F60']['severity']);
        $this->assertSame(61, $alerts['F61']['evidence_json']['days_overdue']);
        $this->assertSame('critical', $alerts['F61']['severity']);
    }

    public function test_partial_payment_reduces_amount_open(): void
    {
        $this->makeInvoice('FPART', ['total_price' => 10000, 'total_paid' => 7500]);

        $alerts = $this->overdue();

        $this->assertSame(2500.0, $alerts[0]['amount_open']);
        $this->assertSame(7500.0, $alerts[0]['evidence_json']['total_paid']);
    }

    // -------------------------------------------------------------------------
    // Overdue — exclusions
    // -------------------------------------------------------------------------

    public function test_case_L_open_balance_exactly_at_threshold_does_not_trigger(): void
    {
        // min_amount = 500 (seeder). Strict >: exactly €500.00 must NOT trigger.
        $this->makeInvoice('FEXACT', ['total_price' => 500.00]);

        $this->assertCount(0, $this->overdue());
    }

    public function test_one_cent_above_threshold_triggers(): void
    {
        $this->makeInvoice('FEDGE', ['total_price' => 500.01]);

        $this->assertCount(1, $this->overdue());
    }

    public function test_below_threshold_does_not_trigger(): void
    {
        $this->makeInvoice('FSMALL', ['total_price' => 420.93]); // real case F24250178

        $this->assertCount(0, $this->overdue());
    }

    public function test_paid_invoice_does_not_trigger(): void
    {
        $this->makeInvoice('FPAID', ['fl_paid' => true]);

        $this->assertCount(0, $this->overdue());
    }

    public function test_credit_note_does_not_trigger(): void
    {
        $this->makeInvoice('CN20260001');

        $this->assertCount(0, $this->overdue());
    }

    public function test_not_yet_expired_does_not_trigger_overdue(): void
    {
        $this->makeInvoice('FFUTURE', ['date_expiration' => '2026-07-01']);

        $this->assertCount(0, $this->overdue());
    }

    public function test_null_expiration_does_not_trigger_overdue(): void
    {
        $this->makeInvoice('FNULL', ['date_expiration' => null]);

        $this->assertCount(0, $this->overdue());
    }

    // -------------------------------------------------------------------------
    // Partial payments — mutual exclusion with overdue
    // -------------------------------------------------------------------------

    public function test_partial_not_yet_expired_triggers_partial_only(): void
    {
        $this->makeInvoice('FP1', [
            'total_price'     => 10000,
            'total_paid'      => 4000,
            'date_expiration' => '2026-07-15', // future
        ]);

        $partial = $this->partial();
        $this->assertCount(1, $partial);
        $this->assertSame('partial_payment', $partial[0]['alert_type']);
        $this->assertSame('medium', $partial[0]['severity']);
        $this->assertSame(6000.0, $partial[0]['amount_open']);

        $this->assertCount(0, $this->overdue());
    }

    public function test_partial_already_expired_triggers_overdue_only(): void
    {
        $this->makeInvoice('FP2', [
            'total_price'     => 10000,
            'total_paid'      => 4000,
            'date_expiration' => '2026-05-01', // past
        ]);

        $this->assertCount(0, $this->partial());
        $this->assertCount(1, $this->overdue());
    }

    public function test_partial_with_zero_paid_does_not_trigger_partial(): void
    {
        $this->makeInvoice('FP3', [
            'total_paid'      => 0,
            'date_expiration' => '2026-07-15',
        ]);

        $this->assertCount(0, $this->partial());
    }

    public function test_partial_with_null_expiration_still_triggers_partial(): void
    {
        $this->makeInvoice('FP4', [
            'total_price'     => 8000,
            'total_paid'      => 3000,
            'date_expiration' => null,
        ]);

        $partial = $this->partial();

        $this->assertCount(1, $partial);
        $this->assertSame(5000.0, $partial[0]['amount_open']);
    }
}
