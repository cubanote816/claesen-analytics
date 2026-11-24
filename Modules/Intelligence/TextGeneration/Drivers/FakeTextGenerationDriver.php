<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration\Drivers;

use Modules\Intelligence\Contracts\TextGenerationProvider;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\DecodesJsonAnswer;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Modules\Intelligence\TextGeneration\TextGenerationResult;

/**
 * CLA-714: canned answers for tests and local work, never production
 * (TextGenerationManager refuses it there). Bound as a singleton so a test
 * can queue answers and inspect the requests it received.
 */
final class FakeTextGenerationDriver implements TextGenerationProvider
{
    use DecodesJsonAnswer;

    /** @var list<TextGenerationRequest> */
    public array $requests = [];

    /** @var list<string> */
    public array $models = [];

    /** @var list<string|array<mixed>|null> null = failure */
    private array $queue = [];

    private string $model = 'fake-model';

    public function usingModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function queueText(string $text): self
    {
        $this->queue[] = $text;

        return $this;
    }

    /** @param  array<mixed>  $json */
    public function queueJson(array $json): self
    {
        $this->queue[] = $json;

        return $this;
    }

    public function queueFailure(): self
    {
        $this->queue[] = null;

        return $this;
    }

    public function generate(TextGenerationRequest $request): TextGenerationResult
    {
        $this->requests[] = $request;
        $this->models[] = $this->model;

        $answer = $this->queue === [] ? 'Fake answer.' : array_shift($this->queue);

        if ($answer === null) {
            throw new TextGenerationException('The fake provider was told to fail.');
        }

        $text = is_string($answer) ? $answer : json_encode($answer);

        // Same contract as the real drivers: a schema request needs a matching JSON answer.
        return new TextGenerationResult(
            text: $text,
            json: $request->jsonSchema !== null
                ? $this->decodeIfSchema($request, $text, 'fake')
                : (is_array($answer) ? $answer : null),
            provider: 'fake',
            model: $this->model,
            inputTokens: null,
            outputTokens: null,
            durationMs: 0,
        );
    }
}
