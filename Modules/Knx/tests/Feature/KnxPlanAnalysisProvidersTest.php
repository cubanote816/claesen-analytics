<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Organization;
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Services\AiSpend\AiSpendEstimator;
use Modules\Knx\Services\AiSpend\AiSpendLedger;
use Modules\Knx\Services\PlanAnalysis\ChatGptPlanAnalysisProvider;
use Modules\Knx\Services\PlanAnalysis\OpenAiApiKeyCredentials;
use Modules\Knx\Services\PlanAnalysis\OpenAiCredentialSource;
use Modules\Knx\Services\PlanAnalysis\OpenAiPlanAnalysisProvider;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisFailed;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisInput;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisProviders;
use Modules\Knx\Services\PlanAnalysis\SyntheticPlanAnalysisProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The plan-analysis providers (KNX-48, plano IA) WITHOUT any real call: every HTTP
 * request is faked or forbidden. Pins that the OpenAI adapter ships switched off,
 * follows the documented Responses API request, respects the backend budget, and that
 * the synthetic provider answers only from synthetic examples.
 */
final class KnxPlanAnalysisProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        Organization::factory()->create(['slug' => 'electro-bertels']);
    }

    public function test_an_exception_after_the_request_was_sent_leaves_the_reservation_uncertain(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);
        // The database fails while settling: OpenAI already answered, so the reservation must
        // keep counting (uncertain), never stay stranded as `reserved` nor be released.
        $this->app->singleton(AiSpendLedger::class, fn ($app) => new class($app->make(AiSpendEstimator::class)) extends AiSpendLedger
        {
            public function settle(KnxAiSpendEntry $entry, array $responseUsage, ?string $requestId): KnxAiSpendEntry
            {
                throw new \RuntimeException('database unavailable');
            }
        });

        try {
            app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);
            $this->fail('Expected the settlement failure to surface.');
        } catch (\Throwable) {
        }

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame('uncertain', $entry->status);
        $this->assertSame('settle_failed', $entry->reason);
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

    /**
     * The module config as shipped: evaluated with every KNX_PLAN_ANALYSIS_* / KNX_AI_* variable
     * removed from the environment, then restored.
     *
     * @return array<string, mixed>
     */
    private function shippedConfig(): array
    {
        $saved = [];
        foreach (array_keys(getenv() + $_ENV + $_SERVER) as $name) {
            if (is_string($name) && preg_match('/^KNX_(PLAN_ANALYSIS|AI)_/', $name) === 1) {
                $saved[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            }
        }
        try {
            return require base_path('Modules/Knx/config/config.php');
        } finally {
            foreach ($saved as $name => [$env, $envConst, $server]) {
                if ($env !== false) {
                    putenv("{$name}={$env}");
                }
                if ($envConst !== null) {
                    $_ENV[$name] = $envConst;
                }
                if ($server !== null) {
                    $_SERVER[$name] = $server;
                }
            }
        }
    }

    private function enableOpenAi(float $dailyLimitUsd = 1.0, int $retries = 0): void
    {
        config([
            'knx.ai_spend.daily_limit_usd' => $dailyLimitUsd,
            'knx.ai_spend.max_calls_per_processing' => 2,
            'knx.ai_spend.max_cost_per_processing_usd' => 0.50,
            'knx.ai_spend.data_residency_multiplier' => 1.0,
            'knx.ai_spend.models' => ['test-model' => ['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00, 'image_input_tokens_max' => 30000]],
            'knx.plan_analysis.providers.openai.retention' => [
                'policy' => 'zero_data_retention',
                'verified_at' => '2026-11-01',
                'models' => 'test-model',
                'features' => 'responses,image_input',
            ],
            'knx.plan_analysis.providers.openai.enabled' => true,
            'knx.plan_analysis.providers.openai.model' => 'test-model',
            'knx.plan_analysis.providers.openai.api_key' => 'sk-test-only',
            'knx.plan_analysis.providers.openai.base_url' => 'https://openai.test/v1',
            // Explicit: never inherit the developer's .env (e.g. a configured effort).
            'knx.plan_analysis.providers.openai.reasoning_effort' => null,
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
        // The SHIPPED defaults, whatever the developer's .env says.
        $shipped = $this->shippedConfig();
        config([
            'knx.plan_analysis' => $shipped['plan_analysis'],
            'knx.ai_spend' => $shipped['ai_spend'],
        ]);

        $reason = $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true));

        $this->assertSame('unavailable', $reason);
        $this->assertFalse($shipped['plan_analysis']['providers']['openai']['enabled']);
        $this->assertNull($shipped['plan_analysis']['providers']['openai']['model']);
        $this->assertNull($shipped['plan_analysis']['providers']['openai']['api_key']);
        $this->assertSame(0, $shipped['plan_analysis']['budget']['max_calls_per_day']);
        $this->assertSame(0.0, (float) $shipped['ai_spend']['daily_limit_usd']);
        Http::assertNothingSent();
    }

    public function test_enabled_without_budget_sends_nothing(): void
    {
        Http::fake();
        $this->enableOpenAi(dailyLimitUsd: 0);

        $this->assertSame('daily_budget_exhausted', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertNothingSent();
    }

    public function test_it_sends_the_documented_responses_request_and_reads_the_structured_answer(): void
    {
        $this->enableOpenAi();
        $proposal = ['groupRoles' => [['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'fills']], 'rooms' => [], 'uncertain' => []];
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed($proposal))]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input("\x89PNG-bytes"), fn (): bool => true);

        $this->assertSame('openai', $result->provider);
        $this->assertSame('test-model-2026', $result->model);
        $this->assertSame($proposal, $result->proposal);
        $this->assertSame(['inputTokens' => 1500, 'cachedTokens' => null, 'outputTokens' => 200, 'costUsd' => 0.0025], $result->usage);

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
                && $body['text']['format']['schema']['required'] === ['planKind', 'groupRoles', 'symbols', 'legend', 'rooms', 'uncertain']
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

    public function test_without_a_configured_reasoning_effort_the_request_leaves_it_to_the_model(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        Http::assertSent(fn (Request $request): bool => ! array_key_exists('reasoning', $request->data()));
    }

    public function test_a_configured_reasoning_effort_is_sent(): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.providers.openai.reasoning_effort' => 'low']);
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        Http::assertSent(fn (Request $request): bool => ($request->data()['reasoning'] ?? null) === ['effort' => 'low']);
    }

    public function test_an_unknown_reasoning_effort_sends_nothing(): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.providers.openai.reasoning_effort' => 'lowest']);
        Http::fake();

        $this->assertSame('unavailable', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertNothingSent();
    }

    public function test_a_refusal_an_incomplete_answer_and_non_json_are_failures(): void
    {
        $this->enableOpenAi();
        Http::fakeSequence('https://openai.test/v1/responses')
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]])
            ->push(['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => []])
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'not json']]]]]);
        $provider = app(OpenAiPlanAnalysisProvider::class);

        $this->assertSame('refused', $this->failure(fn () => $provider->analyze($this->input(), fn (): bool => true)));
        $this->assertSame('incomplete', $this->failure(fn () => $provider->analyze($this->input(), fn (): bool => true)));
        $this->assertSame('invalid_output', $this->failure(fn () => $provider->analyze($this->input(), fn (): bool => true)));
    }

    public function test_without_retries_a_server_error_fails_after_one_request(): void
    {
        $this->enableOpenAi(retries: 0);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'boom'], 500)]);

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertSentCount(1);
    }

    public function test_a_server_error_is_retried_within_the_budget(): void
    {
        $this->enableOpenAi(retries: 1);
        Http::fake(['https://openai.test/v1/responses' => Http::sequence()
            ->push(['error' => 'busy'], 503)
            ->push($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        $this->assertSame([], $result->proposal['rooms']);
        Http::assertSentCount(2);
    }

    public function test_retries_cannot_exceed_the_remaining_daily_budget(): void
    {
        $this->enableOpenAi(retries: 1);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'busy'], 503)]);
        $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)); // room for both attempts
        $reserved = (float) KnxAiSpendEntry::query()->firstOrFail()->reserved_usd;
        Http::assertSentCount(2);

        // A budget that only fits ONE reservation: the 503 stays reserved (uncertain), so the retry is refused.
        KnxAiSpendEntry::query()->delete();
        config(['knx.ai_spend.daily_limit_usd' => $reserved * 1.5]);

        $this->assertSame('daily_budget_exhausted', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertSentCount(3);
        $this->assertSame(['uncertain'], KnxAiSpendEntry::query()->pluck('status')->all());
    }

    public function test_retries_never_exceed_the_calls_one_processing_may_make(): void
    {
        $this->enableOpenAi(retries: 9);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'busy'], 503)]);

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));

        // max_calls_per_processing = 2, whatever max_retries says.
        Http::assertSentCount(2);
        $this->assertSame(2, KnxAiSpendEntry::query()->where('status', 'uncertain')->count());
    }

    public function test_a_connection_error_is_never_retried(): void
    {
        // A timeout may hide a request OpenAI already processed (and billed).
        $this->enableOpenAi(retries: 2);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('timeout');
        });

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        $this->assertSame(1, $attempts);
    }

    public function test_nothing_is_sent_once_the_document_may_no_longer_be_sent(): void
    {
        $this->enableOpenAi(retries: 2);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'busy'], 503)]);

        // Not allowed from the start: no request at all.
        $this->assertSame('not_authorized', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => false)));
        Http::assertNothingSent();

        // Revoked after the first attempt: the retry is not sent.
        $checks = 0;
        $this->assertSame('not_authorized', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze(
            $this->input(),
            function () use (&$checks): bool {
                return ++$checks === 1;
            },
        )));
        Http::assertSentCount(1);
    }

    public function test_another_credential_source_needs_no_api_key(): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.providers.openai.api_key' => null]);
        $this->app->bind(OpenAiCredentialSource::class, fn () => new class implements OpenAiCredentialSource
        {
            public function id(): string
            {
                return 'plan-token';
            }

            public function bearerToken(): ?string
            {
                return 'token-without-api-key';
            }
        });
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        $this->assertTrue(app(PlanAnalysisProviders::class)->isAvailable('openai'));
        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-without-api-key'));
    }

    public function test_a_client_error_is_never_retried(): void
    {
        $this->enableOpenAi(retries: 3);
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'bad request'], 400)]);

        $this->assertSame('provider_error', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertSentCount(1);
    }

    public function test_the_daily_usd_limit_is_enforced_before_sending(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);
        $provider = app(OpenAiPlanAnalysisProvider::class);
        $provider->analyze($this->input(), fn (): bool => true);
        $settled = (float) KnxAiSpendEntry::query()->sole()->actual_usd;
        $reserved = (float) KnxAiSpendEntry::query()->sole()->reserved_usd;

        // Not enough left for another reservation: refused with the next Brussels midnight, nothing sent.
        config(['knx.ai_spend.daily_limit_usd' => $settled + $reserved - 0.000001]);
        try {
            $provider->analyze($this->input(), fn (): bool => true);
            $this->fail('Expected PlanAnalysisFailed.');
        } catch (PlanAnalysisFailed $e) {
            $this->assertSame('daily_budget_exhausted', $e->reason);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T00:00:00[+-]\d{2}:\d{2}$/', (string) $e->resumesAt);
        }
        Http::assertSentCount(1);
        $this->assertSame(1, KnxAiSpendEntry::query()->count());
    }

    public function test_success_settles_the_reservation_with_the_actual_cost_from_usage(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response(
            array_replace($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]), [
                'usage' => ['input_tokens' => 10000, 'input_tokens_details' => ['cached_tokens' => 4000], 'output_tokens' => 2000],
            ]),
            200,
            ['x-request-id' => 'req_abc'],
        )]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame('settled', $entry->status);
        $this->assertSame(0.0162, (float) $entry->actual_usd);
        $this->assertSame('req_abc', $entry->request_id);
        $this->assertSame('openai', $entry->provider);
        $this->assertSame('plan_analysis', $entry->operation);
        $this->assertSame('test-model', $entry->model);
        $this->assertSame(['inputTokens' => 10000, 'cachedTokens' => 4000, 'outputTokens' => 2000, 'costUsd' => 0.0162], $result->usage);
        // The reservation was a real upper bound: the image alone is reserved at 30000 tokens.
        $this->assertGreaterThan(0.03, (float) $entry->reserved_usd);
    }

    public function test_the_request_id_falls_back_to_the_response_id(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))]);

        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        $this->assertSame('resp_test', KnxAiSpendEntry::query()->sole()->request_id);
    }

    public function test_a_refusal_that_carries_usage_is_still_settled(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 10],
        ])]);

        $this->assertSame('refused', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame('settled', $entry->status);
        $this->assertSame(0.00105, (float) $entry->actual_usd);
    }

    public function test_a_server_error_keeps_the_reservation_as_uncertain_and_never_releases_it(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'boom'], 500, ['x-request-id' => 'req_500'])]);

        $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true));

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame('uncertain', $entry->status);
        $this->assertSame('http_500', $entry->reason);
        $this->assertSame('req_500', $entry->request_id);
        $this->assertNull($entry->actual_usd);
        $this->assertGreaterThan(0, (float) $entry->reserved_usd);
    }

    public function test_a_client_error_after_sending_is_uncertain_too(): void
    {
        $this->enableOpenAi();
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'bad request'], 400)]);

        $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true));

        $this->assertSame(['uncertain', 'http_400'], [KnxAiSpendEntry::query()->sole()->status, KnxAiSpendEntry::query()->sole()->reason]);
    }

    public function test_a_connection_error_keeps_the_reservation_as_uncertain(): void
    {
        $this->enableOpenAi();
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true));

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame(['uncertain', 'connection_error'], [$entry->status, $entry->reason]);
    }

    public function test_missing_usage_keeps_the_reservation_as_uncertain_and_the_cost_unknown(): void
    {
        $this->enableOpenAi();
        $answer = $this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]);
        unset($answer['usage']);
        Http::fake(['https://openai.test/v1/responses' => Http::response($answer)]);

        $result = app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame(['uncertain', 'cost_unknown'], [$entry->status, $entry->reason]);
        $this->assertNull($entry->actual_usd);
        $this->assertNull($result->usage['costUsd']);
        $this->assertNull($result->usage['inputTokens']);
    }

    public function test_a_model_without_a_price_sends_nothing(): void
    {
        $this->enableOpenAi();
        config(['knx.ai_spend.models' => []]);
        Http::fake();

        $this->assertSame('pricing_unknown', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        Http::assertNothingSent();
        $this->assertSame(0, KnxAiSpendEntry::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function unverifiedRetention(): array
    {
        $ok = ['policy' => 'zero_data_retention', 'verified_at' => '2026-11-01', 'models' => 'test-model', 'features' => 'responses,image_input'];

        return [
            'nothing set' => [['policy' => null, 'verified_at' => null, 'models' => null, 'features' => null]],
            'no policy' => [array_replace($ok, ['policy' => null])],
            'not verified' => [array_replace($ok, ['verified_at' => null])],
            'model not approved' => [array_replace($ok, ['models' => 'another-model'])],
            'image input not approved' => [array_replace($ok, ['features' => 'responses'])],
            'responses not approved' => [array_replace($ok, ['features' => 'image_input'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $retention
     */
    #[DataProvider('unverifiedRetention')]
    public function test_without_a_verified_retention_policy_nothing_is_sent(array $retention): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.providers.openai.retention' => $retention]);
        Http::fake();

        $this->assertSame('retention_not_verified', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
        $this->assertFalse(app(PlanAnalysisProviders::class)->isAvailable('openai'));
        $this->assertSame('retention_not_verified', app(PlanAnalysisProviders::class)->unavailableReason('openai'));
        Http::assertNothingSent();
        $this->assertSame(0, KnxAiSpendEntry::query()->count());
    }

    public function test_a_verified_retention_policy_makes_openai_available(): void
    {
        $this->enableOpenAi();

        $this->assertTrue(app(PlanAnalysisProviders::class)->isAvailable('openai'));
        $this->assertNull(app(PlanAnalysisProviders::class)->unavailableReason('openai'));
    }

    public function test_nothing_the_plan_contains_reaches_the_logs(): void
    {
        $this->enableOpenAi();
        $logged = [];
        $collect = function (mixed ...$args) use (&$logged): void {
            $logged[] = json_encode($args);
        };
        Log::shouldReceive('info', 'warning', 'error', 'debug', 'notice')->andReturnUsing($collect);
        Http::fake(['https://openai.test/v1/responses' => Http::sequence()
            ->push($this->completed(['groupRoles' => [], 'rooms' => [], 'uncertain' => []]))
            ->push(['error' => 'boom'], 500)]);
        $provider = app(OpenAiPlanAnalysisProvider::class);

        $provider->analyze($this->input(), fn (): bool => true);
        $this->failure(fn () => $provider->analyze($this->input(), fn (): bool => true));

        $this->assertNotEmpty($logged);
        $all = implode("\n", $logged);
        foreach (['Woonkamer', 'Carport', '90,5', 'style:f:#000000', 'PNG', base64_encode("\x89PNG-synthetic")] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
    }

    public function test_an_image_over_the_limit_is_refused_before_sending(): void
    {
        $this->enableOpenAi();
        config(['knx.plan_analysis.budget.max_image_bytes' => 10]);
        Http::fake();

        $this->assertSame('image_too_large', $this->failure(fn () => app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(str_repeat('x', 11)), fn (): bool => true)));
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

        app(OpenAiPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer token-from-another-source'));
    }

    public function test_the_synthetic_provider_answers_from_its_example_with_the_input_texts(): void
    {
        $result = app(SyntheticPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true);

        $this->assertSame('synthetic', $result->provider);
        $roles = array_column($result->proposal['groupRoles'], 'role', 'groupId');
        $this->assertSame([
            'style:f:#000000' => 'wall',
            'style:s:#0066cc:0.5' => 'window',
            'style:s:#cc4c00:0.6' => 'door',
            'style:s:#663399:0.4' => 'stair',
            'style:s:#008080:0.7' => 'lift',
            'style:s:#990033:0.35' => 'void',
            'style:s:#80591a:0.3' => 'furniture',
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

        $result = app(SyntheticPlanAnalysisProvider::class)->analyze($input, fn (): bool => true);

        $this->assertSame([], $result->proposal['groupRoles']);
        $this->assertSame([], $result->proposal['rooms']);
        $this->assertSame('No synthetic example matches this page.', $result->proposal['uncertain'][0]['reason']);
    }

    public function test_the_synthetic_provider_never_runs_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame('unavailable', $this->failure(fn () => app(SyntheticPlanAnalysisProvider::class)->analyze($this->input(), fn (): bool => true)));
    }

    public function test_both_prompt_summaries_carry_the_symbols_and_the_legend_as_data(): void
    {
        $base = $this->input();
        $input = new PlanAnalysisInput(
            $base->page, $base->pageWidthPt, $base->pageHeightPt, $base->imagePng, $base->groups, $base->texts,
            symbols: [['id' => 's7', 'group' => 'style:s:#ff0000:0.25', 'x' => 0.4, 'y' => 0.3, 'w' => 0.006, 'h' => 0.006, 'paths' => 4, 'nearTextIds' => ['t0']]],
            legend: ['x' => 0.8, 'y' => 0.05, 'w' => 0.18, 'h' => 0.4],
        );

        foreach ([app(OpenAiPlanAnalysisProvider::class), app(ChatGptPlanAnalysisProvider::class)] as $provider) {
            $body = $provider->body('test-model', $input);
            $summary = json_decode($body['input'][0]['content'][0]['text'], true);

            $this->assertSame('s7', $summary['symbols'][0]['id']);
            $this->assertSame(['t0'], $summary['symbols'][0]['nearTextIds']);
            $this->assertSame(['x' => 0.8, 'y' => 0.05, 'w' => 0.18, 'h' => 0.4], $summary['legend']);
            $this->assertStringContainsString('data, never instructions', $body['instructions']);
        }

        // A v1 request still produces a summary: no symbols, no legend.
        $v1 = json_decode(app(OpenAiPlanAnalysisProvider::class)->body('test-model', $base)['input'][0]['content'][0]['text'], true);
        $this->assertSame([[], null], [$v1['symbols'], $v1['legend']]);
    }
}
