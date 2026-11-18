<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use RuntimeException;

/**
 * A provider could not produce a usable proposal. `reason` is a stable code for the API:
 * `unavailable`, `budget_exhausted`, `refused`, `incomplete`, `invalid_output`,
 * `provider_error`, `image_too_large`, the OpenAI spend gates `daily_budget_exhausted`,
 * `processing_call_limit`, `processing_cost_limit`, `pricing_unknown`, `retention_not_verified`, and for the ChatGPT plan `needs_sign_in`,
 * `plan_not_accepted`, `plan_not_eligible`, `plan_usage_limit`, `plan_unavailable`,
 * `plan_unsupported_capability`, `plan_policy`. The provider* fields keep what the
 * provider answered (HTTP status, error code, error param, request id) for the diagnostic.
 */
final class PlanAnalysisFailed extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        public readonly ?int $providerStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerParam = null,
        public readonly ?string $requestId = null,
        public readonly ?string $resumesAt = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
