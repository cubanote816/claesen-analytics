<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Controllers\Concerns\ResolvesPlanDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanAnalysis;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisFailed;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisInput;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisProviders;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisService;

/**
 * AI proposal for one plan page (KNX-48, plano IA).
 *
 * POST sends the rendered page and Kantoor's summary to ONE provider, only when the
 * document is authorized for it, and stores the validated proposal. GET reads the latest
 * proposal of a page (`analysis: null` when there is none).
 */
class PlanAnalysisController extends Controller
{
    use ResolvesPlanDocument;

    private const FAILURE_STATUS = [
        'not_authorized' => 403,
        'unavailable' => 409,
        'budget_exhausted' => 429,
        'image_too_large' => 413,
        'refused' => 502,
        'incomplete' => 502,
        'invalid_output' => 502,
        'provider_error' => 502,
        'needs_sign_in' => 409,
        'plan_usage_limit' => 429,
        'plan_not_eligible' => 403,
        'plan_policy' => 403,
        'plan_not_accepted' => 403,
        'plan_unavailable' => 503,
        'plan_unsupported_capability' => 502,
    ];

    public function __construct(
        private readonly PlanAnalysisService $analyses,
        private readonly PlanAnalysisProviders $providers,
    ) {}

    public function show(Request $request, string $code, string $documentId): array
    {
        $validated = $request->validate(['page' => ['sometimes', 'nullable', 'integer', 'min:1']]);
        $document = $this->planDocument($code, $documentId);
        $analysis = $this->analyses->latest($document, (int) ($validated['page'] ?? 1));

        return [
            'documentId' => (string) $document->getKey(),
            'documentRevision' => $document->revision,
            'analysis' => $analysis === null ? null : $this->present($analysis),
        ];
    }

    public function store(Request $request, string $code, string $documentId): JsonResponse
    {
        $document = $this->planDocument($code, $documentId);

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'provider' => ['required', 'string', 'max:32'],
            'image' => ['required', 'string'],
            'pageWidthPt' => ['required', 'numeric', 'gt:0'],
            'pageHeightPt' => ['required', 'numeric', 'gt:0'],
            'groups' => ['present', 'array', 'max:2000'],
            'groups.*.id' => ['required', 'string', 'max:255'],
            'groups.*.kind' => ['required', 'string', 'in:layer,style'],
            'groups.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'groups.*.paths' => ['required', 'integer', 'min:0'],
            'groups.*.strokeWidth' => ['present', 'nullable', 'numeric', 'min:0'],
            'groups.*.strokeColor' => ['present', 'nullable', 'string', 'max:32'],
            'groups.*.closedPct' => ['required', 'integer', 'between:0,100'],
            'groups.*.curvedPct' => ['required', 'integer', 'between:0,100'],
            'texts' => ['present', 'array', 'max:5000'],
            'texts.*.id' => ['required', 'string', 'max:64'],
            'texts.*.text' => ['required', 'string', 'max:500'],
            'texts.*.x' => ['required', 'numeric', 'between:0,1'],
            'texts.*.y' => ['required', 'numeric', 'between:0,1'],
        ]);

        if (! $this->providers->exists($validated['provider'])) {
            throw ValidationException::withMessages(['provider' => ['Unknown plan-analysis provider.']]);
        }
        $png = base64_decode($validated['image'], true);
        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw ValidationException::withMessages(['image' => ['The image must be a base64-encoded PNG.']]);
        }

        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        try {
            $analysis = $this->analyses->analyze($document, $employee, $validated['provider'], new PlanAnalysisInput(
                page: (int) $validated['page'],
                pageWidthPt: (float) $validated['pageWidthPt'],
                pageHeightPt: (float) $validated['pageHeightPt'],
                imagePng: $png,
                groups: $this->groups($validated['groups']),
                texts: $this->texts($validated['texts']),
            ));
        } catch (PlanAnalysisFailed $e) {
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'code' => 'plan_analysis_'.$e->reason,
                'providerCode' => $e->providerCode,
                'providerParam' => $e->providerParam,
                'requestId' => $e->requestId,
            ], fn (mixed $value): bool => $value !== null), self::FAILURE_STATUS[$e->reason] ?? 502);
        }

        return response()->json([
            'documentId' => (string) $document->getKey(),
            'documentRevision' => $document->revision,
            'analysis' => $this->present($analysis->load('createdBy')),
        ], 201);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array{id: string, kind: string, name: ?string, paths: int, strokeWidth: ?float, strokeColor: ?string, closedPct: int, curvedPct: int}>
     */
    private function groups(array $groups): array
    {
        return array_map(fn (array $g): array => [
            'id' => (string) $g['id'],
            'kind' => (string) $g['kind'],
            'name' => $g['name'] === null ? null : (string) $g['name'],
            'paths' => (int) $g['paths'],
            'strokeWidth' => $g['strokeWidth'] === null ? null : (float) $g['strokeWidth'],
            'strokeColor' => $g['strokeColor'] === null ? null : (string) $g['strokeColor'],
            'closedPct' => (int) $g['closedPct'],
            'curvedPct' => (int) $g['curvedPct'],
        ], array_values($groups));
    }

    /**
     * @param  list<array<string, mixed>>  $texts
     * @return list<array{id: string, text: string, x: float, y: float}>
     */
    private function texts(array $texts): array
    {
        return array_map(fn (array $t): array => [
            'id' => (string) $t['id'],
            'text' => (string) $t['text'],
            'x' => (float) $t['x'],
            'y' => (float) $t['y'],
        ], array_values($texts));
    }

    private function present(KnxPlanAnalysis $analysis): array
    {
        return [
            'id' => (string) $analysis->getKey(),
            'page' => $analysis->page,
            'revision' => $analysis->document_revision,
            'provider' => $analysis->provider,
            'billing' => $this->providers->billing($analysis->provider),
            'model' => $analysis->model,
            'schemaVersion' => $analysis->schema_version,
            'proposal' => $analysis->proposal,
            'usage' => $analysis->usage,
            'createdAt' => $analysis->created_at?->toIso8601String(),
            'createdBy' => $analysis->createdBy?->name,
        ];
    }
}
