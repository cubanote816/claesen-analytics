<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;

/**
 * CLA-714: the request schema before it leaves the app. assertSupported()
 * checks every node of the schema, whatever the answer will contain, so an
 * unsupported keyword is always refused before any provider call. forGemini()
 * and forAnthropic() translate the shared subset into what each provider
 * accepts; the wire schema may be looser, the local check stays exact.
 */
final class ProviderSchema
{
    public const KEYWORDS = ['type', 'properties', 'required', 'items', 'enum', 'description', 'title'];

    public const TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    /** @param  array<string, mixed>|bool  $schema */
    public static function assertSupported(array|bool $schema): void
    {
        if (is_bool($schema)) {
            return;
        }

        $unknown = array_diff(array_keys($schema), self::KEYWORDS);

        if ($unknown !== []) {
            throw new TextGenerationException('The JSON schema uses an unsupported keyword: '.implode(', ', $unknown).'.');
        }

        foreach ((array) ($schema['type'] ?? []) as $type) {
            if (! in_array($type, self::TYPES, true)) {
                throw new TextGenerationException("The JSON schema uses an unsupported type: {$type}.");
            }
        }

        foreach ([...array_values($schema['properties'] ?? []), ...(array_key_exists('items', $schema) ? [$schema['items']] : [])] as $child) {
            self::assertSupported($child);
        }
    }

    /**
     * Vertex responseSchema (OpenAPI subset): one upper-case type, nullable for
     * [x, null], string-only enums, no boolean subschemas.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function forGemini(array $schema): array
    {
        $types = array_values(array_diff((array) ($schema['type'] ?? []), ['null']));
        $wire = array_intersect_key($schema, array_flip(['description', 'title', 'required']));

        if (count($types) === 1) {
            $wire['type'] = strtoupper($types[0]);
        }

        if (in_array('null', (array) ($schema['type'] ?? []), true)) {
            $wire['nullable'] = true;
        }

        if (isset($schema['enum']) && array_filter($schema['enum'], fn ($choice) => ! is_string($choice)) === []) {
            $wire['enum'] = $schema['enum'];
        }

        // An empty subschema must reach Vertex as {} (a schema object), never [] (a list).
        foreach ($schema['properties'] ?? [] as $key => $child) {
            $wire['properties'][$key] = (is_array($child) ? self::forGemini($child) : []) ?: (object) [];
        }

        if (is_array($schema['items'] ?? null)) {
            $wire['items'] = self::forGemini($schema['items']) ?: (object) [];
        }

        // Always a JSON object: property names such as "0" would otherwise become a JSON list.
        if (isset($wire['properties'])) {
            $wire['properties'] = (object) $wire['properties'];
        }

        return $wire;
    }

    /**
     * Anthropic structured outputs: objects need additionalProperties false;
     * boolean subschemas become {} (true) or are left to the local check.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function forAnthropic(array $schema): array
    {
        $wire = $schema;
        $properties = [];

        foreach ($schema['properties'] ?? [] as $key => $child) {
            $properties[$key] = is_array($child) ? self::forAnthropic($child) : (object) [];
        }

        if (in_array('object', (array) ($schema['type'] ?? []), true) || isset($schema['properties']) || isset($schema['required'])) {
            // additionalProperties false must not forbid a required field: declare it as "any value".
            foreach ($schema['required'] ?? [] as $key) {
                $properties[$key] ??= (object) [];
            }

            $wire['additionalProperties'] = false;
        }

        // Always a JSON object: property names such as "0" would otherwise become a JSON list.
        if ($properties !== []) {
            $wire['properties'] = (object) $properties;
        }

        if (array_key_exists('items', $schema)) {
            $wire['items'] = is_array($schema['items']) ? self::forAnthropic($schema['items']) : (object) [];
        }

        return $wire;
    }
}
