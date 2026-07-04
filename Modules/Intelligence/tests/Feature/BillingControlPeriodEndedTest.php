<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Filament\Pages\MonthlyBillingControlPage;
use Modules\Intelligence\Models\BillingAlert;
use Tests\TestCase;

/**
 * CLA-219 — "can be closed" must not fire for a month still in progress,
 * regardless of how few (or zero) alerts exist for that period.
 */
class BillingControlPeriodEndedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dataFor(string $period): array
    {
        $page = new MonthlyBillingControlPage();
        $page->setPeriod($period);

        return $page->getMaandafsluitingData();
    }

    public function test_current_in_progress_month_is_not_period_ended_even_with_zero_alerts(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 4, 12, 0, 0, 'Europe/Brussels'));

        $data = $this->dataFor('2026-07');

        $this->assertSame(0, $data['critical_open']);
        $this->assertSame(0, $data['high_open']);
        $this->assertSame(0, $data['confirmed_open']);
        $this->assertFalse($data['period_ended']);
    }

    public function test_future_month_is_not_period_ended(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 4, 12, 0, 0, 'Europe/Brussels'));

        $data = $this->dataFor('2026-08');

        $this->assertFalse($data['period_ended']);
    }

    public function test_past_month_with_zero_alerts_is_period_ended(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 4, 12, 0, 0, 'Europe/Brussels'));

        $data = $this->dataFor('2026-06');

        $this->assertTrue($data['period_ended']);
    }

    public function test_past_month_with_critical_alert_is_period_ended_but_has_open_alert(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 4, 12, 0, 0, 'Europe/Brussels'));

        BillingAlert::create([
            'dedup_key'    => BillingAlert::buildDedupKey(2026, 6, 'missing_customer_invoice', 'P20260099'),
            'period_year'  => 2026,
            'period_month' => 6,
            'alert_type'   => 'missing_customer_invoice',
            'severity'     => 'critical',
            'status'       => BillingAlert::STATUS_OPEN,
            'project_id'   => 'P20260099',
            'evidence_json' => ['costs_in_month' => 1000.00],
            'recommendation' => 'Test recommendation',
        ]);

        $data = $this->dataFor('2026-06');

        $this->assertTrue($data['period_ended']);
        $this->assertSame(1, $data['critical_open']);
    }
}
