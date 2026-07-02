<?php

namespace Modules\Employee\App\DataTransferObjects;

class DailyStatsDTO
{
    public function __construct(
        public readonly string $date,
        public readonly float $hours,
        public readonly float $productivity,
        public readonly int $completedTasks,
        public readonly float $distance,
        public readonly array $details
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            hours: (float) $data['hours'],
            productivity: (float) $data['productivity'],
            completedTasks: (int) $data['completedTasks'],
            distance: (float) $data['distance'],
            details: [
                'startTime' => $data['details']['startTime'],
                'endTime'   => $data['details']['endTime'],
                'breaks'    => (float) $data['details']['breaks'],
            ]
        );
    }

    public function toArray(): array
    {
        return [
            'date'           => $this->date,
            'hours'          => $this->hours,
            'productivity'   => $this->productivity,
            'completedTasks' => $this->completedTasks,
            'distance'       => $this->distance,
            'details'        => $this->details,
        ];
    }
}
