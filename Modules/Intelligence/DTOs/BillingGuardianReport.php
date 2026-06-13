<?php

namespace Modules\Intelligence\DTOs;

class BillingGuardianReport
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $totalDetected,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        /** @var array<string, int> alert_type → count detected */
        public readonly array $byType,
        public readonly bool $dryRun = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'period'         => sprintf('%d-%02d', $this->year, $this->month),
            'total_detected' => $this->totalDetected,
            'created'        => $this->created,
            'updated'        => $this->updated,
            'skipped'        => $this->skipped,
            'by_type'        => $this->byType,
            'dry_run'        => $this->dryRun,
        ];
    }
}
