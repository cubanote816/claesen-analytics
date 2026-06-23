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
use Modules\Intelligence\Services\GeminiService;

class TranslateModelAttributesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOCALES = ['nl', 'en', 'fr', 'de'];

    public function __construct(
        private readonly string $modelClass,
        private readonly int    $modelId,
        private readonly array  $attributes,
        private readonly string $sourceLocale
    ) {}

    public function handle(GeminiService $gemini): void
    {
        /** @var \Illuminate\Database\Eloquent\Model&\Spatie\Translatable\HasTranslations $model */
        $model = ($this->modelClass)::find($this->modelId);
        if (!$model) {
            return;
        }

        $changed = false;

        foreach ($this->attributes as $attribute) {
            $translations = $model->getTranslations($attribute);
            $sourceText   = $translations[$this->sourceLocale] ?? null;

            if (empty($sourceText)) {
                continue;
            }

            $missingLocales = array_values(
                array_filter(self::LOCALES, fn (string $l) => empty($translations[$l] ?? ''))
            );

            if (empty($missingLocales)) {
                continue;
            }

            try {
                $result = $gemini->translateAndDetect($sourceText, $missingLocales);

                foreach ($result['translations'] ?? [] as $locale => $text) {
                    if (in_array($locale, $missingLocales, true) && !empty($text)) {
                        $model->setTranslation($attribute, $locale, $text);
                        $changed = true;
                    }
                }
            } catch (\Exception $e) {
                Log::error("TranslateModelAttributesJob: Gemini failed for {$this->modelClass}#{$this->modelId} attr={$attribute}: {$e->getMessage()}");
            }
        }

        $allComplete = $this->allLocalesComplete($model);
        $newStatus   = $allComplete ? 'complete' : 'pending';

        if ($changed || $this->statusNeedsUpdate($model, $newStatus)) {
            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update(array_merge(
                    $changed ? ['updated_at' => now()] : [],
                    ['ai_translation_status' => $newStatus],
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
