<?php

declare(strict_types=1);

namespace Modules\Intelligence\Contracts;

/**
 * CLA-714: where the provider and model for an AI task can be set.
 * TextGenerationManager asks each source in order; the first one that
 * names a provider for the task wins, otherwise the config default applies.
 */
interface TaskSettingsSource
{
    /** Short identifier reported as the resolution source, e.g. "task_config". */
    public function name(): string;

    /** @return array{provider: ?string, model: ?string}|null */
    public function settingsFor(string $task): ?array;
}
