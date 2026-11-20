<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

/** CLA-714: the AI answer plus which provider and model produced it. */
final readonly class TextGenerationResult
{
    /** @param  array<mixed>|null  $json  decoded answer when the request had a JSON schema */
    public function __construct(
        public ?string $text,
        public ?array $json,
        public string $provider,
        public string $model,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public int $durationMs,
    ) {}
}
