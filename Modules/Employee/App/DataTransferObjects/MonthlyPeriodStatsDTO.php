<?php

namespace Modules\Employee\App\DataTransferObjects;

class MonthlyPeriodStatsDTO
{
    public function __construct(
        public readonly string $periodType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array $summary,
        /** @var WeeklyStatsDTO[] */
        public readonly array $weeklyStats
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            periodType: $data['period_type'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            summary: [
                'total_hours'          => (float) $data['summary']['total_hours'],
                'average_productivity' => (float) $data['summary']['average_productivity'],
                'total_tasks'          => (int) $data['summary']['total_tasks'],
                'total_distance'       => (float) $data['summary']['total_distance'],
                'total_cost'           => (float) $data['summary']['total_cost'],
                'total_sales'          => (float) $data['summary']['total_sales'],
                'total_weeks'          => (int) $data['summary']['total_weeks'],
            ],
            weeklyStats: array_map(
                fn(array $w) => WeeklyStatsDTO::fromArray($w),
                $data['weekly_stats']
            )
        );
    }

    public function toArray(): array
    {
        return [
            'period_type'  => $this->periodType,
            'start_date'   => $this->startDate,
            'end_date'     => $this->endDate,
            'summary'      => $this->summary,
            'weekly_stats' => array_map(fn(WeeklyStatsDTO $s) => $s->toArray(), $this->weeklyStats),
        ];
    }
}
