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
     * @param  string  $imageBase64  Raw base64 image data (no data: URI prefix).
     * @param  string  $imageMediaType  e.g. "image/jpeg", "image/png".
     * @param  array<int, array{id: int, name: string, product_family: ?string, model_reference: ?string, typical_application: ?string, brand: ?string, group_name: ?string}>  $catalog
     * @return array{status: string, candidates: array<int, array{catalog_id: ?int, confidence: float, evidence: array<int, string>, status: string}>}
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

            return is_array($decoded) && isset($decoded['status'], $decoded['candidates'])
                ? $decoded
                : $abstain;
        } catch (\Throwable $e) {
            Log::error('Claude Vision Exception: '.$e->getMessage());

            return $abstain;
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a technical assistant helping field technicians of a Belgian sports lighting contractor identify floodlights (luminaires) installed on a physical frame, by comparing a photo against the contractor's internal catalog of known luminaire types.

CRITICAL RULE: Never infer a specific manufacturer, product family, or model unless clearly supported by visible evidence in the photo. A less specific but correct identification is always preferred over a more specific but unsupported one. When there is not enough visual evidence to confidently match any catalog entry, return status "unknown" with an empty candidates array — this is a valid, expected, and preferred outcome over guessing.
PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     */
    protected function buildPrompt(array $catalog): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Internal luminaire catalog (JSON array; each item has "id", "name", "product_family", "model_reference", "typical_application", "brand", "group_name"):
{$catalogJson}

Task: look at the attached photo of a luminaire frame and identify which catalog item(s), if any, plausibly match what is visible.

For each candidate return:
- "catalog_id": the matching catalog "id", or null if nothing matches well enough to name a specific entry
- "confidence": a number between 0 and 1
- "evidence": short list of concrete visual features supporting the match (housing shape, module arrangement, mounting/bracket, color, proportions, visible labels)
- "status": "identified" (confident, specific match), "probable" (partial match, lower confidence), or "unknown" (not enough evidence)

If nothing in the catalog plausibly matches, return exactly one candidate with "catalog_id": null and "status": "unknown", and set the top-level "status" to "unknown" as well.
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
}
