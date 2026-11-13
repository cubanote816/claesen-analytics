<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Controllers\Concerns\ResolvesPlanDocument;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanAiAuthorization;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisProviders;

/**
 * Who may send which plan document to which AI provider (KNX-48, plano IA).
 *
 * By default a document may not be sent anywhere. A person grants one provider for one
 * document; revoking takes effect at once. The plan-analysis endpoint checks this
 * BEFORE any provider call and never falls back to another provider.
 */
class PlanAiAuthorizationController extends Controller
{
    use ResolvesPlanDocument;

    public function __construct(private readonly PlanAnalysisProviders $providers) {}

    public function index(string $code, string $documentId): array
    {
        $document = $this->planDocument($code, $documentId);
        $grants = $this->grants($document);

        return [
            'documentId' => (string) $document->getKey(),
            'providers' => array_map(
                fn (string $id): array => $this->entry($id, $grants[$id] ?? null),
                $this->providers->ids(),
            ),
        ];
    }

    public function update(Request $request, string $code, string $documentId, string $provider): array
    {
        $document = $this->planDocument($code, $documentId);
        abort_unless($this->providers->exists($provider), 404);

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        $grant = KnxPlanAiAuthorization::query()->firstOrCreate(
            ['document_id' => $document->getKey(), 'provider' => $provider],
            ['project_id' => $document->project_id, 'granted_by_employee_id' => $employee->getKey()],
        );

        return $this->entry($provider, $grant->load('grantedBy'));
    }

    public function destroy(string $code, string $documentId, string $provider): array
    {
        $document = $this->planDocument($code, $documentId);
        abort_unless($this->providers->exists($provider), 404);

        KnxPlanAiAuthorization::query()
            ->where('document_id', $document->getKey())
            ->where('provider', $provider)
            ->delete();

        return $this->entry($provider, null);
    }

    /**
     * @return array<string, KnxPlanAiAuthorization>
     */
    private function grants(KnxDocument $document): array
    {
        return KnxPlanAiAuthorization::query()
            ->with('grantedBy')
            ->where('document_id', $document->getKey())
            ->get()
            ->keyBy('provider')
            ->all();
    }

    private function entry(string $provider, ?KnxPlanAiAuthorization $grant): array
    {
        return [
            'provider' => $provider,
            'external' => $this->providers->isExternal($provider),
            'available' => $this->providers->isAvailable($provider),
            'authorized' => $grant !== null,
            'grantedAt' => $grant?->created_at?->toIso8601String(),
            'grantedBy' => $grant?->grantedBy?->name,
        ];
    }
}
