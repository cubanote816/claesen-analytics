<?php

declare(strict_types=1);

namespace Modules\Intelligence\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Models\Glossary;
use Modules\Intelligence\Models\TranslationState;

/**
 * CLA-611: read/write access to the per-(attribute, locale) translation
 * state, plus the derived public-visibility rule (gap G2 and the "estado
 * público derivado, no declarado" requirement).
 *
 * An entity is "approved" in a locale ONLY when every tracked translatable
 * attribute of that entity is reviewed|published — computed from the state
 * rows on every read, never a hand-editable flag.
 */
class TranslationStateService
{
    /**
     * @return array<string, string> attribute => status for the given locale
     */
    public function statusesForAttributeLocales(Model $model, array $attributes, string $locale): array
    {
        return TranslationState::query()
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->whereIn('attribute', $attributes)
            ->where('locale', $locale)
            ->pluck('status', 'attribute')
            ->all();
    }

    /**
     * Worst status across the given attributes for one locale — the public
     * per-locale translation_status of an entity. Attributes WITHOUT any
     * state row count as machine when they carry a value and missing when
     * they don't (legacy data: never silently approved).
     *
     * @param  array<string>  $attributes
     */
    public function entityStatusForLocale(Model $model, array $attributes, string $locale): string
    {
        $states = $this->statusesForAttributeLocales($model, $attributes, $locale);
        $statuses = [];

        foreach ($attributes as $attribute) {
            $statuses[] = $states[$attribute]
                ?? $this->legacyStatusFor($model, $attribute, $locale);
        }

        return TranslationState::worstOf($statuses);
    }

    private function legacyStatusFor(Model $model, string $attribute, string $locale): string
    {
        $hasValue = method_exists($model, 'getTranslation')
            && filled($model->getTranslation($attribute, $locale, false));

        return $hasValue ? TranslationState::STATUS_MACHINE : TranslationState::STATUS_MISSING;
    }

    public function recordState(
        Model $model,
        string $attribute,
        string $locale,
        string $status,
        ?string $sourceLocale = null,
        ?string $sourceHash = null,
        ?int $glossaryVersion = null,
        ?string $error = null,
    ): TranslationState {
        return TranslationState::query()->updateOrCreate(
            [
                'translatable_type' => $model->getMorphClass(),
                'translatable_id' => $model->getKey(),
                'attribute' => $attribute,
                'locale' => $locale,
            ],
            array_filter([
                'site_id' => $model->getAttribute('site_id'),
                'status' => $status,
                'source_locale' => $sourceLocale,
                'source_hash' => $sourceHash,
                'glossary_version' => $glossaryVersion,
                'error' => $error,
            ], fn ($value) => $value !== null)
        );
    }

    public function stateFor(Model $model, string $attribute, string $locale): ?TranslationState
    {
        return TranslationState::query()
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->where('attribute', $attribute)
            ->where('locale', $locale)
            ->first();
    }
}
