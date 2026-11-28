<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature\TextGeneration;

use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\Drivers\FakeTextGenerationDriver;
use Modules\Intelligence\TextGeneration\TextGenerationManager;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Tests\TestCase;

/**
 * CLA-714 (S8a, S8b): provider and model are chosen per task from config,
 * fail closed, and the call is logged without the prompt.
 */
final class TextGenerationManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'intelligence.ai.default' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            'intelligence.ai.tasks' => [],
            'intelligence.ai.providers' => [
                'gemini' => ['label' => 'Google Gemini', 'models' => ['gemini-2.5-flash', 'gemini-2.5-pro']],
                'anthropic' => ['label' => 'Anthropic Claude', 'models' => ['claude-sonnet-5', 'claude-haiku-5-5']],
            ],
        ]);
    }

    private function manager(): TextGenerationManager
    {
        return app(TextGenerationManager::class);
    }

    public function test_task_with_its_own_config_uses_it(): void
    {
        config(['intelligence.ai.tasks.lead_summary' => ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5']]);

        $this->assertSame(
            ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'source' => 'task_config'],
            $this->manager()->resolve('lead_summary'),
        );
    }

    public function test_task_without_config_uses_the_default(): void
    {
        config(['intelligence.ai.tasks.lead_summary' => ['provider' => null, 'model' => null]]);

        $this->assertSame(
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'source' => 'default_config'],
            $this->manager()->resolve('lead_summary'),
        );
        $this->assertSame('default_config', $this->manager()->resolve('unknown_task')['source']);
    }

    public function test_task_provider_without_model_uses_a_model_allowed_for_that_provider(): void
    {
        config(['intelligence.ai.tasks.lead_summary' => ['provider' => 'anthropic', 'model' => null]]);
        $this->assertSame('claude-sonnet-5', $this->manager()->resolve('lead_summary')['model']);

        config(['intelligence.ai.tasks.lead_summary' => ['provider' => 'gemini', 'model' => '']]);
        $this->assertSame('gemini-2.5-flash', $this->manager()->resolve('lead_summary')['model']);
    }

    public function test_task_model_without_provider_uses_the_default_provider(): void
    {
        config(['intelligence.ai.tasks.lead_summary' => ['provider' => null, 'model' => 'gemini-2.5-pro']]);

        $this->assertSame(
            ['provider' => 'gemini', 'model' => 'gemini-2.5-pro', 'source' => 'task_config'],
            $this->manager()->resolve('lead_summary'),
        );

        config(['intelligence.ai.tasks.lead_summary' => ['provider' => null, 'model' => 'claude-haiku-5-5']]);
        $this->expectException(TextGenerationException::class);
        $this->manager()->resolve('lead_summary');
    }

    public function test_a_schema_answer_that_does_not_match_fails(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'fake', 'model' => 'fake-model']]);
        $fake = app(FakeTextGenerationDriver::class)->queueJson(['score' => 'high']);
        $schema = ['type' => 'object', 'required' => ['score', 'summary'], 'properties' => ['score' => ['type' => 'integer']]];

        try {
            $this->manager()->generate(new TextGenerationRequest(task: 'lead_summary', prompt: 'x', jsonSchema: $schema));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertSame('The fake answer did not match the requested JSON schema.', $e->getMessage());
        }

        $fake->queueJson(['score' => 7, 'summary' => 'ok']);
        $this->assertSame(7, $this->manager()->generate(new TextGenerationRequest(task: 'lead_summary', prompt: 'x', jsonSchema: $schema))->json['score']);

        $fake->queueJson(['a' => 1]);
        $this->expectExceptionMessage('The JSON schema uses an unsupported keyword: minProperties.');
        $this->manager()->generate(new TextGenerationRequest(task: 'lead_summary', prompt: 'x', jsonSchema: ['type' => 'object', 'minProperties' => 1]));
    }

    public function test_unknown_provider_fails_closed(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'openai', 'model' => 'gpt-x']]);

        $this->expectException(TextGenerationException::class);
        $this->expectExceptionMessage('Unknown AI provider "openai"');

        $this->manager()->resolve('lead_summary');
    }

    public function test_model_outside_the_allowlist_fails_closed(): void
    {
        config(['intelligence.ai.tasks.lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-9-ultra']]);

        $this->expectException(TextGenerationException::class);
        $this->expectExceptionMessage('Model "gemini-9-ultra" is not allowed for provider "gemini"');

        $this->manager()->resolve('lead_summary');
    }

    public function test_fake_provider_is_refused_in_production(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'fake', 'model' => 'fake-model']]);
        $this->assertSame('fake', $this->manager()->resolve('lead_summary')['provider']);

        $this->app['env'] = 'production';

        $this->expectException(TextGenerationException::class);
        $this->expectExceptionMessage('The fake AI provider is not allowed in production');

        $this->manager()->resolve('lead_summary');
    }

    public function test_generate_uses_the_resolved_driver_and_logs_without_the_prompt(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'fake', 'model' => 'fake-model']]);
        $fake = app(FakeTextGenerationDriver::class);
        $fake->queueJson(['summary' => 'ok']);
        Log::spy();

        $result = $this->manager()->generate(new TextGenerationRequest(
            task: 'lead_summary',
            prompt: 'SECRET-PROMPT prospect@example.com',
            system: 'SECRET-SYSTEM',
            jsonSchema: ['type' => 'object'],
        ));

        $this->assertSame(['summary' => 'ok'], $result->json);
        $this->assertSame('fake', $result->provider);
        $this->assertSame('fake-model', $result->model);
        $this->assertSame('fake-model', $fake->models[0]);
        $this->assertSame('SECRET-PROMPT prospect@example.com', $fake->requests[0]->prompt);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []): bool {
            $flat = json_encode($context);

            return $message === '[ai.text_generation]'
                && ($context['ok'] ?? null) === true
                && $context['task'] === 'lead_summary'
                && $context['provider'] === 'fake'
                && $context['model'] === 'fake-model'
                && $context['ok'] === true
                && ! str_contains($flat, 'SECRET');
        })->once();
    }

    public function test_an_unsupported_schema_is_refused_before_any_provider_call(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'fake', 'model' => 'fake-model']]);
        $fake = app(FakeTextGenerationDriver::class);
        $schema = ['type' => 'object', 'properties' => ['next_steps' => ['type' => 'array', 'maxItems' => 3]]];

        try {
            $this->manager()->generate(new TextGenerationRequest(task: 'lead_summary', prompt: 'x', jsonSchema: $schema));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertSame('The JSON schema uses an unsupported keyword: maxItems.', $e->getMessage());
        }

        $this->assertSame([], $fake->requests);
    }

    public function test_a_failed_call_is_logged_and_rethrown_without_the_prompt(): void
    {
        config(['intelligence.ai.default' => ['provider' => 'fake', 'model' => 'fake-model']]);
        app(FakeTextGenerationDriver::class)->queueFailure();
        Log::spy();

        try {
            $this->manager()->generate(new TextGenerationRequest(task: 'lead_summary', prompt: 'SECRET-PROMPT'));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => $message === '[ai.text_generation]'
            && ($context['ok'] ?? null) === false
            && ! str_contains(json_encode($context), 'SECRET'))->once();
    }
}
