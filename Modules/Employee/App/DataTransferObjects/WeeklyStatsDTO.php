<?php

namespace Modules\Employee\App\DataTransferObjects;

class WeeklyStatsDTO
{
    public function __construct(
        public readonly string $weekStart,
        public readonly string $weekEnd,
        public readonly float $hours,
        public readonly float $productivity,
        public readonly int $completedTasks,
        public readonly float $distance,
        public readonly int $weekNumber,
        public readonly array $details
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            weekStart: $data['week_start'],
            weekEnd: $data['week_end'],
            hours: (float) $data['hours'],
            productivity: (float) $data['productivity'],
            completedTasks: (int) $data['completed_tasks'],
            distance: (float) $data['distance'],
            weekNumber: (int) $data['week_number'],
            details: [
                'approved_hours' => (float) $data['details']['approved_hours'],
                'total_cost'     => (float) $data['details']['total_cost'],
                'total_sales'    => (float) $data['details']['total_sales'],
                'days_worked'    => (int) $data['details']['days_worked'],
            ]
        );
    }

    public function toArray(): array
    {
        return [
            'week_start'     => $this->weekStart,
            'week_end'       => $this->weekEnd,
            'week_number'    => $this->weekNumber,
            'hours'          => $this->hours,
            'productivity'   => $this->productivity,
            'completed_tasks'=> $this->completedTasks,
            'distance'       => $this->distance,
            'details'        => $this->details,
        ];
    }
}
