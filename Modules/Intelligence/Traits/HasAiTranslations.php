<?php

namespace Modules\Intelligence\Traits;

use Illuminate\Support\Facades\DB;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\TranslationStateService;

/**
 * CLA-611: automatic AI translation for Spatie-translatable models.
 *
 * COMPATIBILITY CONTRACT (do not break — 30+ FieldOps tests and the Website
 * baseline mock GeminiService::translateAndDetect and assert its exact call
 * pattern):
 *  - dispatch after commit with (class-string, scalar id, attributes,
 *    source locale) — ADR D6 shape, unchanged;
 *  - the job translates the MISSING locales per attribute via
 *    translateAndDetect($sourceText, $missingLocales) — unchanged;
 *  - no dispatch when no AI-translatable attribute is dirty — unchanged;
 *  - no FieldOps model edits: everything here lives inside the trait, and
 *    optional per-model context hooks are method_exists-guarded.
 *
 * NEW behaviour (gaps G1/G5): when the SOURCE text of an attribute changes
 * on update, every derived locale with an existing value is invalidated —
 * reviewed|published values move to needs_review and are NEVER overwritten
 * without an explicit retranslate action; machine (and legacy-stateless)
 * values move to stale and are requeued for retranslation by the job.
 */
trait HasAiTranslations
{
    public static function bootHasAiTranslations(): void
    {
        static::created(function ($model) {
            static::scheduleTranslationJob($model);
        });

        static::updated(function ($model) {
            $attrs = static::resolveAiAttributes($model);
            $dispatchNeeded = false;

            foreach ($attrs as $attr) {
                if (! $model->isDirty($attr)) {
                    continue;
                }

                $dispatchNeeded = true;

                if (static::sourceTextChanged($model, $attr)) {
                    static::invalidateDerivedLocales($model, $attr);
                }
            }

            if ($dispatchNeeded) {
                static::scheduleTranslationJob($model);
            }
        });
    }

    protected static function scheduleTranslationJob(object $model): void
    {
        $attrs = static::resolveAiAttributes($model);
        if (empty($attrs)) {
            return;
        }

        $locale = app()->getLocale();

        DB::afterCommit(function () use ($model, $attrs, $locale) {
            TranslateModelAttributesJob::dispatch(
                get_class($model),
                $model->getKey(),
                $attrs,
                $locale,
            );
        });
    }

    /**
     * Did the SOURCE-side text of this attribute actually change (not just
     * another locale being added)? The source locale is the request locale
     * at save time — the locale the editor was writing in.
     */
    protected static function sourceTextChanged(object $model, string $attr): bool
    {
        $original = $model->getRawOriginal($attr);
        $current = $model->getAttributes()[$attr] ?? null;

        if ($original === null || $current === null) {
            return $original !== $current;
        }

        $originalTranslations = json_decode((string) $original, true) ?: [];
        $currentTranslations = json_decode((string) $current, true) ?: [];

        $sourceLocale = app()->getLocale();

        return ($originalTranslations[$sourceLocale] ?? null) !== ($currentTranslations[$sourceLocale] ?? null);
    }

    /**
     * G1/G5: the source text changed — invalidate every derived locale that
     * holds a value. Approved human values are preserved (needs_review);
     * machine-derived values become stale and the job retranslates them.
     */
    protected static function invalidateDerivedLocales(object $model, string $attr): void
    {
        $service = app(TranslationStateService::class);
        $translations = method_exists($model, 'getTranslations')
            ? $model->getTranslations($attr)
            : [];
        $sourceLocale = app()->getLocale();

        foreach ($translations as $locale => $value) {
            if ($locale === $sourceLocale || filled($value) === false) {
                continue;
            }

            $state = $service->stateFor($model, $attr, $locale);

            $approved = in_array($state?->status, [TranslationState::STATUS_REVIEWED, TranslationState::STATUS_PUBLISHED], true);

            $service->recordState(
                $model,
                $attr,
                $locale,
                $approved ? TranslationState::STATUS_NEEDS_REVIEW : TranslationState::STATUS_STALE,
                $state?->source_locale ?? $sourceLocale,
                $state?->source_hash,
                $state?->glossary_version,
            );
        }
    }

    protected static function resolveAiAttributes(object $model): array
    {
        if (method_exists($model, 'getAiTranslatableAttributes')) {
            return $model->getAiTranslatableAttributes();
        }

        if (method_exists($model, 'getTranslatableAttributes')) {
            return $model->getTranslatableAttributes();
        }

        return property_exists($model, 'translatable') ? $model->translatable : [];
    }
}
