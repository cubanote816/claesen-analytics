<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /** CLA-611 (gap G7): hard cap on source strings per provider call. */
    public const MAX_BATCH_SIZE = 20;

    protected string $apiUrl;

    public function __construct(protected GoogleServiceAccountAuthService $auth)
    {
        $this->apiUrl = config('services.gemini.url') ?: 'https://us-central1-aiplatform.googleapis.com/v1/projects/gen-lang-client-0849598291/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent';
    }

    /**
     * Generic method to call Gemini API with JSON output.
     */
    public function generateStructuredResponse(string $prompt): array
    {
        $token = $this->auth->getAccessToken();

        if (empty($token)) {
            Log::error('Gemini: could not obtain a service account access token.');

            return [];
        }

        try {
            $response = Http::withToken($token)->post($this->apiUrl, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.2,
                ],
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error: '.$response->body());

                return [];
            }

            $data = $response->json();
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            return json_decode($content, true) ?: [];
        } catch (\Exception $e) {
            Log::error('Gemini Exception: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Translate multi-language content and detect source language.
     *
     * CLA-611 (gaps G4/G6): optional translation context (page/section/field
     * kind) and a site glossary are injected into the prompt, and the result
     * is cached by hash(source + locales + context + glossary_version) so a
     * re-save of unchanged content costs no provider call. The three extra
     * parameters are OPTIONAL — the historical signature and behaviour are
     * unchanged for existing callers.
     *
     * @param  string  $text  The text to translate.
     * @param  array  $targetLocales  List of locales to translate to (e.g. ['nl', 'en']).
     * @param  string|null  $context  Optional translation context for the prompt.
     * @param  array<string, array{translations: array<string,string>, do_not_translate: bool}>  $glossary  Site glossary terms.
     * @param  int  $glossaryVersion  Derived glossary version (cache key part).
     * @return array {'detected_locale': string, 'translations': array<string, string>}
     */
    public function translateAndDetect(string $text, array $targetLocales, ?string $context = null, array $glossary = [], int $glossaryVersion = 0): array
    {
        if (empty(trim($text))) {
            return [
                'detected_locale' => app()->getLocale(),
                'translations' => array_combine($targetLocales, array_fill(0, count($targetLocales), '')),
            ];
        }

        $cacheKey = $this->translationCacheKey($text, $targetLocales, $context, $glossary, $glossaryVersion);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            Log::info('Gemini translation served from cache.', ['cache' => true, 'locales' => $targetLocales]);

            return $cached;
        }

        $result = $this->callTranslateProvider($text, $targetLocales, $context, $glossary);

        Cache::put($cacheKey, $result, now()->addDays(7));

        return $result;
    }

    /**
     * G6: cache key = hash(source + locales + context + glossary_version) —
     * a glossary edit changes the version and therefore invalidates every
     * cached translation derived under the old glossary.
     */
    private function translationCacheKey(string $text, array $targetLocales, ?string $context, array $glossary, int $glossaryVersion): string
    {
        return 'ai-trans:'.hash('sha256', serialize([
            $text, $targetLocales, $context, $glossary, $glossaryVersion,
        ]));
    }

    /**
     * CLA-611 (gap G7): batch translation of several source strings to ONE
     * target locale in a SINGLE provider call — max MAX_BATCH_SIZE strings
     * per call (the bulk command groups by locale and chunks). Per-string
     * cache hits are removed from the call first, so repeated content costs
     * nothing (gap G6); results are cached after the call. Resumable by
     * design: callers persist per-item state and only pending items come
     * back in.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<string, string>> translations keyed by input index => locale
     */
    public function translateBatch(array $texts, string $targetLocale, ?string $context = null, array $glossary = [], int $glossaryVersion = 0): array
    {
        if (count($texts) > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException('translateBatch accepts at most '.self::MAX_BATCH_SIZE.' source strings per call.');
        }

        $results = [];
        $pending = [];

        foreach ($texts as $index => $text) {
            $cacheKey = $this->translationCacheKey($text, [$targetLocale], $context, $glossary, $glossaryVersion);
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && filled($cached['translations'][$targetLocale] ?? null)) {
                $results[$index] = $cached['translations'];

                continue;
            }

            $pending[$index] = [$text, $cacheKey];
        }

        if ($pending === []) {
            return $results;
        }

        $numbered = collect($pending)
            ->map(fn (array $item, int $index) => ($index + 1).'. '.str_replace(["\r", "\n"], ' ', $item[0]))
            ->values()
            ->implode("\n");

        $glossaryBlock = $this->glossaryBlock($glossary);
        $contextBlock = filled($context) ? "\nContext (audience/section/field kind — respect it in tone and terminology): {$context}" : '';

        $prompt = <<<PROMPT
Translate each numbered source string to {$targetLocale}. Keep the numbering in the JSON keys.{$contextBlock}
{$glossaryBlock}
Source strings:
{$numbered}

Return JSON: {"translations": {"1": "...", "2": "..."}}
PROMPT;

        $result = $this->generateStructuredResponse($prompt);
        $translations = is_array($result['translations'] ?? null) ? $result['translations'] : [];

        Log::info('Gemini batch translation call.', ['provider_call' => true, 'source_strings' => count($pending), 'locale' => $targetLocale]);

        foreach ($pending as $index => [$text, $cacheKey]) {
            $numberKey = (string) array_search($index, array_keys($pending), true) + 1;
            $translated = $translations[$numberKey] ?? $translations[(string) $index] ?? null;

            $payload = [
                'detected_locale' => app()->getLocale(),
                'translations' => [$targetLocale => is_string($translated) ? $translated : ''],
            ];

            Cache::put($cacheKey, $payload, now()->addDays(7));
            $results[$index] = $payload['translations'];
        }

        return $results;
    }

    private function callTranslateProvider(string $text, array $targetLocales, ?string $context, array $glossary): array
    {
        $localesList = implode(', ', $targetLocales);
        $glossaryBlock = $this->glossaryBlock($glossary);
        $contextBlock = filled($context) ? "\nContext (audience/section/field kind — respect it in tone and terminology): {$context}" : '';

        $prompt = <<<PROMPT
Task: Detect source language of "{$text}" and translate to: {$localesList}.{$contextBlock}
{$glossaryBlock}
Return JSON: {"detected_locale": "ISO", "translations": {"code": "text"}}
PROMPT;

        $result = $this->generateStructuredResponse($prompt);

        Log::info('Gemini translation call.', ['provider_call' => true, 'locales' => $targetLocales]);

        return [
            'detected_locale' => $result['detected_locale'] ?? 'nl',
            'translations' => $result['translations'] ?? [],
        ];
    }

    /**
     * Render the glossary into prompt instructions (gap G4): pinned
     * translations per locale and invariant brand terms.
     *
     * @param  array<string, array{translations: array<string,string>, do_not_translate: bool}>  $glossary
     */
    private function glossaryBlock(array $glossary): string
    {
        if ($glossary === []) {
            return '';
        }

        $lines = ['Glossary (MUST be respected):'];

        foreach ($glossary as $term => $entry) {
            if (! empty($entry['do_not_translate'])) {
                $lines[] = "- \"{$term}\" is a brand name: keep it unchanged in every locale.";

                continue;
            }

            $pinned = collect($entry['translations'] ?? [])
                ->map(fn ($translation, $locale) => "{$locale}: \"{$translation}\"")
                ->implode(', ');

            if (filled($pinned)) {
                $lines[] = "- \"{$term}\" translates exactly as: {$pinned}.";
            }
        }

        return implode("\n", $lines);
    }

    protected function fallbackResponse(string $text, array $targetLocales): array
    {
        return [
            'detected_locale' => app()->getLocale(),
            'translations' => array_combine($targetLocales, array_fill(0, count($targetLocales), $text)),
        ];
    }

    /**
     * Analyze technician behavior and return archetypes.
     */
    public function analyzeEmployee(array $payload, string $locale = 'nl'): array
    {
        $prompt = 'Analyze this employee data: '.json_encode($payload).". Return JSON with archetype_label, archetype_icon, manager_insight, analysis in {$locale}.";

        return $this->generateStructuredResponse($prompt);
    }

    /**
     * Analyze project financial data.
     */
    public function analyzeProject(array $payload, $context): array
    {
        $locale = $context->locale ?? 'nl';
        $prompt = 'Analyze this project data: '.json_encode($payload).". Return JSON: {efficiency_score: int, ai_summary: string, critical_leak: string, golden_rule: string} in {$locale}.";

        $result = $this->generateStructuredResponse($prompt);
        $result['full_dna'] = $payload;

        return $result;
    }

    /**
     * Generate caption and alt text for a gallery image in all target locales.
     *
     * @param  array  $projectContext  title, category, client, location, year, description
     * @param  string|null  $userCaption  manual caption source — translated if set; generated if null
     * @param  string|null  $userAlt  manual alt source — translated if set; generated if null
     * @param  array  $locales  target locales, e.g. ['nl','en','fr','de']
     * @return array{caption: array<string,string>, alt: array<string,string>}
     */
    public function generateMediaMetadata(
        array $projectContext,
        ?string $userCaption,
        ?string $userAlt,
        array $locales
    ): array {
        $empty = array_fill_keys($locales, '');

        $contextStr = json_encode($projectContext, JSON_UNESCAPED_UNICODE);
        $localesList = implode(', ', $locales);

        $captionInstruction = ! empty(trim((string) $userCaption))
            ? "Caption source (translate only, do not change meaning): \"{$userCaption}\""
            : 'Generate a concise, descriptive caption (max 15 words) based on the project context.';

        $altInstruction = ! empty(trim((string) $userAlt))
            ? "Alt text source (translate only, do not change meaning): \"{$userAlt}\""
            : 'Generate a concise SEO-friendly alt text (max 12 words) based on the project context.';

        $prompt = <<<PROMPT
You are writing image metadata for a Belgian exterior lighting contractor's portfolio website.
Project context: {$contextStr}

Task 1 — Caption: {$captionInstruction}
Task 2 — Alt text: {$altInstruction}

Return JSON exactly like this, with all {$localesList} locales filled:
{"caption":{"nl":"...","en":"...","fr":"...","de":"..."},"alt":{"nl":"...","en":"...","fr":"...","de":"..."}}
PROMPT;

        $result = $this->generateStructuredResponse($prompt);

        return [
            'caption' => array_merge(
                $empty,
                (isset($result['caption']) && is_array($result['caption'])) ? $result['caption'] : []
            ),
            'alt' => array_merge(
                $empty,
                (isset($result['alt']) && is_array($result['alt'])) ? $result['alt'] : []
            ),
        ];
    }
}
