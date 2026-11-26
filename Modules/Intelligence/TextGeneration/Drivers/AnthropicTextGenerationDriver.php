<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration\Drivers;

use Modules\Intelligence\Contracts\TextGenerationProvider;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\AnthropicMessageClient;
use Modules\Intelligence\TextGeneration\DecodesJsonAnswer;
use Modules\Intelligence\TextGeneration\ProviderSchema;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Modules\Intelligence\TextGeneration\TextGenerationResult;

/** CLA-714: Anthropic Claude through the official SDK (anthropic-ai/sdk). */
final class AnthropicTextGenerationDriver implements TextGenerationProvider
{
    use DecodesJsonAnswer;

    private const DEFAULT_MAX_TOKENS = 1024;

    public function __construct(
        private readonly AnthropicMessageClient $client,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {}

    public function generate(TextGenerationRequest $request): TextGenerationResult
    {
        $apiKey = config('services.anthropic.key');

        if (blank($apiKey)) {
            throw new TextGenerationException('The Anthropic API key is not configured.');
        }

        $params = [
            'maxTokens' => $request->maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
            'requestOptions' => ['timeout' => (float) $this->timeoutSeconds],
        ];

        if ($request->system !== null) {
            $params['system'] = $request->system;
        }

        if ($request->temperature !== null) {
            $params['temperature'] = $request->temperature;
        }

        if ($request->jsonSchema !== null) {
            ProviderSchema::assertSupported($request->jsonSchema);
            $params['outputConfig'] = ['format' => ['type' => 'json_schema', 'schema' => ProviderSchema::forAnthropic($request->jsonSchema)]];
        }

        $startedAt = hrtime(true);

        try {
            $message = $this->client->createMessage($apiKey, $params);
        } catch (\Throwable $e) {
            // The SDK exception is not chained: its message and request can carry the
            // upstream body and the API key, and log formatters print previous exceptions.
            // APIStatusException exposes the HTTP status as the public \$status property.
            $status = isset($e->status) && is_int($e->status) ? ' HTTP '.$e->status : '';

            throw new TextGenerationException('The Anthropic request failed ('.class_basename($e).$status.').');
        }

        $text = collect($message->content ?? [])->first(fn ($block) => ($block->type ?? null) === 'text')?->text;

        if (! is_string($text)) {
            throw new TextGenerationException('The Anthropic answer had no text.');
        }

        return new TextGenerationResult(
            text: $text,
            json: $this->decodeIfSchema($request, $text, 'anthropic'),
            provider: 'anthropic',
            model: $this->model,
            inputTokens: $message->usage->inputTokens ?? null,
            outputTokens: $message->usage->outputTokens ?? null,
            durationMs: self::elapsedMs($startedAt),
        );
    }
}
