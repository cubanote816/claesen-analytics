<?php

namespace Modules\Intelligence\Traits;

use Illuminate\Support\Facades\DB;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;

trait HasAiTranslations
{
    public static function bootHasAiTranslations(): void
    {
        static::created(function ($model) {
            static::scheduleTranslationJob($model);
        });

        static::updated(function ($model) {
            $attrs = static::resolveAiAttributes($model);

            foreach ($attrs as $attr) {
                if ($model->isDirty($attr)) {
                    static::scheduleTranslationJob($model);
                    return;
                }
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
