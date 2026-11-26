<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;

/**
 * CLA-714: shared JSON handling for drivers. An answer to a request with a
 * JSON schema must be a JSON object or array that matches the schema.
 *
 * Supported subset (documented in docs/ai/module-contracts.md): type (object,
 * array, string, integer, number, boolean, null, or a list of them),
 * properties, required, items, enum, true/false schemas, and the description
 * and title annotations. Any other keyword or type is refused, never silently
 * ignored. Objects are decoded as objects, so {} and [] stay distinct.
 */
trait DecodesJsonAnswer
{
    private const SCHEMA_KEYWORDS = ['type', 'properties', 'required', 'items', 'enum', 'description', 'title'];

    private const SCHEMA_TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    /** @return array<mixed>|null */
    private function decodeIfSchema(TextGenerationRequest $request, string $text, string $provider): ?array
    {
        if ($request->jsonSchema === null) {
            return null;
        }

        ProviderSchema::assertSupported($request->jsonSchema);
        $value = json_decode($text);

        if (! ($value instanceof \stdClass || is_array($value)) || ! self::matchesSchema($value, $request->jsonSchema)) {
            throw new TextGenerationException("The {$provider} answer did not match the requested JSON schema.");
        }

        return json_decode($text, true);
    }

    /** @param  array<string, mixed>|bool  $schema */
    private static function matchesSchema(mixed $value, array|bool $schema): bool
    {
        if (is_bool($schema)) {
            return $schema;
        }

        $unknown = array_diff(array_keys($schema), self::SCHEMA_KEYWORDS);

        if ($unknown !== []) {
            throw new TextGenerationException('The JSON schema uses an unsupported keyword: '.implode(', ', $unknown).'.');
        }

        if (isset($schema['type']) && ! self::matchesType($value, (array) $schema['type'])) {
            return false;
        }

        if (array_key_exists('enum', $schema)) {
            $allowed = array_map(fn (mixed $choice): string => self::canonical(json_decode(json_encode($choice))), $schema['enum']);

            if (! in_array(self::canonical($value), $allowed, true)) {
                return false;
            }
        }

        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);

            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists((string) $key, $members)) {
                    return false;
                }
            }

            foreach ($schema['properties'] ?? [] as $key => $memberSchema) {
                if (array_key_exists((string) $key, $members) && ! self::matchesSchema($members[$key], $memberSchema)) {
                    return false;
                }
            }
        }

        if (is_array($value) && array_key_exists('items', $schema)) {
            foreach ($value as $item) {
                if (! self::matchesSchema($item, $schema['items'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param  list<string>  $types */
    private static function matchesType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            if (! in_array($type, self::SCHEMA_TYPES, true)) {
                throw new TextGenerationException("The JSON schema uses an unsupported type: {$type}.");
            }
        }

        return collect($types)->contains(fn (string $type): bool => match ($type) {
            'object' => $value instanceof \stdClass,
            'array' => is_array($value),
            'string' => is_string($value),
            // JSON Schema treats 7.0 as an integer: a number without a fractional part.
            'integer' => is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
        });
    }

    /** JSON text with object members sorted, so enum comparison ignores member order. */
    private static function canonical(mixed $value): string
    {
        return (string) json_encode(self::sortedMembers($value));
    }

    private static function sortedMembers(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $members = array_map(fn (mixed $member): mixed => self::sortedMembers($member), get_object_vars($value));
            ksort($members);

            return (object) $members;
        }

        return is_array($value) ? array_map(fn (mixed $item): mixed => self::sortedMembers($item), $value) : $value;
    }

    private static function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
