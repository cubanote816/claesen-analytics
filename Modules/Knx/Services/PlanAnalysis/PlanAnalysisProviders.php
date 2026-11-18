<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Modules\Knx\Models\KnxEmployee;

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

    /** How the provider is paid: `simulation`, `subscription` or `api` (unknown counts as `api`). */
    public function billing(string $id): string
    {
        return (string) config("knx.plan_analysis.providers.{$id}.billing", 'api');
    }

    /**
     * The provider implementation for a configured id. `chatgpt` acts for ONE employee.
     */
    public function provider(string $id, ?KnxEmployee $employee = null): PlanAnalysisProvider
    {
        return match ($id) {
            SyntheticPlanAnalysisProvider::ID => app(SyntheticPlanAnalysisProvider::class),
            OpenAiPlanAnalysisProvider::ID => app(OpenAiPlanAnalysisProvider::class),
            ChatGptPlanAnalysisProvider::ID => $employee === null
                ? throw new PlanAnalysisFailed('unavailable', 'The ChatGPT plan needs an employee.')
                : app(ChatGptPlanAnalysisProvider::class)->forEmployee($employee),
            default => throw new PlanAnalysisFailed('unavailable', "Unknown plan-analysis provider `{$id}`."),
        };
    }

    public function isAvailable(string $id, ?KnxEmployee $employee = null): bool
    {
        // The ChatGPT plan belongs to each employee: no employee, no plan.
        if ($id === ChatGptPlanAnalysisProvider::ID) {
            return $this->exists($id) && app(ChatGptPlanAnalysisProvider::class)->availableFor($employee);
        }

        if (! $this->exists($id) || ! (bool) config("knx.plan_analysis.providers.{$id}.enabled", false)) {
            return false;
        }

        return match ($id) {
            // Synthetic examples are a development aid: never in staging or production.
            'synthetic' => app()->environment(['local', 'testing']),
            // Ready when a model is chosen and the bound credential source has a token
            // (today the API key; another source needs no key).
            'openai' => $this->openAiConfigured() && $this->retentionVerified(),
            default => false,
        };
    }

    /**
     * Why a provider is not available when that is more specific than `unavailable`.
     */
    public function unavailableReason(string $id, ?KnxEmployee $employee = null): ?string
    {
        if ($this->isAvailable($id, $employee)) {
            return null;
        }
        if ($id === OpenAiPlanAnalysisProvider::ID && $this->exists($id)
            && (bool) config('knx.plan_analysis.providers.openai.enabled', false)
            && $this->openAiConfigured() && ! $this->retentionVerified()) {
            return 'retention_not_verified';
        }

        return 'unavailable';
    }

    /**
     * Real plans only go to OpenAI when the retention policy approved for OUR account was
     * verified for the configured model and for the features used (`responses` and
     * `image_input`).
     */
    public function retentionVerified(): bool
    {
        $retention = (array) config('knx.plan_analysis.providers.openai.retention', []);
        $verifiedAt = $retention['verified_at'] ?? null;
        if (! filled($retention['policy'] ?? null) || ! filled($verifiedAt)) {
            return false;
        }
        try {
            Carbon::parse((string) $verifiedAt);
        } catch (InvalidFormatException) {
            return false;
        }

        $model = (string) config('knx.plan_analysis.providers.openai.model');

        return in_array($model, $this->csv($retention['models'] ?? null), true)
            && array_diff(['responses', 'image_input'], $this->csv($retention['features'] ?? null)) === [];
    }

    private function openAiConfigured(): bool
    {
        return filled(config('knx.plan_analysis.providers.openai.model'))
            && app(OpenAiCredentialSource::class)->bearerToken() !== null;
    }

    /**
     * @return list<string>
     */
    private function csv(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items), fn (string $item): bool => $item !== ''));
    }
}
