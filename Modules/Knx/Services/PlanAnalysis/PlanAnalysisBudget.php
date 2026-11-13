<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Illuminate\Support\Facades\Cache;

/**
 * The backend-enforced spending limits of EXTERNAL plan-analysis providers
 * (config `knx.plan_analysis.budget`). A call is reserved BEFORE it is sent; with the
 * default of 0 calls per day nothing is ever sent.
 */
class PlanAnalysisBudget
{
    public function __construct(private readonly PlanAnalysisProviders $providers) {}

    /**
     * @throws PlanAnalysisFailed `budget_exhausted` when today's calls are used up
     */
    public function reserve(string $provider): void
    {
        if (! $this->providers->isExternal($provider)) {
            return;
        }

        $limit = $this->limitFor($provider);
        if ($limit <= 0) {
            throw new PlanAnalysisFailed('budget_exhausted', 'No external plan-analysis calls are allowed (max_calls_per_day = 0).');
        }

        $key = 'knx:plan-analysis:calls:'.$provider.':'.now()->toDateString();
        Cache::add($key, 0, now()->endOfDay());
        if ((int) Cache::increment($key) > $limit) {
            throw new PlanAnalysisFailed('budget_exhausted', "The daily limit of {$limit} plan-analysis calls is used up.");
        }
    }

    /** A provider's own `max_calls_per_day` when configured, else the global one. */
    public function limitFor(string $provider): int
    {
        $own = config("knx.plan_analysis.providers.{$provider}.max_calls_per_day");

        return $own !== null ? (int) $own : $this->maxCallsPerDay();
    }

    public function maxCallsPerDay(): int
    {
        return (int) config('knx.plan_analysis.budget.max_calls_per_day', 0);
    }

    public function maxOutputTokens(): int
    {
        return max(1, (int) config('knx.plan_analysis.budget.max_output_tokens', 4000));
    }

    public function maxRetries(): int
    {
        return max(0, (int) config('knx.plan_analysis.budget.max_retries', 0));
    }

    public function timeoutSeconds(): int
    {
        return max(1, (int) config('knx.plan_analysis.budget.timeout_seconds', 60));
    }

    public function maxImageBytes(): int
    {
        return max(1, (int) config('knx.plan_analysis.budget.max_image_bytes', 8 * 1024 * 1024));
    }
}
