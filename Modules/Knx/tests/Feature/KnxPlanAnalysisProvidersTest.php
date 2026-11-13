<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Knx\Services\PlanAnalysis\OpenAiApiKeyCredentials;
use Modules\Knx\Services\PlanAnalysis\OpenAiCredentialSource;
use Modules\Knx\Services\PlanAnalysis\OpenAiPlanAnalysisProvider;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisFailed;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisInput;
use Modules\Knx\Services\PlanAnalysis\SyntheticPlanAnalysisProvider;
use Tests\TestCase;

/**
 * The plan-analysis providers (KNX-48, plano IA) WITHOUT any real call: every HTTP
 * request is faked or forbidden. Pins that the OpenAI adapter ships switched off,
 * follows the documented Responses API request, respects the backend budget, and that
 * the synthetic provider answers only from synthetic examples.
 */
final class KnxPlanAnalysisProvidersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function input(string $png = "\x89PNG-synthetic"): PlanAnalysisInput
    {
        return new PlanAnalysisInput(
            page: 2,
            pageWidthPt: 1190.55,
            pageHeightPt: 841.89,
            imagePng: $png,
            groups: [
                ['id' => 'style:f:#000000', 'kind' => 'style', 'name' => 'Fill #000000', 'paths' => 25, 'strokeWidth' => null, 'strokeColor' => null, 'closedPct' => 100, 'curvedPct' => 0],
                ['id' => 'style:s:#0066cc:0.5', 'kind' => 'style', 'name' => 'Stroke #0066cc 0.5 pt', 'paths' => 18, 'strokeWidth' => 0.5, 'strokeColor' => '#0066cc', 'closedPct' => 0, 'curvedPct' => 0],
                ['id' => 'style:s:#cc4c00:0.6', 'kind' => 'style', 'name' => 'Stroke #cc4c00 0.6 pt', 'paths' => 10, 'strokeWidth' => 0.6, 'strokeColor' => '#cc4c00', 'closedPct' => 0, 'curvedPct' => 50],
                ['id' => 'style:s:#663399:0.4', 'kind' => 'style', 'name' => 'Stroke #663399 0.4 pt', 'paths' => 14, 'strokeWidth' => 0.4, 'strokeColor' => '#663399', 'closedPct' => 0, 'curvedPct' => 0],
                ['id' => 'style:s:#008080:0.7', 'kind' => 'style', 'name' => 'Stroke #008080 0.7 pt', 'paths' => 4, 'strokeWidth' => 0.7, 'strokeColor' => '#008080', 'closedPct' => 25, 'curvedPct' => 0],
                ['id' => 'style:s:#990033:0.35', 'kind' => 'style', 'name' => 'Stroke #990033 0.35 pt', 'paths' => 6, 'strokeWidth' => 0.35, 'strokeColor' => '#990033', 'closedPct' => 50, 'curvedPct' => 0],
                ['id' => 'style:s:#80591a:0.3', 'kind' => 'style', 'name' => 'Stroke #80591a 0.3 pt', 'paths' => 4, 'strokeWidth' => 0.3, 'strokeColor' => '#80591a', 'closedPct' => 100, 'curvedPct' => 0],
            ],
            texts: [
                ['id' => 't0', 'text' => 'Woonkamer', 'x' => 0.19, 'y' => 0.34],
                ['id' => 't1', 'text' => '90,5 m2', 'x' => 0.19, 'y' => 0.36],
                ['id' => 't2', 'text' => 'WC', 'x' => 0.25, 'y' => 0.48],
                ['id' => 't3', 'text' => 'Carport', 'x' => 0.8, 'y' => 0.7],
            ],
        );
    }

    private function enableOpenAi(int $callsPerDay = 5, int $retries = 0): void
    {
        config([
            'knx.plan_analysis.providers.openai.enabled' => true,
            'knx.plan_analysis.providers.openai.model' => 'test-model',
            'knx.plan_analysis.providers.openai.api_key' => 'sk-test-only',
            'knx.plan_analysis.providers.openai.base_url' => 'https://openai.test/v1',
            'knx.plan_analysis.budget.max_calls_per_day' => $callsPerDay,
            'knx.plan_analysis.budget.max_retries' => $retries,
            'knx.plan_analysis.budget.max_output_tokens' => 1234,
        ]);
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    private function completed(array $proposal): array
    {
        return [
            'id' => 'resp_test',
            'object' => 'response',
            'status' => 'completed',
            'model' => 'test-model-2026',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'output_text', 'text' => json_encode($proposal)]],
            ]],
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 200],
        ];
    }

    private function failure(callable $call): string
    {
        try {
            $call();
        } catch (PlanAnalysisFailed $e) {
            return $e->reason;
        }
        $this->fail('Expected PlanAnalysisFailed.');
    }

    public function test_the_openai_adapter_ships_switched_off_and_sends_nothing(): void
    {
        Http::fake();

        $reason = $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input()));

        $this->assertSame('unavailable', $reason);
        $this->assertFalse(config('knx.plan_analysis.providers.openai.enabled'));
        $this->assertNull(config('knx.plan_analysis.providers.openai.model'));
        $this->assertSame(0, config('knx.plan_analysis.budget.max_calls_per_day'));
        Http::assertNothingSent();
    }

    public function test_enabled_without_budget_sends_nothing(): void
    {
        Http::fake();
        $this->enableOpenAi(callsPerDay: 0);

        $this->assertSame('budget_exhausted', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input())));
        Http::assertNothingSent();
    }

    public function test_it_sends_the_documented_responses_request_and_reads_the_structured_answer(): void
    {
        $this->enableOpenAi();
        $proposal = ['groupRoles' => [['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'fills']], 'rooms' => [], 'uncertain' => []];
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed($proposal))]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input("\x89PNG-bytes"));

        $this->assertSame('openai', $result->provider);
        $this->assertSame('test-model-2026', $result->model);
        $this->assertSame($proposal, $result->proposal);
        $this->assertSame(['inputTokens' => 1500, 'outputTokens' => 200], $result->usage);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $content = $body['input'][0]['content'];
            $summary = json_decode($content[0]['text'], true);

            return $request->method() === 'POST'
                && $request->url() === 'https://openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer sk-test-only')
                && $body['model'] === 'test-model'
                && $body['store'] === false
                && $body['max_output_tokens'] === 1234
                && $body['text']['format']['type'] === 'json_schema'
                && $body['text']['format']['strict'] === true
                && $body['text']['format']['name'] === 'plan_analysis'
                && $body['text']['format']['schema']['required'] === ['groupRoles', 'rooms', 'uncertain']
                && $body['input'][0]['role'] === 'user'
                && $content[0]['type'] === 'input_text'
                && $summary['page'] === 2
                && count($summary['groups']) === 7
                && $summary['texts'][0] === ['id' => 't0', 'text' => 'Woonkamer', 'x' => 0.19, 'y' => 0.34]
                && $content[1]['type'] === 'input_image'
                && $content[1]['image_url'] === 'data:image/png;base64,'.base64_encode("\x89PNG-bytes")
                && $content[1]['detail'] === 'high';
        });
    }

    public function test_a_refusal_an_incomplete_answer_and_non_json_are_failures(): void
    {
        $this->enableOpenAi();
        Http::fakeSequence('https://openai.test/v1/responses')
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]])
            ->push(['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => []])
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'not json']]]]]);
        $provider = app(OpenAiPlanAnalysisProvider::class);

        $this->assertSame('refused', $this->failure(fn () => $provider->analyze($this->input())));
        $this->assertSame('incomplete', $this->failure(fn () => $provider->analyze($this->input())));
        $this->assertSame('invalid_output', $this->failure(fn () => $provider->analyze($this->input())));
    }

    public function test_without_retries_a_server_error_fails_after_one_request(): void
    {
        $this->enableOpenAi(retries: 0);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'boom'], 500)]);

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input())));
        Http::assertSentCount(1);
    }

    public function test_a_server_error_is_retried_within_the_budget(): void
    {
        $this->enableOpenAi(retries: 1);
        Http::fake(['https://openai.test/v1/responses' => Http::sequence()
            ->push(['error' => 'busy'], 503)
            ->push($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input());

        $this->assertSame([], $result->proposal['rooms']);
        Http::assertSentCount(2);
    }

    public function test_a_client_error_is_never_retried(): void
    {
        $this->enableOpenAi(retries: 3);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'bad request'], 400)]);

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input())));
        Http::assertSentCount(1);
    }

    public function test_the_daily_call_limit_is_enforced_before_sending(): void
    {
        $this->enableOpenAi(callsPerDay: 1);
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);
        $provider = app(OpenAiPlanAnalysisProvider::class);

        $provider->analyze($this->input());

        $this->assertSame('budget_exhausted', $this->failure(fn () => $provider->analyze($this->input())));
        Http::assertSentCount(1);
    }

    public function test_an_image_over_the_limit_is_refused_before_sending(): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.budget.max_image_bytes' => 10]);
        Http::fake();

        $this->assertSame('image_too_large', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(str_repeat('x', 11)))));
        Http::assertNothingSent();
    }

    public function test_the_credential_source_is_separate_from_the_adapter(): void
    {
        $this->assertInstanceOf(OpenAiApiKeyCredentials::class, app(OpenAiCredentialSource::class));

        // Another source (e.g. a future ChatGPT plan token) plugs in without touching the adapter.
        $this->enableOpenAi();
        $this->app->bind(OpenAiCredentialSource::class, fn () => new class implements OpenAiCredentialSource
        {
            public function id(): string
            {
                return 'other';
            }

            public function bearerToken(): ?string
            {
                return 'token-from-another-source';
            }
        });
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input());

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-from-another-source'));
    }

    public function test_the_synthetic_provider_answers_from_its_example_with_the_input_texts(): void
    {
        $result = app(SyntheticPlanAnalysisProvider::class)->analyze($this->input());

        $this->assertSame('synthetic', $result->provider);
        $roles = array_column($result->proposal['groupRoles'], 'role', 'groupId');
        $this->assertSame([
            'style:f:#000000' => 'wall',
            'style:s:#0066cc:0.5' => 'window',
            'style:s:#cc4c00:0.6' => 'door',
            'style:s:#663399:0.4' => 'stair',
            'style:s:#008080:0.7' => 'lift',
            'style:s:#990033:0.35' => 'void',
            'style:s:#80591a:0.3' => 'hidden',
        ], $roles);
        // Only rooms whose text is on the page, placed at that text; no invented name.
        $this->assertSame(
            [['name' => 'Woonkamer', 'textId' => 't0', 'x' => 0.19, 'y' => 0.34], ['name' => 'WC', 'textId' => 't2', 'x' => 0.25, 'y' => 0.48]],
            array_map(fn (array $r): array => array_intersect_key($r, array_flip(['name', 'textId', 'x', 'y'])), $result->proposal['rooms']),
        );
        $this->assertSame([0.8, 0.7], [$result->proposal['uncertain'][0]['x'], $result->proposal['uncertain'][0]['y']]);
    }

    public function test_the_synthetic_provider_has_no_answer_for_an_unknown_page(): void
    {
        $input = new PlanAnalysisInput(1, 595.0, 842.0, 'png', [['id' => 'layer:X', 'kind' => 'layer', 'name' => 'X', 'paths' => 1, 'strokeWidth' => null, 'strokeColor' => null, 'closedPct' => 0, 'curvedPct' => 0]], []);

        $result = app(SyntheticPlanAnalysisProvider::class)->analyze($input);

        $this->assertSame([], $result->proposal['groupRoles']);
        $this->assertSame([], $result->proposal['rooms']);
        $this->assertSame('No synthetic example matches this page.', $result->proposal['uncertain'][0]['reason']);
    }

    public function test_the_synthetic_provider_never_runs_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame('unavailable', $this->failure(fn () => app(SyntheticPlanAnalysisProvider::class)->analyze($this->input())));
    }
}
