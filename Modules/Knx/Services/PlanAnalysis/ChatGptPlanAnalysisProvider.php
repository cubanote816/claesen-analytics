<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Knx\Models\KnxChatGptConnection;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\ChatGpt\ChatGptApiError;
use Modules\Knx\Services\ChatGpt\ChatGptConnections;
use Modules\Knx\Services\ChatGpt\ChatGptFailed;
use Modules\Knx\Services\ChatGpt\ChatGptSettings;

/**
 * Plan analysis on the EMPLOYEE'S OWN ChatGPT plan (`POST {resource}/responses`, "Sign in
 * with ChatGPT"). Billing: subscription. It is built per employee (the credential is that
 * employee's access token) and never falls back to the API key adapter.
 *
 * The documented flow differs from the API adapter: `store: false` and `stream: true` are
 * mandatory, the limit/sampling/tool parameters are omitted, and success is ONLY a
 * `response.completed` event. Exactly one attempt is made, no retries: a failure is
 * reported with the provider's status, code, param and request id.
 */
class ChatGptPlanAnalysisProvider implements PlanAnalysisProvider
{
    public const ID = 'chatgpt';

    private ?KnxEmployee $employee = null;

    private ?string $model = null;

    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptConnections $connections,
        private readonly PlanAnalysisBudget $budget,
    ) {}

    /** A copy of this provider acting for one employee, optionally with a model of their choice. */
    public function forEmployee(KnxEmployee $employee, ?string $model = null): self
    {
        $copy = clone $this;
        $copy->employee = $employee;
        $copy->model = $model;

        return $copy;
    }

    public function id(): string
    {
        return self::ID;
    }

    /**
     * Configured, THIS employee connected with plan permission, and a model chosen.
     */
    public function availableFor(?KnxEmployee $employee): bool
    {
        if ($employee === null || ! $this->settings->configured() || ! filled(config('knx.plan_analysis.providers.chatgpt.model'))) {
            return false;
        }
        $connection = $this->connections->connectionFor($employee);

        return $connection !== null
            && $connection->status === KnxChatGptConnection::STATUS_CONNECTED
            && $connection->plan_permission;
    }

    public function analyze(PlanAnalysisInput $input, callable $maySend): PlanAnalysisResult
    {
        $model = $this->model ?? (string) config('knx.plan_analysis.providers.chatgpt.model');
        $connection = $this->employee === null ? null : $this->connections->connectionFor($this->employee);

        if ($model === '' || ! $this->settings->configured() || $connection === null || $connection->status !== KnxChatGptConnection::STATUS_CONNECTED) {
            throw new PlanAnalysisFailed('unavailable', 'The ChatGPT plan is not available for this employee.');
        }
        if (strlen($input->imagePng) > $this->budget->maxImageBytes()
            || ($input->referencePng !== null && strlen($input->referencePng) > $this->budget->maxImageBytes())) {
            throw new PlanAnalysisFailed('image_too_large');
        }

        try {
            $usable = $this->connections->usableToken($connection);
        } catch (ChatGptFailed $failure) {
            throw new PlanAnalysisFailed(
                $failure->reason === 'needs_sign_in' ? 'needs_sign_in' : 'provider_error',
                $failure->getMessage(),
                providerCode: $failure->reason,
            );
        }
        if (! $usable['connection']->plan_permission) {
            throw new PlanAnalysisFailed('unavailable', 'The ChatGPT plan is not available for this employee.');
        }

        if (! $maySend()) {
            throw new PlanAnalysisFailed('not_authorized', 'This document is no longer authorized for this provider.');
        }
        $this->budget->reserve(self::ID);

        $text = $this->stream($usable['token'], $this->body($model, $input));
        $decoded = json_decode($text['text'], true);
        if (! is_array($decoded)) {
            throw new PlanAnalysisFailed('invalid_output', 'The answer is not JSON.', requestId: $text['requestId']);
        }

        return new PlanAnalysisResult(
            provider: self::ID,
            model: $text['model'] ?? $model,
            proposal: $decoded,
            usage: [
                'inputTokens' => isset($text['usage']['input_tokens']) ? (int) $text['usage']['input_tokens'] : null,
                'outputTokens' => isset($text['usage']['output_tokens']) ? (int) $text['usage']['output_tokens'] : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function body(string $model, PlanAnalysisInput $input): array
    {
        return [
            'model' => $model,
            'store' => false,
            'stream' => true,
            'instructions' => OpenAiPlanAnalysisProvider::INSTRUCTIONS,
            'input' => [[
                'role' => 'user',
                'content' => $input->contentParts(null),
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
     * The single attempt. Success is only a `response.completed` event.
     *
     * @param  array<string, mixed>  $body
     * @return array{text: string, model: ?string, usage: array<string, mixed>, requestId: ?string}
     */
    private function stream(string $token, array $body): array
    {
        $url = rtrim($this->settings->resource(), '/').'/responses';

        try {
            $response = Http::withToken($token)
                ->timeout($this->budget->timeoutSeconds())
                ->post($url, $body);
        } catch (ConnectionException) {
            throw new PlanAnalysisFailed('provider_error', 'ChatGPT could not be reached.');
        }

        $requestId = $response->header('x-request-id') ?: null;

        if (! $response->successful()) {
            $error = ChatGptApiError::fromResponse($response);

            throw new PlanAnalysisFailed($error->reason(), $error->describe(), $error->status, $error->code, $error->param, $error->requestId);
        }

        $deltas = '';
        foreach ($this->events($response->body()) as $event) {
            switch ($event['type'] ?? null) {
                case 'response.output_text.delta':
                    $deltas .= is_string($event['delta'] ?? null) ? $event['delta'] : '';
                    break;
                case 'response.completed':
                    return $this->completed((array) ($event['response'] ?? []), $deltas, $requestId);
                case 'response.failed':
                    $error = ChatGptApiError::fromEvent($event['response']['error'] ?? $event['error'] ?? null, $requestId);

                    throw new PlanAnalysisFailed($error->reason(), $error->describe(), $response->status(), $error->code, $error->param, $requestId);
                case 'error':
                    $error = ChatGptApiError::fromEvent($event['error'] ?? $event, $requestId);

                    throw new PlanAnalysisFailed($error->reason(), $error->describe(), $response->status(), $error->code, $error->param, $requestId);
                case 'response.incomplete':
                    $why = $event['response']['incomplete_details']['reason'] ?? 'unknown';

                    throw new PlanAnalysisFailed('incomplete', "The answer is incomplete ({$why}).", $response->status(), requestId: $requestId);
            }
        }

        throw new PlanAnalysisFailed('incomplete', 'The stream ended without a completed response.', $response->status(), requestId: $requestId);
    }

    /**
     * @param  array<string, mixed>  $completed
     * @return array{text: string, model: ?string, usage: array<string, mixed>, requestId: ?string}
     */
    private function completed(array $completed, string $deltas, ?string $requestId): array
    {
        $text = null;
        foreach ((array) ($completed['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new PlanAnalysisFailed('refused', 'The model refused to analyse the plan.', requestId: $requestId);
                }
                if (($part['type'] ?? null) === 'output_text' && $text === null) {
                    $text = (string) ($part['text'] ?? '');
                }
            }
        }
        $text ??= $deltas;

        if ($text === '') {
            throw new PlanAnalysisFailed('invalid_output', 'The answer has no output text.', requestId: $requestId);
        }

        return [
            'text' => $text,
            'model' => is_string($completed['model'] ?? null) ? $completed['model'] : null,
            'usage' => (array) ($completed['usage'] ?? []),
            'requestId' => $requestId,
        ];
    }

    /**
     * The server-sent events of the body, in order; undecodable ones are skipped.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function events(string $body): \Generator
    {
        foreach (preg_split('/\r?\n\r?\n/', $body) ?: [] as $block) {
            $data = [];
            $name = null;
            foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5));
                } elseif (str_starts_with($line, 'event:')) {
                    $name = trim(substr($line, 6));
                }
            }
            $decoded = json_decode(implode("\n", $data), true);
            if (! is_array($decoded)) {
                continue;
            }
            $decoded['type'] ??= $name;

            yield $decoded;
        }
    }
}
