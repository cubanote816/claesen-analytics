<?php

namespace Modules\Employee\App\DataTransferObjects;

class PeriodStatsDTO
{
    public function __construct(
        public readonly string $periodType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array $summary,
        /** @var DailyStatsDTO[] */
        public readonly array $dailyStats
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
                'total_days'           => (int) $data['summary']['total_days'],
            ],
            dailyStats: array_map(
                fn(array $daily) => DailyStatsDTO::fromArray($daily),
                $data['daily_stats']
            )
        );
    }

    public function toArray(): array
    {
        return [
            'period_type' => $this->periodType,
            'start_date'  => $this->startDate,
            'end_date'    => $this->endDate,
            'summary'     => $this->summary,
            'daily_stats' => array_map(fn(DailyStatsDTO $s) => $s->toArray(), $this->dailyStats),
        ];
    }
}
