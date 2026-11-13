<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * The configured plan-analysis providers (config `knx.plan_analysis.providers`).
 *
 * `available` means the provider can run in THIS environment; it says nothing about a
 * document: sending one still needs that document's authorization for that provider.
 */
class PlanAnalysisProviders
{
    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys((array) config('knx.plan_analysis.providers', []));
    }

    public function exists(string $id): bool
    {
        return in_array($id, $this->ids(), true);
    }

    public function isExternal(string $id): bool
    {
        // Unknown or unflagged providers count as external: the safe side.
        return (bool) config("knx.plan_analysis.providers.{$id}.external", true);
    }

    /**
     * The provider implementation for a configured id.
     */
    public function provider(string $id): PlanAnalysisProvider
    {
        return match ($id) {
            SyntheticPlanAnalysisProvider::ID => app(SyntheticPlanAnalysisProvider::class),
            OpenAiPlanAnalysisProvider::ID => app(OpenAiPlanAnalysisProvider::class),
            default => throw new PlanAnalysisFailed('unavailable', "Unknown plan-analysis provider `{$id}`."),
        };
    }

    public function isAvailable(string $id): bool
    {
        if (! $this->exists($id) || ! (bool) config("knx.plan_analysis.providers.{$id}.enabled", false)) {
            return false;
        }

        return match ($id) {
            // Synthetic examples are a development aid: never in staging or production.
            'synthetic' => app()->environment(['local', 'testing']),
            // Ready when a model is chosen and the bound credential source has a token
            // (today the API key; another source needs no key).
            'openai' => filled(config('knx.plan_analysis.providers.openai.model'))
                && app(OpenAiCredentialSource::class)->bearerToken() !== null,
            default => false,
        };
    }
}
