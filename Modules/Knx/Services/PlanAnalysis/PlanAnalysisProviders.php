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

    public function isAvailable(string $id): bool
    {
        if (! $this->exists($id) || ! (bool) config("knx.plan_analysis.providers.{$id}.enabled", false)) {
            return false;
        }

        return match ($id) {
            // Synthetic examples are a development aid: never in staging or production.
            'synthetic' => app()->environment(['local', 'testing']),
            'openai' => filled(config('knx.plan_analysis.providers.openai.model'))
                && filled(config('knx.plan_analysis.providers.openai.api_key')),
            default => false,
        };
    }
}
