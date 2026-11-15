<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
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
        'daily_budget_exhausted' => 429,
        'processing_call_limit' => 429,
        'processing_cost_limit' => 429,
        'pricing_unknown' => 409,
        'retention_not_verified' => 409,
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
            // Plano IA v2 (optional: a v1 client keeps working). Symbol candidates per
            // instance and the detected legend region, in page fractions.
            'symbols' => ['sometimes', 'array', 'max:3000'],
            'symbols.*.id' => ['required', 'string', 'regex:/^s\d+$/', 'max:16'],
            'symbols.*.group' => ['required', 'string', 'max:255'],
            'symbols.*.x' => ['required', 'numeric', 'between:0,1'],
            'symbols.*.y' => ['required', 'numeric', 'between:0,1'],
            'symbols.*.w' => ['required', 'numeric', 'between:0,1'],
            'symbols.*.h' => ['required', 'numeric', 'between:0,1'],
            'symbols.*.paths' => ['required', 'integer', 'min:0'],
            'symbols.*.nearTextIds' => ['present', 'array', 'max:3'],
            'symbols.*.nearTextIds.*' => ['string', Rule::in(is_array($request->input('texts')) ? array_column($request->input('texts'), 'id') : [])],
            'legend' => ['sometimes', 'nullable', 'array:x,y,w,h'],
            'legend.x' => ['required_with:legend', 'numeric', 'between:0,1'],
            'legend.y' => ['required_with:legend', 'numeric', 'between:0,1'],
            'legend.w' => ['required_with:legend', 'numeric', 'between:0,1'],
            'legend.h' => ['required_with:legend', 'numeric', 'between:0,1'],
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
                symbols: $this->symbols($validated['symbols'] ?? []),
                legend: isset($validated['legend']) ? array_map('floatval', [
                    'x' => $validated['legend']['x'], 'y' => $validated['legend']['y'],
                    'w' => $validated['legend']['w'], 'h' => $validated['legend']['h'],
                ]) : null,
            ));
        } catch (PlanAnalysisFailed $e) {
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'code' => 'plan_analysis_'.$e->reason,
                'providerCode' => $e->providerCode,
                'providerParam' => $e->providerParam,
                'requestId' => $e->requestId,
                'resumesAt' => $e->resumesAt,
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

    /**
     * @param  list<array<string, mixed>>  $symbols
     * @return list<array{id: string, group: string, x: float, y: float, w: float, h: float, paths: int, nearTextIds: list<string>}>
     */
    private function symbols(array $symbols): array
    {
        return array_map(fn (array $s): array => [
            'id' => (string) $s['id'],
            'group' => (string) $s['group'],
            'x' => (float) $s['x'],
            'y' => (float) $s['y'],
            'w' => (float) $s['w'],
            'h' => (float) $s['h'],
            'paths' => (int) $s['paths'],
            'nearTextIds' => array_values(array_map('strval', $s['nearTextIds'])),
        ], array_values($symbols));
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
