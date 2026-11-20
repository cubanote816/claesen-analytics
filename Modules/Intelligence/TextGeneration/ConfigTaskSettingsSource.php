<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Contracts\TaskSettingsSource;

/** CLA-714: per-task settings from config('intelligence.ai.tasks'). */
final class ConfigTaskSettingsSource implements TaskSettingsSource
{
    public function name(): string
    {
        return 'task_config';
    }

    public function settingsFor(string $task): ?array
    {
        $settings = config("intelligence.ai.tasks.{$task}");

        if (! is_array($settings)) {
            return null;
        }

        return [
            'provider' => $settings['provider'] ?? null,
            'model' => $settings['model'] ?? null,
        ];
    }
}
