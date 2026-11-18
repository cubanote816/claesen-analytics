<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * The OpenAI API key of the plan analysis (config `knx.plan_analysis.providers.openai.api_key`,
 * env `KNX_PLAN_ANALYSIS_OPENAI_API_KEY`). Deliberately separate from any other OpenAI
 * key of the application, so enabling plan analysis is its own decision.
 */
final class OpenAiApiKeyCredentials implements OpenAiCredentialSource
{
    public function id(): string
    {
        return 'api_key';
    }

    public function bearerToken(): ?string
    {
        $key = config('knx.plan_analysis.providers.openai.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
