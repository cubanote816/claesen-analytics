<?php

declare(strict_types=1);

namespace Modules\Knx\Services\AiSpend;

use RuntimeException;

/**
 * A call was refused BEFORE it could be sent. `reason`: `daily_budget_exhausted`,
 * `processing_call_limit`, `processing_cost_limit` or `pricing_unknown`.
 */
final class AiSpendRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        public readonly ?float $remainingUsd = null,
        public readonly ?string $resumesAt = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
