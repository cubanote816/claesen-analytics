<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Services\BiConfigService;
use Modules\Intelligence\Services\SyncMirrorDataService;
use Tests\TestCase;

class LaborSyncWindowTest extends TestCase
{
    use RefreshDatabase;

    private BiConfigService $config;
    private SyncMirrorDataService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->config = app(BiConfigService::class);
        $this->sync   = app(SyncMirrorDataService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function atBrusselsTime(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-06-13 {$time}", 'Europe/Brussels'));
    }

    private function setWindow(?string $start, ?string $end): void
    {
        $this->config->set('labor_sync_schedule', ['start' => $start, 'end' => $end]);
    }

    public function test_allowed_when_no_window_configured(): void
    {
        $this->setWindow(null, null);

        $this->assertTrue($this->sync->isLaborSyncAllowed());
    }

    public function test_allowed_when_only_start_is_null(): void
    {
        $this->setWindow(null, '06:00');

        $this->assertTrue($this->sync->isLaborSyncAllowed());
    }

    public function test_normal_window_allows_inside(): void
    {
        $this->setWindow('01:00', '05:00');
        $this->atBrusselsTime('02:00');

        $this->assertTrue($this->sync->isLaborSyncAllowed());
    }

    public function test_normal_window_blocks_outside(): void
    {
        $this->setWindow('01:00', '05:00');
        $this->atBrusselsTime('06:00');

        $this->assertFalse($this->sync->isLaborSyncAllowed());
    }

    public function test_normal_window_start_is_inclusive_end_is_exclusive(): void
    {
        $this->setWindow('01:00', '05:00');

        $this->atBrusselsTime('01:00');
        $this->assertTrue($this->sync->isLaborSyncAllowed());

        $this->atBrusselsTime('05:00');
        $this->assertFalse($this->sync->isLaborSyncAllowed());
    }

    public function test_midnight_spanning_window_allows_before_midnight(): void
    {
        $this->setWindow('22:00', '06:00');
        $this->atBrusselsTime('23:30');

        $this->assertTrue($this->sync->isLaborSyncAllowed());
    }

    public function test_midnight_spanning_window_allows_after_midnight(): void
    {
        $this->setWindow('22:00', '06:00');
        $this->atBrusselsTime('03:00');

        $this->assertTrue($this->sync->isLaborSyncAllowed());
    }

    public function test_midnight_spanning_window_blocks_business_hours(): void
    {
        $this->setWindow('22:00', '06:00');

        $this->atBrusselsTime('08:00');
        $this->assertFalse($this->sync->isLaborSyncAllowed());

        $this->atBrusselsTime('14:00');
        $this->assertFalse($this->sync->isLaborSyncAllowed());
    }
}
