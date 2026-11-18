<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanAiAuthorization;
use Modules\Knx\Models\KnxPlanAnalysis;

/**
 * Runs one plan-page analysis: authorization → availability → provider → validation →
 * stored proposal. The authorization is checked BEFORE anything reaches a provider, and
 * a refused or failed provider never falls back to another one.
 */
class PlanAnalysisService
{
    public function __construct(
        private readonly PlanAnalysisProviders $providers,
        private readonly PlanAnalysisValidator $validator,
    ) {}

    public function isAuthorized(KnxDocument $document, string $provider): bool
    {
        return KnxPlanAiAuthorization::query()
            ->where('document_id', $document->getKey())
            ->where('provider', $provider)
            ->exists();
    }

    /**
     * @throws PlanAnalysisFailed
     */
    public function analyze(KnxDocument $document, KnxEmployee $employee, string $provider, PlanAnalysisInput $input): KnxPlanAnalysis
    {
        if (! $this->isAuthorized($document, $provider)) {
            throw new PlanAnalysisFailed('not_authorized', 'This document is not authorized for this provider.');
        }
        if (! $this->providers->isAvailable($provider, $employee)) {
            throw new PlanAnalysisFailed('unavailable', 'This provider is not available in this environment.');
        }

        $result = $this->providers->provider($provider, $employee)->analyze(
            $input,
            fn (): bool => $this->isAuthorized($document, $provider),
        );
        $proposal = $this->validator->validate($input, $result->proposal);

        // Read back what was stored, so the POST answer and a later GET are identical
        // (the JSON column normalizes the proposal).
        return KnxPlanAnalysis::query()->create([
            'project_id' => $document->project_id,
            'document_id' => $document->getKey(),
            'page' => $input->page,
            'document_revision' => $document->revision,
            'provider' => $result->provider,
            'model' => $result->model,
            'schema_version' => (string) config('knx.plan_analysis.schema_version'),
            'proposal' => $proposal,
            'usage' => $result->usage,
            'created_by_employee_id' => $employee->getKey(),
        ])->refresh();
    }

    public function latest(KnxDocument $document, int $page): ?KnxPlanAnalysis
    {
        return KnxPlanAnalysis::query()
            ->with('createdBy')
            ->where('document_id', $document->getKey())
            ->where('page', $page)
            ->latest('id')
            ->first();
    }
}
