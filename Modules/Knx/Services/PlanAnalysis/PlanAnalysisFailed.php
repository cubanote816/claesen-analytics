<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use RuntimeException;

/**
 * A provider could not produce a usable proposal. `reason` is a stable code for the API:
 * `unavailable`, `budget_exhausted`, `refused`, `incomplete`, `invalid_output`,
 * `provider_error`, `image_too_large`.
 */
final class PlanAnalysisFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
