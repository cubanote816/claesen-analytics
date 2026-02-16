<?php

namespace App\Traits;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;

trait HasAiTranslations
{
    public static function bootHasAiTranslations(): void
    {
        static::saving(function ($model) {
            // Avoid recursion if we are already processing
            if (static::$isProcessingAiTranslations ?? false) {
                return;
            }

            $model->processAiTranslations();
        });
    }

    protected static bool $isProcessingAiTranslations = false;

    public function processAiTranslations(): void
    {
        /** @var GeminiService $gemini */
        $gemini = app(GeminiService::class);

        // Use the locales configured in the plugin or a sensible default
        $targetLocales = ['nl', 'en', 'fr'];

        $attributesToTranslate = method_exists($this, 'getAiTranslatableAttributes')
            ? $this->getAiTranslatableAttributes()
            : $this->getTranslatableAttributes();

        foreach ($attributesToTranslate as $attribute) {
            $translations = $this->getTranslations($attribute);

            // Find the translation that was just updated or is the only one present
            // In Filament, it usually sets the translation for the active locale.
            // We'll look for the first non-empty string that looks like it needs processing.

            $sourceText = null;
            $sourceLocale = null;

            // We check the "dirty" state if possible, but for translatable fields it's an array.
            // For now, let's take the first non-empty translation as the "truth" if others are empty
            // or if the current app locale has content.

            $currentLocale = app()->getLocale();
            $textInCurrent = $translations[$currentLocale] ?? null;

            if ($textInCurrent) {
                $sourceText = $textInCurrent;
                $sourceLocale = $currentLocale;
            } else {
                // Fallback to the first non-empty one
                foreach ($translations as $locale => $text) {
                    if (!empty($text)) {
                        $sourceText = $text;
                        $sourceLocale = $locale;
                        break;
                    }
                }
            }

            if (!$sourceText) {
                continue;
            }

            // Check if we already have all translations to avoid unnecessary API calls
            $isMissingTranslations = false;
            foreach ($targetLocales as $loc) {
                if (empty($translations[$loc] ?? '')) {
                    $isMissingTranslations = true;
                    break;
                }
            }

            // Start of optimized logic:
            // 1. If the attribute is NOT dirty (not changed), AND we have all translations, skip it.
            if (!$this->isDirty($attribute) && !$isMissingTranslations) {
                continue;
            }
            // If it IS dirty, we proceed (re-translate).
            // If it is missing translations, we proceed (fill gaps).

            // If we have content, let's let Gemini decide if it needs correction/translation
            // Note: In a production environment, we might want to skip this if translations are already full
            // unless we want to "re-detect" everything.

            try {
                static::$isProcessingAiTranslations = true;

                $result = $gemini->translateAndDetect($sourceText, $targetLocales);
                $detected = $result['detected_locale'];
                $aiTranslations = $result['translations'];

                // Logic: 
                // 1. If detected != sourceLocale, it means the user wrote in the "wrong" tab.
                // 2. We move the content to the correct locale and apply translations to others.

                foreach ($aiTranslations as $locale => $translatedText) {
                    $this->setTranslation($attribute, $locale, $translatedText);
                }

                static::$isProcessingAiTranslations = false;
            } catch (\Exception $e) {
                Log::error("Failed to process AI translations for attribute '{$attribute}': " . $e->getMessage());
                static::$isProcessingAiTranslations = false;
            }
        }
    }
}
