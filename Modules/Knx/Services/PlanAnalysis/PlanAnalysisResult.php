<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * A provider's raw PROPOSAL for one page, in the shape of PlanAnalysisSchema, plus what
 * produced it. Nothing here is trusted: PlanAnalysisValidator checks it against the input.
 */
final class PlanAnalysisResult
{
    /**
     * @param  array<string, mixed>  $proposal  planKind, groupRoles, symbols, legend, rooms, uncertain (plan-analysis/2)
     * @param  array{inputTokens: ?int, outputTokens: ?int}  $usage
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $model,
        public readonly array $proposal,
        public readonly array $usage = ['inputTokens' => null, 'outputTokens' => null],
    ) {}
}
