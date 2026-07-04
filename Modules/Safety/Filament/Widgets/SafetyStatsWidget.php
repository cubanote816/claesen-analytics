<?php

declare(strict_types=1);

namespace Modules\Safety\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Models\Answer;

class SafetyStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected ?string $pollingInterval = '60s';
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'safety::filament.widgets.safety-stats-widget';

    public string $period = '30';

    protected function getPeriodOptions(): array
    {
        return [
            '7'  => __('safety::inspections.widgets.periods.7'),
            '30' => __('safety::inspections.widgets.periods.30'),
            '90' => __('safety::inspections.widgets.periods.90'),
        ];
    }

    protected function getPeriodLabel(): string
    {
        return $this->getPeriodOptions()[$this->period] ?? $this->getPeriodOptions()['30'];
    }

    protected function getStats(): array
    {
        $days = max(1, (int) $this->period);
        $rangeStart = now()->subDays($days)->startOfDay();
        $previousRangeStart = now()->subDays($days * 2)->startOfDay();
        $previousRangeEnd = $rangeStart->copy()->subSecond();

        $period = $this->getPeriodLabel();

        $totalCurrent = Inspection::where('completed_at', '>=', $rangeStart)->count();
        $totalPrevious = Inspection::whereBetween('completed_at', [$previousRangeStart, $previousRangeEnd])->count();

        $nokCurrent = Answer::whereHas('inspection', fn ($q) =>
            $q->where('completed_at', '>=', $rangeStart)
        )->where('status', 'nok')->count();

        $totalInspections = Inspection::count();
        $pdfGenerated     = Inspection::whereNotNull('pdf_path')->count();

        // Daily data for sparkline, matching the selected period
        $dailyCounts = Inspection::selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->where('completed_at', '>=', $rangeStart)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        $trend = $totalPrevious > 0
            ? round((($totalCurrent - $totalPrevious) / $totalPrevious) * 100)
            : 0;

        $trendDesc = __('safety::inspections.widgets.stats.trend', [
            'trend' => ($trend >= 0 ? '+' : '') . $trend . '%'
        ]);

        return [
            Stat::make(__('safety::inspections.widgets.stats.title', ['period' => $period]), $totalCurrent)
                ->description($trendDesc)
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'warning')
                ->chart($dailyCounts ?: [0]),

            Stat::make(__('safety::inspections.widgets.stats.nok_title', ['period' => $period]), $nokCurrent)
                ->description(__('safety::inspections.widgets.stats.nok_hint'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($nokCurrent > 5 ? 'danger' : ($nokCurrent > 0 ? 'warning' : 'success')),

            Stat::make(__('safety::inspections.widgets.stats.pdf_reports'), "{$pdfGenerated} / {$totalInspections}")
                ->description(__('safety::inspections.widgets.stats.pdf_hint'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
        ];
    }
}
