<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;

/**
 * CLA-714: shared JSON handling for drivers. An answer to a request with a
 * JSON schema must decode and match it. Supported keywords: type, properties,
 * required, items, enum (plus description/title); any other keyword is
 * rejected rather than silently not enforced.
 */
trait DecodesJsonAnswer
{
    private const SCHEMA_KEYWORDS = ['type', 'properties', 'required', 'items', 'enum', 'description', 'title'];

    /** @return array<mixed>|null */
    private function decodeIfSchema(TextGenerationRequest $request, string $text, string $provider): ?array
    {
        if ($request->jsonSchema === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! self::matchesSchema($decoded, $request->jsonSchema)) {
            throw new TextGenerationException("The {$provider} answer did not match the requested JSON schema.");
        }

        return $decoded;
    }

    private static function matchesSchema(mixed $value, array $schema): bool
    {
        if (array_diff(array_keys($schema), self::SCHEMA_KEYWORDS) !== []) {
            throw new TextGenerationException('The JSON schema uses a keyword that is not supported.');
        }

        $object = is_array($value) && ($value === [] || ! array_is_list($value));
        $checks = [
            ! isset($schema['enum']) || in_array($value, $schema['enum'], true),
            ! isset($schema['type']) || self::matchesType($value, (array) $schema['type']),
            ! $object || array_diff($schema['required'] ?? [], array_keys($value)) === [],
        ];

        foreach ($object ? ($schema['properties'] ?? []) : [] as $key => $sub) {
            $checks[] = ! array_key_exists($key, $value) || self::matchesSchema($value[$key], $sub);
        }

        foreach (is_array($value) && array_is_list($value) && isset($schema['items']) ? $value : [] as $item) {
            $checks[] = self::matchesSchema($item, $schema['items']);
        }

        return ! in_array(false, $checks, true);
    }

    private static function matchesType(mixed $value, array $types): bool
    {
        return collect($types)->contains(fn (string $type): bool => match ($type) {
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => throw new TextGenerationException("The JSON schema type \"{$type}\" is not supported."),
        });
    }

    private static function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
