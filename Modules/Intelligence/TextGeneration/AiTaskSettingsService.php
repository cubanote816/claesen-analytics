<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Models\AiTaskSetting;

/** CLA-714: writes the screen settings, validated against the allowlist. */
class AiTaskSettingsService
{
    /** Providers selectable on screen ("fake" never is). */
    public const SCREEN_PROVIDERS = ['gemini', 'anthropic'];

    /** @return list<string> tasks declared in config('intelligence.ai.tasks') */
    public static function tasks(): array
    {
        return array_keys(config('intelligence.ai.tasks', []));
    }

    /** @return array<string, string> provider => label */
    public static function providerOptions(): array
    {
        $options = [];

        foreach (self::SCREEN_PROVIDERS as $provider) {
            if (is_array(config("intelligence.ai.providers.{$provider}"))) {
                $options[$provider] = (string) config("intelligence.ai.providers.{$provider}.label", $provider);
            }
        }

        return $options;
    }

    /** @return list<string> */
    public static function models(?string $provider): array
    {
        if (! array_key_exists((string) $provider, self::providerOptions())) {
            return [];
        }

        return array_values(config("intelligence.ai.providers.{$provider}.models", []));
    }

    /** @throws TextGenerationException */
    public function save(string $task, string $provider, string $model, ?int $userId): AiTaskSetting
    {
        $this->assertAllowed($task, $provider, $model);

        $setting = AiTaskSetting::query()->firstOrNew(['task' => $task]);

        // A save that changes nothing is a no-op: no write, no audit entry, and
        // updated_by keeps pointing at whoever really made the current choice.
        if ($setting->exists && $setting->provider === $provider && $setting->model === $model) {
            return $setting;
        }

        $setting->fill(['provider' => $provider, 'model' => $model, 'updated_by' => $userId])->save();

        return $setting;
    }

    public function clear(string $task): void
    {
        AiTaskSetting::query()->where('task', $task)->get()->each->delete();
    }

    /** @throws TextGenerationException */
    public function assertAllowed(string $task, string $provider, string $model): void
    {
        if (! in_array($task, self::tasks(), true)) {
            throw new TextGenerationException("Unknown AI task \"{$task}\".");
        }

        if (! array_key_exists($provider, self::providerOptions())) {
            throw new TextGenerationException("Provider \"{$provider}\" cannot be chosen on screen.");
        }

        if (! in_array($model, self::models($provider), true)) {
            throw new TextGenerationException("Model \"{$model}\" is not allowed for provider \"{$provider}\".");
        }
    }
}
