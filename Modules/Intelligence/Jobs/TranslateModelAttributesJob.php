<?php

declare(strict_types=1);

namespace Modules\Intelligence\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GlossaryService;
use Modules\Intelligence\Services\TranslationStateService;

/**
 * CLA-611: translate the translatable attributes of one model record.
 *
 * Frozen call pattern (do not break — FieldOps/Website tests mock and
 * assert it): per attribute, translateAndDetect($sourceText, $missingLocales)
 * where $missingLocales are the locales WITHOUT a value, extended with the
 * locales the CLA-611 invalidation marked `stale` (source text changed).
 *
 * Per-locale state (new): success writes `machine` (+ source hash +
 * glossary version); a provider failure (exception OR empty result) writes
 * an OBSERVABLE `failed` state with the error (gap G3 — previously a failed
 * translation was invisible). States reviewed|published are never touched
 * here — the trait downgraded them to needs_review on source change, and
 * only an explicit human retranslate action may overwrite them (gap G5).
 */
class TranslateModelAttributesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOCALES = ['nl', 'en', 'fr', 'de'];

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        private readonly string $modelClass,
        private readonly int    $modelId,
        private readonly array  $attributes,
        private readonly string $sourceLocale
    ) {}

    public function handle(GeminiService $gemini): void
    {
        // F3/CLA-471 (docs/ai/adr-multi-organization.md): this job is generic
        // across every translatable model in the app, most of which never use
        // Modules\Core\Models\Concerns\BelongsToSite — and the one that does
        // (Website\Project) is looked up here by primary key from a trusted,
        // internally-dispatched job payload, never user input. Site scoping
        // exists to stop a request from seeing another site's rows by
        // accident; a lookup that already names the exact row it wants has
        // nothing left for that scope to protect, so it's excluded rather
        // than requiring every job dispatch site to carry a resolved site
        // just to read it back a moment later. withoutGlobalScope('site') is
        // a no-op for the (majority of) models that never registered it.
        /** @var \Illuminate\Database\Eloquent\Model&\Spatie\Translatable\HasTranslations $model */
        $model = ($this->modelClass)::query()->withoutGlobalScope('site')->find($this->modelId);
        if (!$model) {
            return;
        }

        $stateService = app(TranslationStateService::class);
        $glossary = app(GlossaryService::class)->forSite($model->getAttribute('site_id') !== null ? (int) $model->getAttribute('site_id') : null);
        // Optional per-model context (page_id/section/field kind) — models
        // without the hook get null; method_exists-guarded so no model is
        // forced to implement it.
        $context = method_exists($model, 'getAiTranslationContext')
            ? $model->getAiTranslationContext()
            : null;

        $changed = false;

        foreach ($this->attributes as $attribute) {
            $translations = $model->getTranslations($attribute);
            $sourceText   = $translations[$this->sourceLocale] ?? null;

            if (empty($sourceText)) {
                continue;
            }

            // G1: locales to (re)translate = empty ones + stale ones (the
            // trait marked them when the source text changed).
            $staleLocales = TranslationState::query()
                ->where('translatable_type', $model->getMorphClass())
                ->where('translatable_id', $model->getKey())
                ->where('attribute', $attribute)
                ->where('status', TranslationState::STATUS_STALE)
                ->pluck('locale')
                ->all();

            $missingLocales = array_values(array_unique(array_merge(
                array_filter(self::LOCALES, fn (string $l) => empty($translations[$l] ?? '')),
                $staleLocales
            )));

            if (empty($missingLocales)) {
                continue;
            }

            try {
                $result = $gemini->translateAndDetect($sourceText, $missingLocales, $context, $glossary['terms'], $glossary['version']);

                $translationsReturned = $result['translations'] ?? [];

                foreach ($translationsReturned as $locale => $text) {
                    if (in_array($locale, $missingLocales, true) && !empty($text)) {
                        $model->setTranslation($attribute, $locale, $text);
                        $changed = true;

                        $stateService->recordState(
                            $model,
                            $attribute,
                            $locale,
                            TranslationState::STATUS_MACHINE,
                            $this->sourceLocale,
                            hash('sha256', $sourceText),
                            $glossary['version'],
                        );
                    }
                }

                // G3: an incomplete/empty provider result is an observable
                // failure, not silence — the requested locales that came
                // back empty go to `failed` with the reason, visible in the
                // panel.
                $failedLocales = array_diff($missingLocales, array_keys(array_filter(
                    $translationsReturned,
                    fn ($text) => filled($text)
                )));

                foreach ($failedLocales as $locale) {
                    $stateService->recordState(
                        $model,
                        $attribute,
                        $locale,
                        TranslationState::STATUS_FAILED,
                        $this->sourceLocale,
                        null,
                        $glossary['version'],
                        'Provider returned no translation for this locale.',
                    );
                }
            } catch (\Exception $e) {
                Log::error("TranslateModelAttributesJob: Gemini failed for {$this->modelClass}#{$this->modelId} attr={$attribute}: {$e->getMessage()}");

                foreach ($missingLocales as $locale) {
                    $stateService->recordState(
                        $model,
                        $attribute,
                        $locale,
                        TranslationState::STATUS_FAILED,
                        $this->sourceLocale,
                        null,
                        $glossary['version'],
                        $e->getMessage(),
                    );
                }
            }
        }

        $allComplete = $this->allLocalesComplete($model);
        $newStatus   = $allComplete ? 'complete' : 'pending';

        // The legacy ROW-level column only exists on the FieldOps tables
        // that introduced it — the Website CLA-611 tables track per-locale
        // state exclusively. Never write a column the model's table lacks.
        $hasLegacyStatusColumn = array_key_exists('ai_translation_status', $model->getAttributes());

        if ($changed || ($hasLegacyStatusColumn && $this->statusNeedsUpdate($model, $newStatus))) {
            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update(array_merge(
                    $changed ? ['updated_at' => now()] : [],
                    $hasLegacyStatusColumn ? ['ai_translation_status' => $newStatus] : [],
                    $changed ? $this->buildTranslationUpdates($model) : [],
                ));
        }
    }

    private function allLocalesComplete(object $model): bool
    {
        foreach ($this->attributes as $attribute) {
            foreach (self::LOCALES as $locale) {
                $val = method_exists($model, 'getTranslation')
                    ? ($model->getTranslation($attribute, $locale, false) ?? '')
                    : '';
                if (empty($val)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function statusNeedsUpdate(object $model, string $newStatus): bool
    {
        return isset($model->ai_translation_status)
            && $model->ai_translation_status !== $newStatus;
    }

    private function buildTranslationUpdates(object $model): array
    {
        $updates = [];
        foreach ($this->attributes as $attribute) {
            if (method_exists($model, 'getTranslations')) {
                $updates[$attribute] = json_encode($model->getTranslations($attribute));
            }
        }

        return $updates;
    }
}
