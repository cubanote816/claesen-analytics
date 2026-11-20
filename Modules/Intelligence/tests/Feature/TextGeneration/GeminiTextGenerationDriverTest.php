<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature\TextGeneration;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\TextGeneration\Drivers\GeminiTextGenerationDriver;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Tests\TestCase;

/** CLA-714 (S8a): Gemini driver over a faked HTTP layer. */
final class GeminiTextGenerationDriverTest extends TestCase
{
    private const URL = 'https://vertex.test/v1/projects/p/locations/l/publishers/google/models/gemini-2.5-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.url' => self::URL]);
        $this->mock(GoogleServiceAccountAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
        });
        Http::preventStrayRequests();
    }

    private function driver(string $model = 'gemini-2.5-pro'): GeminiTextGenerationDriver
    {
        return new GeminiTextGenerationDriver(app(GeminiService::class), $model, 30);
    }

    private function answer(string $text, ?array $usage = null): array
    {
        return array_filter([
            'candidates' => [['content' => ['parts' => [['text' => $text]]]]],
            'usageMetadata' => $usage,
        ]);
    }

    public function test_the_requested_model_is_called_and_text_is_returned_with_usage(): void
    {
        Http::fake(['*' => Http::response($this->answer('Hello', ['promptTokenCount' => 12, 'candidatesTokenCount' => 3]))]);

        $result = $this->driver()->generate(new TextGenerationRequest(task: 't', prompt: 'Say hello'));

        $this->assertSame('Hello', $result->text);
        $this->assertNull($result->json);
        $this->assertSame('gemini', $result->provider);
        $this->assertSame('gemini-2.5-pro', $result->model);
        $this->assertSame(12, $result->inputTokens);
        $this->assertSame(3, $result->outputTokens);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://vertex.test/v1/projects/p/locations/l/publishers/google/models/gemini-2.5-pro:generateContent'
                && $request->hasHeader('Authorization', 'Bearer fake-token')
                && $request['contents'][0]['parts'][0]['text'] === 'Say hello'
                && ! isset($request['systemInstruction'])
                && ! isset($request['generationConfig']['responseSchema']);
        });
    }

    public function test_system_instruction_and_schema_are_sent_and_json_is_decoded(): void
    {
        Http::fake(['*' => Http::response($this->answer('{"score": 7}'))]);
        $schema = ['type' => 'object', 'properties' => ['score' => ['type' => 'integer']]];

        $result = $this->driver()->generate(new TextGenerationRequest(
            task: 't', prompt: 'Rate it', system: 'You are strict.', jsonSchema: $schema, maxTokens: 256, temperature: 0.1,
        ));

        $this->assertSame(['score' => 7], $result->json);
        Http::assertSent(fn (Request $request): bool => $request['systemInstruction']['parts'][0]['text'] === 'You are strict.'
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['responseSchema'] === $schema
            && $request['generationConfig']['maxOutputTokens'] === 256
            && $request['generationConfig']['temperature'] === 0.1);
    }

    public function test_non_json_answer_with_a_schema_fails(): void
    {
        Http::fake(['*' => Http::response($this->answer('not json'))]);

        $this->expectException(TextGenerationException::class);

        $this->driver()->generate(new TextGenerationRequest(task: 't', prompt: 'Rate it', jsonSchema: ['type' => 'object']));
    }

    public function test_http_failure_fails_without_leaking_the_prompt(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->driver()->generate(new TextGenerationRequest(task: 't', prompt: 'SECRET-PROMPT'));
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    public function test_existing_structured_response_still_uses_the_configured_url(): void
    {
        Http::fake(['*' => Http::response($this->answer('{"a": 1}'))]);

        $this->assertSame(['a' => 1], app(GeminiService::class)->generateStructuredResponse('x'));
        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && $request['generationConfig']['response_mime_type'] === 'application/json');
    }
}
