<?php

namespace Modules\Intelligence\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

class ClaudeVisionService
{
    protected ?string $apiKey;

    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
        $this->model = config('services.anthropic.vision_model', 'claude-sonnet-5');
    }

    /**
     * Compare a luminaire frame photo against the internal catalog and return
     * identification candidates with confidence and evidence.
     *
     * Never forced to identify a luminaire — "unknown" is a valid, expected result
     * when there is not enough visual evidence to support a match.
     *
     * CLA-389: when nothing in the internal catalog matches, a candidate may still
     * carry a non-authoritative `suggested_brand`/`suggested_model` guess (derived
     * from the model's own knowledge of the brands present in $catalog, not from a
     * second catalog) — capped at "probable", never "identified", since it can't be
     * verified against real hardware data. The caller (LuminaireVisionController)
     * never persists anything from this; a technician must explicitly confirm.
     *
     * @param  string  $imageBase64  Raw base64 image data (no data: URI prefix).
     * @param  string  $imageMediaType  e.g. "image/jpeg", "image/png".
     * @param  array<int, array{id: int, name: string, product_family: ?string, model_reference: ?string, typical_application: ?string, brand: ?string, group_name: ?string}>  $catalog
     * @return array{status: string, candidates: array<int, array{catalog_id: ?int, suggested_brand: ?string, suggested_model: ?string, confidence: float, evidence: array<int, string>, status: string}>}
     */
    public function identifyLuminaires(string $imageBase64, string $imageMediaType, array $catalog): array
    {
        $abstain = ['status' => 'unknown', 'candidates' => []];

        if (empty($this->apiKey)) {
            Log::error('Anthropic API key is missing.');

            return $abstain;
        }

        if (empty($catalog)) {
            Log::warning('ClaudeVisionService called with an empty luminaire catalog.');

            return $abstain;
        }

        try {
            $client = new Client(apiKey: $this->apiKey);

            $message = $client->messages->create(
                maxTokens: 2048,
                model: $this->model,
                system: $this->systemPrompt(),
                outputConfig: [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $this->responseSchema(),
                    ],
                ],
                messages: [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'mediaType' => $imageMediaType,
                                    'data' => $imageBase64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $this->buildPrompt($catalog),
                            ],
                        ],
                    ],
                ],
            );

            $textBlock = collect($message->content)->first(
                fn ($block) => ($block->type ?? null) === 'text'
            );

            $decoded = json_decode($textBlock->text ?? '{}', true);

            if (! is_array($decoded) || ! isset($decoded['status'], $decoded['candidates'])) {
                return $abstain;
            }

            $decoded['candidates'] = array_map($this->clampExternalSuggestions(...), $decoded['candidates']);

            return $decoded;
        } catch (\Throwable $e) {
            Log::error('Claude Vision Exception: '.$e->getMessage());

            return $abstain;
        }
    }

    /**
     * Defense in depth: never trust the model to police its own "identified" claim
     * for a candidate that has no internal catalog match — an out-of-catalog guess
     * is inherently unverifiable, so it's downgraded to "probable" regardless of
     * what the prompt asked for. Public (not just protected) so this pure rule is
     * directly unit-testable without a live Anthropic call.
     *
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function clampExternalSuggestions(array $candidate): array
    {
        $isExternalGuess = ($candidate['catalog_id'] ?? null) === null
            && (($candidate['suggested_brand'] ?? null) !== null || ($candidate['suggested_model'] ?? null) !== null);

        if ($isExternalGuess && ($candidate['status'] ?? null) === 'identified') {
            $candidate['status'] = 'probable';
        }

        return $candidate;
    }

    /**
     * CLA-390 — compare a photo of a physical frame (headframe structure, taken
     * before any luminaires/positions are recorded) against the internal
     * LuminaireFrameType catalog. Separate method from identifyLuminaires() on
     * purpose: frame types have no brand/subgroup concept (they're structural
     * shapes, not manufacturer products), so there's no out-of-catalog brand
     * guess here — the existing "create a custom frame type from this photo"
     * flow (LuminaireFrameType::created_by_user_id) is already the correct
     * fallback when nothing matches, no need to duplicate CLA-389's design.
     *
     * @param  string  $imageBase64  Raw base64 image data (no data: URI prefix).
     * @param  string  $imageMediaType  e.g. "image/jpeg", "image/png".
     * @param  array<int, array{id: int, name: string}>  $catalog
     * @return array{status: string, candidates: array<int, array{catalog_id: ?int, confidence: float, evidence: array<int, string>, status: string}>}
     */
    public function identifyFrameType(string $imageBase64, string $imageMediaType, array $catalog): array
    {
        $abstain = ['status' => 'unknown', 'candidates' => []];

        if (empty($this->apiKey)) {
            Log::error('Anthropic API key is missing.');

            return $abstain;
        }

        if (empty($catalog)) {
            Log::warning('ClaudeVisionService::identifyFrameType called with an empty frame type catalog.');

            return $abstain;
        }

        try {
            $client = new Client(apiKey: $this->apiKey);

            $message = $client->messages->create(
                maxTokens: 1024,
                model: $this->model,
                system: $this->frameTypeSystemPrompt(),
                outputConfig: [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $this->frameTypeResponseSchema(),
                    ],
                ],
                messages: [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'mediaType' => $imageMediaType,
                                    'data' => $imageBase64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $this->buildFrameTypePrompt($catalog),
                            ],
                        ],
                    ],
                ],
            );

            $textBlock = collect($message->content)->first(
                fn ($block) => ($block->type ?? null) === 'text'
            );

            $decoded = json_decode($textBlock->text ?? '{}', true);

            return is_array($decoded) && isset($decoded['status'], $decoded['candidates'])
                ? $decoded
                : $abstain;
        } catch (\Throwable $e) {
            Log::error('Claude Vision Exception (frame type): '.$e->getMessage());

            return $abstain;
        }
    }

    protected function frameTypeSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a technical assistant helping field technicians of a Belgian sports lighting contractor identify the TYPE of physical frame (headframe structure that luminaires get mounted on) shown in a photo, by comparing it against the contractor's internal catalog of known frame shapes.

CRITICAL RULE: Never infer a specific frame type unless clearly supported by the visible structural shape in the photo (arm/cross-arm layout, number of tiers, curvature, mast/lowering mechanism, cage vs. open frame). A less specific but correct identification is always preferred over a more specific but unsupported one. When there is not enough visual evidence to confidently match any catalog entry, return status "unknown" with an empty candidates array — this is a valid, expected, and preferred outcome over guessing. The photo may also show luminaires already mounted on the frame and field-context background — ignore those, judge only the frame structure itself.
PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     */
    protected function buildFrameTypePrompt(array $catalog): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Internal frame type catalog (JSON array; each item has "id", "name" — a structural shape, not a manufacturer product):
{$catalogJson}

Task: look at the attached photo of a physical frame (possibly with luminaires already mounted, and field-context background) and identify which catalog item, if any, matches its structural shape.

For each candidate return:
- "catalog_id": the matching catalog "id", or null if nothing matches well enough to name a specific entry
- "confidence": a number between 0 and 1
- "evidence": short list of concrete structural features supporting the match (arm/tier layout, curvature, mounting mechanism, overall silhouette)
- "status": "identified" (confident, specific match), "probable" (partial match, lower confidence), or "unknown" (not enough evidence)

If nothing in the catalog plausibly matches, return exactly one candidate with "catalog_id": null and "status": "unknown", and set the top-level "status" to "unknown" as well.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function frameTypeResponseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['status', 'candidates'],
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['identified', 'probable', 'unknown'],
                ],
                'candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['catalog_id', 'confidence', 'evidence', 'status'],
                        'properties' => [
                            'catalog_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'confidence' => ['type' => 'number'],
                            'evidence' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['identified', 'probable', 'unknown'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a technical assistant helping field technicians of a Belgian sports lighting contractor identify floodlights (luminaires) installed on a physical frame, by comparing a photo against the contractor's internal catalog of known luminaire types.

CRITICAL RULE: Never infer a specific manufacturer, product family, or model unless clearly supported by visible evidence in the photo. A less specific but correct identification is always preferred over a more specific but unsupported one. When there is not enough visual evidence to confidently match any catalog entry, return status "unknown" with an empty candidates array — this is a valid, expected, and preferred outcome over guessing.

If nothing in the internal catalog matches but the fixture visibly resembles a product from one of the manufacturer brands already present in that catalog, you may add ONE extra candidate with "catalog_id": null and your best-effort "suggested_brand"/"suggested_model" guess, drawn from your own general knowledge of that brand's commercial sports/area floodlight lines — never invent a brand that isn't already one of the catalog's brands. This guess is unverifiable against real hardware data, so its "status" can never be "identified", at most "probable". If you have no real basis for a brand/model guess, leave both null and keep "unknown" — still the preferred outcome over guessing.
PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     */
    protected function buildPrompt(array $catalog): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);
        $knownBrands = implode(', ', array_unique(array_filter(array_column($catalog, 'brand'))));

        return <<<PROMPT
Internal luminaire catalog (JSON array; each item has "id", "name", "product_family", "model_reference", "typical_application", "brand", "group_name"):
{$catalogJson}

Manufacturer brands known to this contractor (for the out-of-catalog suggestion described below — never suggest a brand outside this list): {$knownBrands}

Task: look at the attached photo of a luminaire frame and identify which catalog item(s), if any, plausibly match what is visible.

For each candidate return:
- "catalog_id": the matching catalog "id", or null if nothing matches well enough to name a specific entry
- "suggested_brand" / "suggested_model": only when "catalog_id" is null — your best-effort guess of the real-world brand/model this fixture belongs to, restricted to the known brands list above and grounded in your own knowledge of their product lines, or null/null if you have no real basis to guess
- "confidence": a number between 0 and 1
- "evidence": short list of concrete visual features supporting the match (housing shape, module arrangement, mounting/bracket, color, proportions, visible labels)
- "status": "identified" (confident, specific catalog match), "probable" (partial catalog match OR any out-of-catalog brand/model guess, lower confidence), or "unknown" (not enough evidence for either)

If nothing in the catalog plausibly matches and you have no out-of-catalog guess either, return exactly one candidate with "catalog_id": null, "suggested_brand": null, "suggested_model": null and "status": "unknown", and set the top-level "status" to "unknown" as well.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['status', 'candidates'],
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['identified', 'probable', 'unknown'],
                ],
                'candidates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['catalog_id', 'suggested_brand', 'suggested_model', 'confidence', 'evidence', 'status'],
                        'properties' => [
                            'catalog_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'suggested_brand' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'suggested_model' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'confidence' => ['type' => 'number'],
                            'evidence' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['identified', 'probable', 'unknown'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
