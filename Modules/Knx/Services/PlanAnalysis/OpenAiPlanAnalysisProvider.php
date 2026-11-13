<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Responses API adapter (`POST /v1/responses`), switched OFF by default.
 *
 * Follows the official documentation: the page goes as an `input_image` (base64 data
 * URL), the answer is constrained with Structured Outputs (`text.format` json_schema,
 * `strict: true`), and `store: false` keeps OpenAI from storing the response as
 * application state. Refusals, incomplete answers and HTTP errors become
 * PlanAnalysisFailed; nothing is retried beyond the configured budget.
 */
class OpenAiPlanAnalysisProvider implements PlanAnalysisProvider
{
    public const ID = 'openai';

    private const INSTRUCTIONS = <<<'TXT'
You analyse ONE page of an architectural or installation floor plan for an electrical contractor.
You get the rendered page and a summary of the PDF: stroke groups (id, style, counts) and texts (id, text, position).
Coordinates are page fractions: x from left (0) to right (1), y from top (0) to bottom (1).
Propose, never invent:
- groupRoles: for groups you can identify from the image, the role of ALL their strokes:
  wall, window, door, stair, lift, void (opening in the floor), hidden (installations, furniture,
  dimensions, annotations, frame) or unknown. Use only group ids from the summary.
- rooms: one entry per enclosed space you can see, with a point (x, y) well INSIDE it.
  A name is only allowed when a listed text names the space: copy that text exactly in `name`
  and give its id in `textId`. Without such text, `name` and `textId` are null.
- uncertain: points where you cannot tell (open spaces, unreadable areas, doubtful boundaries),
  each with a short reason.
Confidence is 0..1. Keep reasons short.
TXT;

    public function __construct(
        private readonly PlanAnalysisProviders $providers,
        private readonly PlanAnalysisBudget $budget,
        private readonly OpenAiCredentialSource $credentials,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function analyze(PlanAnalysisInput $input): PlanAnalysisResult
    {
        $token = $this->credentials->bearerToken();
        if (! $this->providers->isAvailable(self::ID) || $token === null) {
            throw new PlanAnalysisFailed('unavailable', 'The OpenAI plan-analysis provider is switched off.');
        }
        if (strlen($input->imagePng) > $this->budget->maxImageBytes()) {
            throw new PlanAnalysisFailed('image_too_large');
        }

        $this->budget->reserve(self::ID);

        $model = (string) config('knx.plan_analysis.providers.openai.model');
        $response = $this->send($token, $this->body($model, $input));

        return new PlanAnalysisResult(
            provider: self::ID,
            model: is_string($response['model'] ?? null) ? $response['model'] : $model,
            proposal: $this->proposal($response),
            usage: [
                'inputTokens' => isset($response['usage']['input_tokens']) ? (int) $response['usage']['input_tokens'] : null,
                'outputTokens' => isset($response['usage']['output_tokens']) ? (int) $response['usage']['output_tokens'] : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function body(string $model, PlanAnalysisInput $input): array
    {
        $summary = [
            'page' => $input->page,
            'pageSizePt' => [$input->pageWidthPt, $input->pageHeightPt],
            'groups' => $input->groups,
            'texts' => $input->texts,
        ];

        return [
            'model' => $model,
            'store' => false,
            'max_output_tokens' => $this->budget->maxOutputTokens(),
            'instructions' => self::INSTRUCTIONS,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                    [
                        'type' => 'input_image',
                        'image_url' => 'data:image/png;base64,'.base64_encode($input->imagePng),
                        'detail' => (string) config('knx.plan_analysis.providers.openai.image_detail', 'high'),
                    ],
                ],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => PlanAnalysisSchema::NAME,
                    'strict' => true,
                    'schema' => PlanAnalysisSchema::schema(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $token, array $body): array
    {
        $url = rtrim((string) config('knx.plan_analysis.providers.openai.base_url'), '/').'/responses';
        $attempts = 1 + $this->budget->maxRetries();

        for ($attempt = 1; ; $attempt++) {
            try {
                /** @var Response $response */
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout($this->budget->timeoutSeconds())
                    ->post($url, $body);
            } catch (ConnectionException $e) {
                if ($attempt < $attempts) {
                    continue;
                }
                throw new PlanAnalysisFailed('provider_error', 'OpenAI could not be reached.');
            }

            if ($response->successful()) {
                return (array) $response->json();
            }
            // Only transient failures are worth another (budgeted) attempt.
            if (($response->status() === 429 || $response->serverError()) && $attempt < $attempts) {
                continue;
            }

            throw new PlanAnalysisFailed('provider_error', "OpenAI answered HTTP {$response->status()}.");
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function proposal(array $response): array
    {
        if (($response['status'] ?? null) !== 'completed') {
            $why = $response['incomplete_details']['reason'] ?? ($response['status'] ?? 'unknown');
            throw new PlanAnalysisFailed('incomplete', "The answer is incomplete ({$why}).");
        }

        foreach ((array) ($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new PlanAnalysisFailed('refused', 'The model refused to analyse the plan.');
                }
                if (($part['type'] ?? null) === 'output_text') {
                    $decoded = json_decode((string) ($part['text'] ?? ''), true);
                    if (! is_array($decoded)) {
                        throw new PlanAnalysisFailed('invalid_output', 'The answer is not JSON.');
                    }

                    return $decoded;
                }
            }
        }

        throw new PlanAnalysisFailed('invalid_output', 'The answer has no output text.');
    }
}
