<?php

namespace Tests\Feature;

use Modules\Cafca\Models\Employee;
use Modules\Performance\Filament\Widgets\EmployeePerformanceChartWidget;
use Modules\Performance\Filament\Widgets\EmployeePerformanceStatsWidget;
use Modules\Performance\Services\EmployeePerformanceService;
use Modules\Performance\Services\TechnicianAnalysisService;
use Tests\TestCase;

class EmployeePerformanceAchievementTest extends TestCase
{
    // No RefreshDatabase: all tests operate on in-memory objects only.
    // The seam is the protected methods of the service and widgets — no DB required.

    private function makeService(): EmployeePerformanceService
    {
        $analysisMock = $this->createMock(TechnicianAnalysisService::class);
        return new EmployeePerformanceService($analysisMock);
    }

    // Test subclass exposes protected buildDailyAchievementStat() without touching public API.
    private function makeTestStatsWidget(): object
    {
        return new class extends EmployeePerformanceStatsWidget {
            public function exposeDailyStat(?float $rate, float $hours): \Filament\Widgets\StatsOverviewWidget\Stat
            {
                return $this->buildDailyAchievementStat($rate, $hours);
            }
        };
    }

    // Test subclass exposes protected buildChartDatasets() without touching public API.
    private function makeTestChartWidget(): object
    {
        return new class extends EmployeePerformanceChartWidget {
            public function exposeDatasets(array $realHours, ?float $targetDaily): array
            {
                return $this->buildChartDatasets($realHours, $targetDaily);
            }
        };
    }

    // ── Service: calculateAchievementRate ───────────────────────────────────

    public function test_returns_null_when_uren_per_week_is_null(): void
    {
        $this->assertNull($this->makeService()->calculateAchievementRate(8.0, null, 1));
    }

    public function test_returns_null_when_uren_per_week_is_zero(): void
    {
        $this->assertNull($this->makeService()->calculateAchievementRate(8.0, 0.0, 1));
    }

    public function test_returns_null_when_uren_per_week_is_negative(): void
    {
        $this->assertNull($this->makeService()->calculateAchievementRate(8.0, -5.0, 1));
    }

    public function test_returns_correct_percentage_for_positive_uren_per_week(): void
    {
        // 8h real / (40h ÷ 5 days) = 8h / 8h = 100 %
        $this->assertEqualsWithDelta(
            100.0,
            $this->makeService()->calculateAchievementRate(8.0, 40.0, 1),
            0.001
        );
    }

    // ── Stats widget: buildDailyAchievementStat ─────────────────────────────

    public function test_daily_stat_shows_achievement_unknown_when_rate_is_null(): void
    {
        $widget = $this->makeTestStatsWidget();
        $stat   = $widget->exposeDailyStat(null, 0.0);

        // Must return a Stat, not throw TypeError from round(null).
        $this->assertInstanceOf(\Filament\Widgets\StatsOverviewWidget\Stat::class, $stat);
        $this->assertSame('gray', $stat->getColor());
        // Value must not contain '%'; rounding null is never called.
        $this->assertStringNotContainsString('%', (string) $stat->getValue());
    }

    public function test_daily_stat_shows_percentage_and_trend_icon_when_rate_is_float(): void
    {
        $widget = $this->makeTestStatsWidget();
        $stat   = $widget->exposeDailyStat(80.0, 6.4);

        $this->assertStringContainsString('%', (string) $stat->getValue());
        $this->assertStringContainsString('arrow-trending', (string) $stat->getDescriptionIcon());
        $this->assertNotSame('gray', $stat->getColor());
    }

    // ── Chart widget: buildChartDatasets ────────────────────────────────────

    public function test_chart_omits_target_dataset_when_target_is_null(): void
    {
        $widget   = $this->makeTestChartWidget();
        $datasets = $widget->exposeDatasets(array_fill(0, 7, 0.0), null);

        // Only the real-hours dataset is present; target line must not be added.
        // Seam: buildChartDatasets() is tested directly — no DB/HTTP involved.
        $this->assertCount(1, $datasets);
        // The single dataset must NOT be the target one (locale-independent check on count).
        $this->assertArrayHasKey('data', $datasets[0]);
        $this->assertCount(7, $datasets[0]['data']);
    }

    public function test_chart_includes_target_dataset_when_target_is_positive(): void
    {
        $widget   = $this->makeTestChartWidget();
        $datasets = $widget->exposeDatasets([8.0, 6.0, 7.5, 0.0, 8.0, 7.0, 8.5], 8.0);

        $this->assertCount(2, $datasets);
        $this->assertTrue(
            in_array(__('performance::dashboard.target'), array_column($datasets, 'label'), true)
        );
    }
}
