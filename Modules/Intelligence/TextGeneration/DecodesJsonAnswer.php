<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;

/** CLA-714: shared JSON handling for drivers. */
trait DecodesJsonAnswer
{
    /** @return array<mixed>|null */
    private function decodeIfSchema(TextGenerationRequest $request, string $text, string $provider): ?array
    {
        if ($request->jsonSchema === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new TextGenerationException("The {$provider} answer was not valid JSON.");
        }

        return $decoded;
    }

    private static function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
