<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Services\CashFlowWatchdogService;
use Tests\TestCase;

/**
 * CLA-156 — Credit notes must be subtracted in all financial totals.
 */
class CreditNoteTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(string $id): MirrorProject
    {
        return MirrorProject::create([
            'id'          => $id,
            'name'        => "Test {$id}",
            'relation_id' => null,
            'fl_active'   => true,
            'state'       => 1,
        ]);
    }

    private function makeInvoice(string $id, string $projectId, array $overrides = []): MirrorInvoice
    {
        return MirrorInvoice::create(array_merge([
            'id'                   => $id,
            'project_id'           => $projectId,
            'relation_id'          => null,
            'total_price_vat_excl' => 10000.0,
            'total_price'          => 12100.0,
            'total_paid'           => 0.0,
            'date'                 => '2026-01-15',
            'date_expiration'      => '2026-02-15',
            'fl_paid'              => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // MirrorInvoice model: accessors and scopes
    // -------------------------------------------------------------------------

    public function test_regular_invoice_signed_total_price_vat_excl_is_positive(): void
    {
        $inv = $this->makeInvoice('F001', 'P001', ['total_price_vat_excl' => 5000.0]);

        $this->assertEquals(5000.0, $inv->signed_total_price_vat_excl);
        $this->assertFalse($inv->is_credit_note);
    }

    public function test_credit_note_signed_total_price_vat_excl_is_negative(): void
    {
        $cn = $this->makeInvoice('CN001', 'P001', ['total_price_vat_excl' => 5000.0]);

        $this->assertEquals(-5000.0, $cn->signed_total_price_vat_excl);
        $this->assertTrue($cn->is_credit_note);
    }

    public function test_scope_regular_invoices_excludes_cn(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001');
        $this->makeInvoice('F002', 'P001');
        $this->makeInvoice('CN001', 'P001');

        $count = MirrorInvoice::where('project_id', 'P001')->regularInvoices()->count();
        $this->assertEquals(2, $count);
    }

    public function test_scope_credit_notes_only_returns_cn(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001');
        $this->makeInvoice('CN001', 'P001');
        $this->makeInvoice('CN002', 'P001');

        $count = MirrorInvoice::where('project_id', 'P001')->creditNotes()->count();
        $this->assertEquals(2, $count);
    }

    // -------------------------------------------------------------------------
    // Net total: 3 regular + 1 CN → correct net
    // -------------------------------------------------------------------------

    public function test_net_invoiced_subtracts_credit_note(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001', ['total_price_vat_excl' => 44502.22]);
        $this->makeInvoice('F002', 'P001', ['total_price_vat_excl' => 12183.64]);
        $this->makeInvoice('F003', 'P001', ['total_price_vat_excl' => 12183.64]);
        $this->makeInvoice('CN001', 'P001', ['total_price_vat_excl' => 12183.64]);

        $invoices = MirrorInvoice::where('project_id', 'P001')->get();
        $netInvoiced = $invoices->sum(fn($i) => $i->signed_total_price_vat_excl);

        $this->assertEqualsWithDelta(56685.86, $netInvoiced, 0.01);
    }

    // -------------------------------------------------------------------------
    // Paid CN reduces "received" total
    // -------------------------------------------------------------------------

    public function test_paid_credit_note_reduces_net_received(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001', ['total_price_vat_excl' => 20000.0, 'fl_paid' => true]);
        $this->makeInvoice('CN001', 'P001', ['total_price_vat_excl' => 5000.0, 'fl_paid' => true]);

        $invoices = MirrorInvoice::where('project_id', 'P001')->get();
        $netPaid  = $invoices->where('fl_paid', true)->sum(fn($i) => $i->signed_total_price_vat_excl);

        $this->assertEqualsWithDelta(15000.0, $netPaid, 0.01);
    }

    // -------------------------------------------------------------------------
    // Open balance = net invoiced − net received
    // -------------------------------------------------------------------------

    public function test_open_balance_is_net_invoiced_minus_net_received(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001', ['total_price_vat_excl' => 30000.0, 'fl_paid' => true]);
        $this->makeInvoice('F002', 'P001', ['total_price_vat_excl' => 10000.0, 'fl_paid' => false]);
        $this->makeInvoice('CN001', 'P001', ['total_price_vat_excl' => 5000.0,  'fl_paid' => true]);

        $invoices = MirrorInvoice::where('project_id', 'P001')->get();

        $netInvoiced = $invoices->sum(fn($i) => $i->signed_total_price_vat_excl);
        $netPaid     = $invoices->where('fl_paid', true)->sum(fn($i) => $i->signed_total_price_vat_excl);
        $openBalance = $netInvoiced - $netPaid;

        // Net invoiced = 30k + 10k - 5k = 35k
        // Net paid     = 30k - 5k = 25k
        // Open balance = 10k (only the unpaid regular invoice)
        $this->assertEqualsWithDelta(35000.0, $netInvoiced, 0.01);
        $this->assertEqualsWithDelta(25000.0, $netPaid, 0.01);
        $this->assertEqualsWithDelta(10000.0, $openBalance, 0.01);
    }

    // -------------------------------------------------------------------------
    // Overdue count ignores CNs
    // -------------------------------------------------------------------------

    public function test_overdue_count_excludes_credit_notes(): void
    {
        $this->makeProject('P001');
        // Overdue regular invoice
        $this->makeInvoice('F001', 'P001', [
            'date_expiration' => '2026-01-01',
            'fl_paid'         => false,
        ]);
        // "Overdue" CN — must not count
        $this->makeInvoice('CN001', 'P001', [
            'date_expiration' => '2026-01-01',
            'fl_paid'         => false,
        ]);

        $invoices = MirrorInvoice::where('project_id', 'P001')->get();

        $overdueCount = $invoices->filter(
            fn($i) => !$i->is_credit_note && !$i->fl_paid && $i->date_expiration?->isPast()
        )->count();

        $this->assertEquals(1, $overdueCount);
    }

    // -------------------------------------------------------------------------
    // CashFlowWatchdogService: calculateLiveFinancials uses net totals
    // -------------------------------------------------------------------------

    public function test_watchdog_subtracts_credit_note_from_invoiced(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001', ['total_price_vat_excl' => 20000.0]);
        $this->makeInvoice('CN001', 'P001', ['total_price_vat_excl' => 5000.0]);

        // No labor/material costs → WIP = 0 - netInvoiced
        $service   = app(CashFlowWatchdogService::class);
        $ref       = new \ReflectionMethod($service, 'calculateLiveFinancials');
        $financials = $ref->invoke($service, 'P001');

        $this->assertEqualsWithDelta(15000.0, $financials['total_invoiced'], 0.01);
    }

    // -------------------------------------------------------------------------
    // Staleness: last_invoice uses only regular invoices (not CNs)
    // -------------------------------------------------------------------------

    public function test_watchdog_last_invoice_date_uses_regular_invoices_only(): void
    {
        $this->makeProject('P001');
        $this->makeInvoice('F001', 'P001', ['date' => now()->subDays(60)->toDateString()]);
        // CN dated 1 day ago — must NOT set the "last invoice" date
        $this->makeInvoice('CN001', 'P001', ['date' => now()->subDays(1)->toDateString()]);

        $service    = app(CashFlowWatchdogService::class);
        $ref        = new \ReflectionMethod($service, 'calculateLiveFinancials');
        $financials = $ref->invoke($service, 'P001');

        // The last *regular* invoice was 60 days ago, not 1 day ago.
        // Carbon 3 diffInDays is signed (past → negative), so we check abs value > 30.
        $this->assertGreaterThan(30, abs($financials['days_since_last_invoice']));
    }
}
