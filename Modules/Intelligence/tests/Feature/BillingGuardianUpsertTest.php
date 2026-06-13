<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Services\BiConfigService;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Tests\TestCase;

/**
 * Guardian subclass with one detection rule stubbed so the §4.4.1
 * rerun/upsert policy can be exercised without real mirror data.
 */
class FakeRuleGuardian extends MonthlyBillingGuardianService
{
    public array $fakeAlerts = [];

    protected function detectMissingCustomerInvoices(int $year, int $month): array
    {
        return $this->fakeAlerts;
    }
}

class BillingGuardianUpsertTest extends TestCase
{
    use RefreshDatabase;

    private FakeRuleGuardian $guardian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardian = new FakeRuleGuardian(app(BiConfigService::class));
    }

    private function fakeAlert(array $overrides = []): array
    {
        return array_merge([
            'alert_type'           => 'missing_customer_invoice',
            'severity'             => 'high',
            'project_id'           => 'P20260001',
            'amount_activity_cost' => 1500.00,
            'amount_open'          => null,
            'evidence_json'        => ['costs_in_month' => 1500.00],
            'recommendation'       => 'Initial recommendation',
        ], $overrides);
    }

    private function runGuardian(array $alerts, bool $dryRun = false): \Modules\Intelligence\DTOs\BillingGuardianReport
    {
        $this->guardian->fakeAlerts = $alerts;

        return $this->guardian->analyzeMonth(2026, 5, $dryRun);
    }

    // -------------------------------------------------------------------------
    // Creation + dedup
    // -------------------------------------------------------------------------

    public function test_first_run_creates_alert_with_dedup_key(): void
    {
        $report = $this->runGuardian([$this->fakeAlert()]);

        $this->assertSame(1, $report->created);
        $this->assertSame(
            '2026:05:missing_customer_invoice:P20260001:',
            BillingAlert::first()->dedup_key
        );
    }

    public function test_dry_run_detects_but_persists_nothing(): void
    {
        $report = $this->runGuardian([$this->fakeAlert()], dryRun: true);

        $this->assertSame(1, $report->totalDetected);
        $this->assertTrue($report->dryRun);
        $this->assertSame(0, BillingAlert::count());
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->guardian->analyzeMonth(2026, 13);
    }

    // -------------------------------------------------------------------------
    // §4.4.1 rerun policy by status
    // -------------------------------------------------------------------------

    public function test_rerun_updates_open_alert(): void
    {
        $this->runGuardian([$this->fakeAlert()]);

        $report = $this->runGuardian([$this->fakeAlert([
            'amount_activity_cost' => 2750.00,
            'recommendation'       => 'Refreshed recommendation',
            'severity'             => 'critical',
        ])]);

        $alert = BillingAlert::where('alert_type', 'missing_customer_invoice')->first();
        $this->assertSame(1, $report->updated);
        $this->assertSame('2750.00', $alert->amount_activity_cost);
        $this->assertSame('Refreshed recommendation', $alert->recommendation);
        $this->assertSame('critical', $alert->severity);
        $this->assertSame(1, BillingAlert::where('alert_type', 'missing_customer_invoice')->count());
    }

    public function test_rerun_updates_in_review_but_preserves_reviewer(): void
    {
        $this->runGuardian([$this->fakeAlert()]);
        BillingAlert::first()->update([
            'status'      => BillingAlert::STATUS_IN_REVIEW,
            'assigned_to' => 7,
            'reviewed_by' => 7,
        ]);

        $this->runGuardian([$this->fakeAlert(['amount_activity_cost' => 9999.99])]);

        $alert = BillingAlert::where('alert_type', 'missing_customer_invoice')->first();
        $this->assertSame('9999.99', $alert->amount_activity_cost);
        $this->assertSame(BillingAlert::STATUS_IN_REVIEW, $alert->status);
        $this->assertSame(7, $alert->assigned_to);
        $this->assertSame(7, $alert->reviewed_by);
    }

    public function test_rerun_on_confirmed_only_updates_amount_open(): void
    {
        $this->runGuardian([$this->fakeAlert(['amount_open' => 100.00])]);
        BillingAlert::first()->update(['status' => BillingAlert::STATUS_CONFIRMED]);

        $this->runGuardian([$this->fakeAlert([
            'amount_open'          => 50.00,
            'amount_activity_cost' => 8888.88,
            'recommendation'       => 'Must NOT overwrite',
            'severity'             => 'low',
        ])]);

        $alert = BillingAlert::where('alert_type', 'missing_customer_invoice')->first();
        $this->assertSame('50.00', $alert->amount_open);          // refreshed
        $this->assertSame('1500.00', $alert->amount_activity_cost); // untouched
        $this->assertSame('Initial recommendation', $alert->recommendation); // untouched
        $this->assertSame('high', $alert->severity);              // untouched
        $this->assertSame(BillingAlert::STATUS_CONFIRMED, $alert->status);
    }

    public function test_rerun_never_reopens_dismissed(): void
    {
        $this->runGuardian([$this->fakeAlert()]);
        BillingAlert::first()->update(['status' => BillingAlert::STATUS_DISMISSED]);

        $report = $this->runGuardian([$this->fakeAlert(['amount_activity_cost' => 7777.77])]);

        $alert = BillingAlert::where('alert_type', 'missing_customer_invoice')->first();
        $this->assertSame(1, $report->skipped);
        $this->assertSame(BillingAlert::STATUS_DISMISSED, $alert->status);
        $this->assertSame('1500.00', $alert->amount_activity_cost);
    }

    public function test_rerun_never_reopens_resolved(): void
    {
        $this->runGuardian([$this->fakeAlert()]);
        BillingAlert::first()->update(['status' => BillingAlert::STATUS_RESOLVED]);

        $report = $this->runGuardian([$this->fakeAlert()]);

        $this->assertSame(1, $report->skipped);
        $this->assertSame(
            BillingAlert::STATUS_RESOLVED,
            BillingAlert::where('alert_type', 'missing_customer_invoice')->first()->status
        );
    }

    // -------------------------------------------------------------------------
    // Monthly close blocker
    // -------------------------------------------------------------------------

    public function test_blocker_created_while_high_alerts_pending(): void
    {
        $this->runGuardian([$this->fakeAlert(['severity' => 'critical'])]);

        $blocker = BillingAlert::where('alert_type', 'monthly_close_blocker')->first();
        $this->assertNotNull($blocker);
        $this->assertSame('critical', $blocker->severity);
        $this->assertSame(1, $blocker->evidence_json['pending_critical_high']);
    }

    public function test_blocker_auto_resolves_when_alerts_cleared(): void
    {
        $this->runGuardian([$this->fakeAlert(['severity' => 'critical'])]);
        BillingAlert::where('alert_type', 'missing_customer_invoice')
            ->update(['status' => BillingAlert::STATUS_RESOLVED]);

        $this->runGuardian([]); // rerun with nothing pending

        $blocker = BillingAlert::where('alert_type', 'monthly_close_blocker')->first();
        $this->assertSame(BillingAlert::STATUS_RESOLVED, $blocker->status);
        $this->assertNotNull($blocker->resolved_at);
    }

    public function test_no_blocker_for_low_severity_alerts(): void
    {
        $this->runGuardian([$this->fakeAlert(['severity' => 'low'])]);

        $this->assertNull(BillingAlert::where('alert_type', 'monthly_close_blocker')->first());
    }
}
