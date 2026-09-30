<?php

declare(strict_types=1);

namespace Modules\Intelligence\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intelligence\Models\TranslationState;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GlossaryService;
use Modules\Intelligence\Services\TranslationStateService;

/**
 * CLA-611 (gaps G1/G7): bulk, RESUMABLE translation of everything the
 * per-locale state engine has queued as stale|failed (and optionally every
 * locale that is simply missing, --include-missing).
 *
 * Groups pending items by target locale and translates at most
 * GeminiService::MAX_BATCH_SIZE (20) source strings per provider call.
 * Interrupted runs are safe: only pending state rows are processed, each
 * item's state is persisted as it completes, and a re-run picks up the rest
 * (the same reason 2014-line imports don't have to finish in one go).
 * Every provider call is logged (coste registrado).
 */
class TranslatePendingTranslationsCommand extends Command
{
    protected $signature = 'intelligence:translate-pending
                            {--include-missing : Also translate locales with no value at all (initial import)}
                            {--model= : Restrict to one translatable model class}';

    protected $description = 'Translate all stale/failed AI-translation states in batches of 20 strings per provider call (resumable)';

    public function handle(GeminiService $gemini, TranslationStateService $stateService, GlossaryService $glossaryService): int
    {
        $query = TranslationState::query()->where(function ($q): void {
            $q->whereIn('status', [
                TranslationState::STATUS_STALE,
                TranslationState::STATUS_FAILED,
            ]);

            if ($this->option('include-missing')) {
                $q->orWhere('status', TranslationState::STATUS_MISSING);
            }
        });

        if ($model = $this->option('model')) {
            $query->where('translatable_type', $model);
        }

        $states = $query->orderBy('id')->get();

        if ($states->isEmpty()) {
            $this->info('No pending translation states.');

            return self::SUCCESS;
        }

        $siteIds = $states->pluck('site_id')->filter()->unique()->values();
        $glossaries = $siteIds->mapWithKeys(fn ($siteId) => [$siteId => $glossaryService->forSite((int) $siteId)]);
        $glossaryVersionless = $glossaryService->forSite(null);

        $translated = 0;
        $failed = 0;

        // Group pending items by (model instance, attribute, target locale),
        // then chunk each group into provider calls of <= 20 source strings.
        $byModel = $states->groupBy(fn (TranslationState $state) => $state->translatable_type.'#'.$state->translatable_id);

        foreach ($byModel as $group) {
            /** @var TranslationState $first */
            $first = $group->first();
            $modelClass = $first->translatable_type;
            $model = $modelClass::query()->withoutGlobalScope('site')->find($first->translatable_id);

            if (! $model) {
                continue;
            }

            $siteId = $model->getAttribute('site_id');
            $glossary = $siteId !== null
                ? ($glossaries[(int) $siteId] ?? $glossaryVersionless)
                : $glossaryVersionless;
            $context = method_exists($model, 'getAiTranslationContext')
                ? $model->getAiTranslationContext()
                : null;

            foreach ($group->groupBy('attribute') as $attribute => $attributeStates) {
                foreach ($attributeStates->groupBy('locale') as $locale => $localeStates) {
                    $items = [];

                    foreach ($localeStates as $state) {
                        /** @var TranslationState $state */
                        $sourceLocale = $state->source_locale ?? config('app.fallback_locale');
                        $sourceText = $model->getTranslation($attribute, $sourceLocale, false);

                        if (filled($sourceText)) {
                            $items[$state->id] = $sourceText;
                        }
                    }

                    foreach (array_chunk($items, GeminiService::MAX_BATCH_SIZE, true) as $chunk) {
                        try {
                            $batchResult = $gemini->translateBatch(
                                $chunk,
                                (string) $locale,
                                $context,
                                $glossary['terms'],
                                $glossary['version']
                            );

                            foreach ($batchResult as $stateId => $translations) {
                                $text = $translations[$locale] ?? '';

                                /** @var TranslationState|null $state */
                                $state = TranslationState::query()->find($stateId);

                                if ($state === null) {
                                    continue; // deleted mid-run — resumable no-op
                                }

                                if (filled($text)) {
                                    $model->setTranslation($attribute, $locale, $text);

                                    $stateService->recordState(
                                        $model,
                                        $attribute,
                                        $locale,
                                        TranslationState::STATUS_MACHINE,
                                        $state->source_locale,
                                        hash('sha256', $items[$stateId] ?? ''),
                                        $glossary['version'],
                                    );

                                    $translated++;
                                } else {
                                    $stateService->recordState(
                                        $model,
                                        $attribute,
                                        $locale,
                                        TranslationState::STATUS_FAILED,
                                        $state->source_locale,
                                        null,
                                        $glossary['version'],
                                        'Provider returned no translation in batch.',
                                    );

                                    $failed++;
                                }
                            }

                            $model->saveQuietly();
                        } catch (\Throwable $e) {
                            foreach (array_keys($chunk) as $stateId) {
                                $state = TranslationState::query()->find($stateId);

                                if ($state !== null) {
                                    $stateService->recordState(
                                        $model,
                                        $attribute,
                                        $locale,
                                        TranslationState::STATUS_FAILED,
                                        $state->source_locale,
                                        null,
                                        $glossary['version'],
                                        $e->getMessage(),
                                    );
                                }
                            }

                            $failed += count($chunk);
                            $this->error("Batch failed: {$e->getMessage()}");
                        }
                    }
                }
            }
        }

        $this->info("Translated {$translated} item(s); {$failed} failed. Provider calls are logged per batch.");

        return self::SUCCESS;
    }
}
