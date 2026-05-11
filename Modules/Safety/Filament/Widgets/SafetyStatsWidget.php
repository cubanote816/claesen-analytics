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

    protected function getStats(): array
    {
        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd   = now()->subMonth()->endOfMonth();

        $totalThisMonth = Inspection::where('completed_at', '>=', $thisMonthStart)->count();
        $totalLastMonth = Inspection::whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])->count();

        // NOK answers this month
        $nokThisMonth = Answer::whereHas('inspection', fn ($q) =>
            $q->where('completed_at', '>=', $thisMonthStart)
        )->where('status', 'nok')->count();

        $totalInspections = Inspection::count();
        $pdfGenerated     = Inspection::whereNotNull('pdf_path')->count();

        // Daily data for sparkline (last 7 days)
        $dailyCounts = Inspection::selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->where('completed_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        $trend = $totalLastMonth > 0
            ? round((($totalThisMonth - $totalLastMonth) / $totalLastMonth) * 100)
            : 0;

        $trendDesc = __('safety::inspections.widgets.stats.trend', [
            'trend' => ($trend >= 0 ? '+' : '') . $trend . '%'
        ]);

        return [
            Stat::make(__('safety::inspections.widgets.stats.this_month'), $totalThisMonth)
                ->description($trendDesc)
                ->descriptionIcon($trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($trend >= 0 ? 'success' : 'warning')
                ->chart($dailyCounts ?: [0]),

            Stat::make(__('safety::inspections.widgets.stats.nok_this_month'), $nokThisMonth)
                ->description(__('safety::inspections.widgets.stats.nok_hint'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($nokThisMonth > 5 ? 'danger' : ($nokThisMonth > 0 ? 'warning' : 'success')),

            Stat::make(__('safety::inspections.widgets.stats.pdf_reports'), "{$pdfGenerated} / {$totalInspections}")
                ->description(__('safety::inspections.widgets.stats.pdf_hint'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
        ];
    }
}
