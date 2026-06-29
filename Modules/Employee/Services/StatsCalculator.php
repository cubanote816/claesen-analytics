<?php

namespace Modules\Employee\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatsCalculator
{
    public function getDailyHours(Collection $entries, float $targetWeeklyHours): array
    {
        $today        = Carbon::today();
        $todayEntries = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->isToday());
        $totalHours   = $todayEntries->sum('hours');
        $targetDaily  = $targetWeeklyHours / 5;

        return [
            'date'                   => $today->format('Y-m-d'),
            'hours'                  => round($totalHours, 2),
            'target_hours'           => round($targetDaily, 2),
            'achievement_percentage' => $targetDaily > 0 ? round(($totalHours / $targetDaily) * 100, 2) : 0,
            'approved_hours'         => round($todayEntries->where('fl_approved', true)->sum('hours'), 2),
            'costs'                  => round($todayEntries->sum('total_costprice'), 2),
            'revenue'                => round($todayEntries->sum('total_salesprice'), 2),
        ];
    }

    public function getWeeklyHours(Collection $entries, float $targetWeeklyHours): array
    {
        $startOfWeek  = Carbon::now()->startOfWeek();
        $endOfWeek    = Carbon::now()->endOfWeek();
        $weekEntries  = $entries->filter(fn($e) => Carbon::parse($e->entry_date)->between($startOfWeek, $endOfWeek));
        $totalHours   = $weekEntries->sum('hours');
        $daysWorked   = $weekEntries->pluck('entry_date')->unique()->count();

        return [
            'start_date'             => $startOfWeek->format('Y-m-d'),
            'end_date'               => $endOfWeek->format('Y-m-d'),
            'hours'                  => round($totalHours, 2),
            'target_hours'           => round($targetWeeklyHours, 2),
            'achievement_percentage' => $targetWeeklyHours > 0 ? round(($totalHours / $targetWeeklyHours) * 100, 2) : 0,
            'approved_hours'         => round($weekEntries->where('fl_approved', true)->sum('hours'), 2),
            'costs'                  => round($weekEntries->sum('total_costprice'), 2),
            'revenue'                => round($weekEntries->sum('total_salesprice'), 2),
            'days_worked'            => $daysWorked,
            'daily_average'          => $daysWorked > 0 ? round($totalHours / $daysWorked, 2) : 0,
        ];
    }
}
