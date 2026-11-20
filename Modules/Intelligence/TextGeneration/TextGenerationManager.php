<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Contracts\TaskSettingsSource;
use Modules\Intelligence\Contracts\TextGenerationProvider;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\TextGeneration\Drivers\AnthropicTextGenerationDriver;
use Modules\Intelligence\TextGeneration\Drivers\FakeTextGenerationDriver;
use Modules\Intelligence\TextGeneration\Drivers\GeminiTextGenerationDriver;

/**
 * CLA-714: picks the AI provider and model for a task, so changing them
 * never needs a code change.
 *
 * Resolution: each TaskSettingsSource is asked in order; the first one that
 * names a provider for the task wins. A source that names only a model is
 * ignored. When the provider has no model, the default model is used if the
 * provider is the default provider, else the first allowed model of that
 * provider. With no source, config('intelligence.ai.default') applies.
 * Unknown providers and models outside config('intelligence.ai.providers.*.models')
 * fail closed. The "fake" provider is refused in production.
 *
 * Logs carry task, provider, model, duration and tokens, never the prompt,
 * the system instruction or the answer.
 */
class TextGenerationManager
{
    public const PROVIDERS = ['gemini', 'anthropic', 'fake'];

    /** @param  list<TaskSettingsSource>  $sources */
    public function __construct(
        private readonly Container $container,
        private readonly array $sources,
    ) {}

    /** @return array{provider: string, model: string, source: string} */
    public function resolve(string $task): array
    {
        $defaultProvider = (string) config('intelligence.ai.default.provider');
        $defaultModel = (string) config('intelligence.ai.default.model');

        foreach ($this->sources as $source) {
            $settings = $source->settingsFor($task);
            $provider = $settings['provider'] ?? null;

            if (blank($provider)) {
                continue;
            }

            $model = $settings['model'] ?? null;

            if (blank($model)) {
                $model = $provider === $defaultProvider
                    ? $defaultModel
                    : (config("intelligence.ai.providers.{$provider}.models")[0] ?? '');
            }

            return $this->validated((string) $provider, (string) $model, $source->name());
        }

        return $this->validated($defaultProvider, $defaultModel, 'default_config');
    }

    public function forTask(string $task): TextGenerationProvider
    {
        $resolved = $this->resolve($task);

        return $this->driver($resolved['provider'], $resolved['model']);
    }

    /** @throws TextGenerationException */
    public function generate(TextGenerationRequest $request): TextGenerationResult
    {
        $resolved = $this->resolve($request->task);
        $startedAt = hrtime(true);

        try {
            $result = $this->driver($resolved['provider'], $resolved['model'])->generate($request);
        } catch (\Throwable $e) {
            Log::warning('[ai.text_generation]', [
                'task' => $request->task,
                'provider' => $resolved['provider'],
                'model' => $resolved['model'],
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'ok' => false,
                'error' => class_basename($e),
            ]);

            throw $e instanceof TextGenerationException
                ? $e
                : new TextGenerationException('The AI request failed ('.class_basename($e).').', 0, $e);
        }

        Log::info('[ai.text_generation]', [
            'task' => $request->task,
            'provider' => $result->provider,
            'model' => $result->model,
            'duration_ms' => $result->durationMs,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'ok' => true,
        ]);

        return $result;
    }

    /** @return array{provider: string, model: string, source: string} */
    private function validated(string $provider, string $model, string $source): array
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new TextGenerationException("Unknown AI provider \"{$provider}\".");
        }

        if ($provider === 'fake') {
            if ($this->container->environment('production')) {
                throw new TextGenerationException('The fake AI provider is not allowed in production.');
            }
        } elseif (! in_array($model, config("intelligence.ai.providers.{$provider}.models", []), true)) {
            throw new TextGenerationException("Model \"{$model}\" is not allowed for provider \"{$provider}\".");
        }

        return ['provider' => $provider, 'model' => $model, 'source' => $source];
    }

    private function driver(string $provider, string $model): TextGenerationProvider
    {
        $timeout = (int) config('intelligence.ai.timeout', 30);

        return match ($provider) {
            'gemini' => new GeminiTextGenerationDriver($this->container->make(GeminiService::class), $model, $timeout),
            'anthropic' => new AnthropicTextGenerationDriver($this->container->make(AnthropicMessageClient::class), $model, $timeout),
            'fake' => $this->container->make(FakeTextGenerationDriver::class)->usingModel($model),
        };
    }
}
