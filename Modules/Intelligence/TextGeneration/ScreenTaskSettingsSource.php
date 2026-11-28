<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Contracts\TaskSettingsSource;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Models\AiTaskSetting;

/**
 * CLA-714: per-task settings chosen on the "AI providers" screen.
 *
 * Read from the database on every resolution, without a cache: one indexed
 * query is negligible next to the AI call itself, and with no cache there is
 * no window in which a worker keeps routing to the old provider after a save.
 * Only a missing table (not migrated yet) falls back to the configuration;
 * any other read failure fails closed, so a transient error never reroutes
 * data to a different provider.
 */
final class ScreenTaskSettingsSource implements TaskSettingsSource
{
    /** @param  class-string<AiTaskSetting>  $model */
    public function __construct(private readonly string $model = AiTaskSetting::class) {}

    public function name(): string
    {
        return 'screen';
    }

    public function settingsFor(string $task): ?array
    {
        try {
            $setting = $this->model::query()->where('task', $task)->first();
        } catch (QueryException $e) {
            if ($e->getCode() !== '42S02') {
                throw new TextGenerationException('The AI screen settings could not be read.');
            }

            Log::warning('[ai.text_generation] screen settings table missing, using configuration', ['task' => $task]);

            return null;
        }

        return $setting === null ? null : ['provider' => $setting->provider, 'model' => $setting->model];
    }
}
