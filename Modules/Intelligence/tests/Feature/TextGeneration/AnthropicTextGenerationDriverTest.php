<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\AnthropicMessageClient;
use Modules\Intelligence\TextGeneration\Drivers\AnthropicTextGenerationDriver;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Tests\TestCase;

/** CLA-714 (S8a): Anthropic driver through the message-client seam. */
final class AnthropicTextGenerationDriverTest extends TestCase
{
    private function client(object $reply): AnthropicMessageClient
    {
        return new class($reply) extends AnthropicMessageClient
        {
            public array $calls = [];

            public function __construct(private object $reply) {}

            public function createMessage(string $apiKey, array $params): object
            {
                $this->calls[] = ['apiKey' => $apiKey, 'params' => $params];

                if ($this->reply instanceof \Throwable) {
                    throw $this->reply;
                }

                return $this->reply;
            }
        };
    }

    private function message(string $text): object
    {
        return (object) [
            'content' => [(object) ['type' => 'text', 'text' => $text]],
            'usage' => (object) ['inputTokens' => 20, 'outputTokens' => 5],
        ];
    }

    public function test_model_system_and_schema_are_passed_and_json_is_decoded(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $client = $this->client($this->message('{"next_step": "call"}'));
        $schema = ['type' => 'object'];

        $result = (new AnthropicTextGenerationDriver($client, 'claude-haiku-5-5', 30))->generate(new TextGenerationRequest(
            task: 't', prompt: 'Suggest', system: 'Be brief.', jsonSchema: $schema, maxTokens: 300,
        ));

        $this->assertSame(['next_step' => 'call'], $result->json);
        $this->assertSame('anthropic', $result->provider);
        $this->assertSame('claude-haiku-5-5', $result->model);
        $this->assertSame(20, $result->inputTokens);
        $this->assertSame(5, $result->outputTokens);

        $params = $client->calls[0]['params'];
        $this->assertSame('test-key', $client->calls[0]['apiKey']);
        $this->assertSame('claude-haiku-5-5', $params['model']);
        $this->assertSame('Be brief.', $params['system']);
        $this->assertSame(300, $params['maxTokens']);
        $this->assertSame([['role' => 'user', 'content' => 'Suggest']], $params['messages']);
        // Anthropic structured outputs need additionalProperties false on every object.
        $this->assertSame(['format' => ['type' => 'json_schema', 'schema' => ['type' => 'object', 'additionalProperties' => false]]], $params['outputConfig']);
        $this->assertSame(['timeout' => 30.0], $params['requestOptions']);
    }

    public function test_nested_objects_also_get_additional_properties_false(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $client = $this->client($this->message('{"lead": {"name": "x"}}'));
        $schema = ['type' => 'object', 'properties' => ['lead' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]]];

        (new AnthropicTextGenerationDriver($client, 'claude-sonnet-5', 30))->generate(new TextGenerationRequest(task: 't', prompt: 'x', jsonSchema: $schema));

        $wire = $client->calls[0]['params']['outputConfig']['format']['schema'];
        $this->assertFalse($wire['additionalProperties']);
        $this->assertFalse($wire['properties']['lead']['additionalProperties']);
        $this->assertArrayNotHasKey('additionalProperties', $wire['properties']['lead']['properties']['name']);
    }

    public function test_plain_text_without_schema_sends_no_output_config(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $client = $this->client($this->message('Hello'));

        $result = (new AnthropicTextGenerationDriver($client, 'claude-sonnet-5', 30))
            ->generate(new TextGenerationRequest(task: 't', prompt: 'Hi'));

        $this->assertSame('Hello', $result->text);
        $this->assertNull($result->json);
        $this->assertArrayNotHasKey('outputConfig', $client->calls[0]['params']);
        $this->assertArrayNotHasKey('system', $client->calls[0]['params']);
    }

    public function test_an_http_status_from_the_sdk_is_kept_in_the_message(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $sdkError = new class('raw upstream body') extends \RuntimeException
        {
            public ?int $status = 529;
        };

        try {
            (new AnthropicTextGenerationDriver($this->client($sdkError), 'claude-sonnet-5', 30))
                ->generate(new TextGenerationRequest(task: 't', prompt: 'Hi'));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertStringContainsString('HTTP 529', $e->getMessage());
            $this->assertStringNotContainsString('raw upstream body', $e->getMessage());
        }
    }

    public function test_missing_api_key_fails_before_calling(): void
    {
        config(['services.anthropic.key' => null]);
        $client = $this->client($this->message('x'));

        try {
            (new AnthropicTextGenerationDriver($client, 'claude-sonnet-5', 30))->generate(new TextGenerationRequest(task: 't', prompt: 'Hi'));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertStringContainsString('API key', $e->getMessage());
        }

        $this->assertSame([], $client->calls);
    }

    public function test_sdk_errors_become_a_text_generation_exception_without_the_raw_error(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $sdkError = new \RuntimeException('upstream body with x-api-key: test-key and SECRET-PROMPT');

        try {
            (new AnthropicTextGenerationDriver($this->client($sdkError), 'claude-sonnet-5', 30))
                ->generate(new TextGenerationRequest(task: 't', prompt: 'SECRET-PROMPT'));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertStringNotContainsString('test-key', $e->getMessage());
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    public function test_non_json_answer_with_a_schema_fails(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $this->expectException(TextGenerationException::class);

        (new AnthropicTextGenerationDriver($this->client($this->message('nope')), 'claude-sonnet-5', 30))
            ->generate(new TextGenerationRequest(task: 't', prompt: 'Hi', jsonSchema: ['type' => 'object']));
    }
}
