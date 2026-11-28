<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration\Drivers;

use Modules\Intelligence\Contracts\TextGenerationProvider;
use Modules\Intelligence\Exceptions\GeminiProviderException;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\TextGeneration\DecodesJsonAnswer;
use Modules\Intelligence\TextGeneration\ProviderSchema;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Modules\Intelligence\TextGeneration\TextGenerationResult;

/** CLA-714: Gemini through GeminiService (module rule: never call Gemini directly). */
final class GeminiTextGenerationDriver implements TextGenerationProvider
{
    use DecodesJsonAnswer;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {}

    public function generate(TextGenerationRequest $request): TextGenerationResult
    {
        if ($request->jsonSchema !== null) {
            ProviderSchema::assertSupported($request->jsonSchema);
        }

        $startedAt = hrtime(true);

        try {
            $answer = $this->gemini->generateContent(
                model: $this->model,
                prompt: $request->prompt,
                system: $request->system,
                responseSchema: $request->jsonSchema === null ? null : ProviderSchema::forGemini($request->jsonSchema),
                maxTokens: $request->maxTokens,
                temperature: $request->temperature,
                timeoutSeconds: $this->timeoutSeconds,
            );
        } catch (GeminiProviderException $e) {
            throw new TextGenerationException($e->getMessage());
        }

        return new TextGenerationResult(
            text: $answer['text'],
            json: $this->decodeIfSchema($request, $answer['text'], 'gemini'),
            provider: 'gemini',
            model: $this->model,
            inputTokens: $answer['input_tokens'],
            outputTokens: $answer['output_tokens'],
            durationMs: self::elapsedMs($startedAt),
        );
    }
}
