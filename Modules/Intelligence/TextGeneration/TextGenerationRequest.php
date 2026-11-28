<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

/**
 * CLA-714: what to ask the AI. The task names the use case (for example
 * "lead_summary") and selects the provider and model through config.
 * Send minimal data: no e-mail addresses or phone numbers.
 */
final readonly class TextGenerationRequest
{
    /** @param  array<string, mixed>|null  $jsonSchema  when set, the answer must be JSON matching it */
    public function __construct(
        public string $task,
        public string $prompt,
        public ?string $system = null,
        public ?array $jsonSchema = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
    ) {}
}
