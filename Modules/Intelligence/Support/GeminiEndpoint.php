<?php

declare(strict_types=1);

namespace Modules\Intelligence\Support;

/**
 * CLA-729 — single source of truth for the Vertex AI Gemini endpoint.
 * Reads only config('services.gemini.*') (never env(), see CLA-532).
 */
final class GeminiEndpoint
{
    public static function generateContentUrl(): string
    {
        $override = config('services.gemini.url');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $project = (string) config('services.gemini.project');
        $location = (string) (config('services.gemini.location') ?: 'global');
        $model = (string) config('services.gemini.model');
        $host = $location === 'global' ? 'aiplatform.googleapis.com' : $location.'-aiplatform.googleapis.com';

        return "https://{$host}/v1/projects/{$project}/locations/{$location}/publishers/google/models/{$model}:generateContent";
    }

    /** Model id: parsed from the override URL when set (empty if unparseable), else config. */
    public static function model(): string
    {
        $override = config('services.gemini.url');

        if (is_string($override) && $override !== '') {
            return preg_match('#/models/([^/:?]+):#', $override, $m) === 1 ? $m[1] : '';
        }

        return (string) config('services.gemini.model');
    }

    /** thinkingConfig is only accepted by Gemini 3+; older models answer HTTP 400. */
    public static function thinkingConfig(): array
    {
        if (preg_match('/^gemini-(\d+)/', self::model(), $m) === 1 && (int) $m[1] >= 3) {
            return ['thinkingLevel' => 'MINIMAL'];
        }

        return [];
    }

    public static function timeout(): int
    {
        return max(1, (int) config('services.gemini.timeout', 30));
    }
}
