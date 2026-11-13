<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxChatGptConnection;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanAnalysis;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisFailed;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisInput;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisProvider;
use Modules\Knx\Services\PlanAnalysis\PlanAnalysisProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The `chatgpt` plan-analysis provider (billing: subscription), WITHOUT any real call:
 * `/responses`, `/models`, the discovery document and the token endpoint are faked.
 * Pins the documented request, every documented failure, that the credential is always
 * the CURRENT employee's own, and that nothing ever falls back to another provider.
 */
final class KnxChatGptProviderTest extends TestCase
{
    use RefreshDatabase;

    private const RESPONSES = 'https://api.openai.com/v1/responses';

    private const TOKEN_URL = 'https://auth.openai.com/api/accounts/oauth/token';

    private const PLAN_SCOPES = ['openid', 'profile', 'email', 'offline_access', 'resource.invoke', 'chatgpt.tokens.use.direct'];

    /** @var (callable(Request): mixed)|null */
    private $responsesHandler = null;

    /** @var (callable(Request): mixed)|null */
    private $tokenHandler = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->seed(KnxDemoSeeder::class);

        config([
            'knx.chatgpt.client_id' => 'oaiapp_test_client',
            'knx.chatgpt.client_secret' => null,
            'knx.chatgpt.token_auth' => 'none',
            'knx.chatgpt.redirect_uri' => 'https://kantoor.example.test/auth/chatgpt/callback',
            'knx.plan_analysis.providers.chatgpt.model' => 'gpt-test',
            'knx.plan_analysis.providers.chatgpt.max_calls_per_day' => 20,
        ]);

        $this->fakeHttp();
        $this->connect('lien.smet@electrobertels.be', 'access-lien');
        $this->actAs('lien.smet@electrobertels.be');
    }

    // ---- request shape --------------------------------------------------------------

    public function test_the_request_follows_the_documented_shape_and_is_sent_once(): void
    {
        $this->responsesHandler = fn () => $this->stream($this->completedEvents($this->proposal()));

        $result = $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true);

        $requests = $this->sent(self::RESPONSES);
        $this->assertCount(1, $requests);
        Http::assertSentCount(1);

        $request = $requests[0];
        $this->assertSame('POST', $request->method());
        $this->assertSame('Bearer access-lien', $request->header('Authorization')[0]);
        $body = $request->data();
        $this->assertSame('gpt-test', $body['model']);
        $this->assertFalse($body['store']);
        $this->assertTrue($body['stream']);
        foreach (['max_output_tokens', 'temperature', 'top_p', 'metadata', 'truncation', 'user', 'prompt', 'background',
            'conversation', 'previous_response_id', 'tools'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $body);
        }
        $this->assertIsString($body['instructions']);
        $this->assertCount(1, $body['input']);
        $this->assertSame('user', $body['input'][0]['role']);
        $this->assertSame(['input_text', 'input_image'], array_column($body['input'][0]['content'], 'type'));
        $this->assertStringStartsWith('data:image/png;base64,', $body['input'][0]['content'][1]['image_url']);
        $this->assertSame('json_schema', $body['text']['format']['type']);
        $this->assertTrue($body['text']['format']['strict']);
        $this->assertSame('plan_analysis', $body['text']['format']['name']);

        $this->assertSame('chatgpt', $result->provider);
        $this->assertSame('gpt-test-2026', $result->model);
        $this->assertSame('Woonkamer', $result->proposal['rooms'][0]['name']);
    }

    public function test_the_output_text_can_come_from_the_accumulated_deltas(): void
    {
        $json = json_encode($this->proposal());
        $this->responsesHandler = fn () => $this->stream([
            ['response.output_text.delta', ['type' => 'response.output_text.delta', 'delta' => substr($json, 0, 20)]],
            ['response.output_text.delta', ['type' => 'response.output_text.delta', 'delta' => substr($json, 20)]],
            ['response.completed', ['type' => 'response.completed', 'response' => ['model' => 'gpt-test', 'output' => []]]],
        ]);

        $result = $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true);

        $this->assertSame($this->proposal(), $result->proposal);
    }

    public function test_an_expired_token_is_refreshed_and_the_new_one_is_sent(): void
    {
        KnxChatGptConnection::query()->update(['access_expires_at' => now()->subMinute()]);
        $this->tokenHandler = fn () => Http::response([
            'access_token' => 'access-lien-2', 'refresh_token' => 'refresh-lien-2', 'expires_in' => 3600,
            'scope' => implode(' ', self::PLAN_SCOPES),
        ]);
        $this->responsesHandler = fn () => $this->stream($this->completedEvents($this->proposal()));

        $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true);

        $this->assertSame('Bearer access-lien-2', $this->sent(self::RESPONSES)[0]->header('Authorization')[0]);
        $this->assertSame('access-lien-2', KnxChatGptConnection::query()->sole()->access_token);
    }

    public function test_a_terminal_refresh_failure_needs_a_new_sign_in_and_sends_nothing_to_responses(): void
    {
        KnxChatGptConnection::query()->update(['access_expires_at' => now()->subMinute()]);
        $this->tokenHandler = fn () => Http::response(['error' => 'invalid_grant'], 400);

        $reason = $this->failure(fn () => $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true));

        $this->assertSame('needs_sign_in', $reason);
        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_the_authorization_is_asked_before_the_attempt_and_a_no_sends_nothing_and_spends_nothing(): void
    {
        $asked = 0;
        $reason = $this->failure(function () use (&$asked): void {
            $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), function () use (&$asked): bool {
                $asked++;

                return false;
            });
        });

        $this->assertSame('not_authorized', $reason);
        $this->assertSame(1, $asked);
        Http::assertNothingSent();
        $this->assertNull(Cache::get('knx:plan-analysis:calls:chatgpt:'.now()->toDateString()));
    }

    // ---- failures -------------------------------------------------------------------

    public function test_a_usage_limit_after_the_stream_started_is_a_failure_with_its_code(): void
    {
        $this->responsesHandler = fn () => $this->stream([
            ['response.created', ['type' => 'response.created', 'response' => ['status' => 'in_progress']]],
            ['response.failed', ['type' => 'response.failed', 'response' => ['status' => 'failed', 'error' => [
                'code' => 'subscription_sharing_usage_limit_exceeded', 'message' => 'Usage limit reached',
            ]]]],
        ], ['x-request-id' => 'req_stream']);

        $e = $this->failed(fn () => $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true));

        $this->assertSame('plan_usage_limit', $e->reason);
        $this->assertSame('subscription_sharing_usage_limit_exceeded', $e->providerCode);
        $this->assertSame('req_stream', $e->requestId);
        $this->assertStringContainsString('https://chatgpt.com/settings/usage', $e->getMessage());
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    /** @return array<string, array{int, array<string, mixed>, string, ?string, ?string}> */
    public static function preStreamErrors(): array
    {
        return [
            'detail 401' => [401, ['detail' => 'not accepted'], 'plan_not_accepted', null, null],
            'detail 403' => [403, ['detail' => 'region'], 'plan_policy', null, null],
            'detail 503' => [503, ['detail' => 'not enabled'], 'plan_unavailable', null, null],
            'not eligible' => [403, ['error' => ['code' => 'subscription_sharing_user_not_eligible', 'message' => 'x']], 'plan_not_eligible', 'subscription_sharing_user_not_eligible', null],
            'usage limit' => [429, ['error' => ['code' => 'subscription_sharing_usage_limit_exceeded', 'message' => 'x']], 'plan_usage_limit', 'subscription_sharing_usage_limit_exceeded', null],
            'usage unavailable' => [503, ['error' => ['code' => 'subscription_sharing_usage_unavailable', 'message' => 'x']], 'plan_unavailable', 'subscription_sharing_usage_unavailable', null],
            'unsupported capability' => [400, ['error' => ['code' => 'subscription_sharing_unsupported_capability', 'message' => 'x', 'param' => 'input_image']], 'plan_unsupported_capability', 'subscription_sharing_unsupported_capability', 'input_image'],
            'route not supported' => [403, ['error' => ['code' => 'subscription_sharing_route_not_supported', 'message' => 'x']], 'plan_policy', 'subscription_sharing_route_not_supported', null],
            'invalid user' => [401, ['error' => ['code' => 'subscription_sharing_invalid_user', 'message' => 'x']], 'plan_not_accepted', 'subscription_sharing_invalid_user', null],
            'scope not authorized' => [403, ['error' => ['code' => 'chatpass_v2_scope_not_authorized', 'message' => 'x']], 'plan_not_accepted', 'chatpass_v2_scope_not_authorized', null],
            'invalid authorization context' => [403, ['error' => ['code' => 'chatpass_v2_invalid_authorization_context', 'message' => 'x']], 'plan_not_accepted', 'chatpass_v2_invalid_authorization_context', null],
            'user unavailable' => [503, ['error' => ['code' => 'subscription_sharing_user_unavailable', 'message' => 'x']], 'plan_unavailable', 'subscription_sharing_user_unavailable', null],
            'unknown error' => [500, ['error' => ['code' => 'boom', 'message' => 'x']], 'provider_error', 'boom', null],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('preStreamErrors')]
    public function test_each_pre_stream_error_is_mapped_keeps_status_code_param_and_request_id_and_is_not_retried(int $status, array $body, string $reason, ?string $code, ?string $param): void
    {
        config(['knx.plan_analysis.budget.max_retries' => 3]);
        $this->responsesHandler = fn () => Http::response($body, $status, ['x-request-id' => 'req_123']);

        $e = $this->failed(fn () => $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true));

        $this->assertSame($reason, $e->reason);
        $this->assertSame($status, $e->providerStatus);
        $this->assertSame($code, $e->providerCode);
        $this->assertSame($param, $e->providerParam);
        $this->assertSame('req_123', $e->requestId);
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    public function test_a_stream_that_ends_without_completed_or_with_incomplete_or_garbage_fails(): void
    {
        $cases = [
            'no completed' => [[['response.output_text.delta', ['type' => 'response.output_text.delta', 'delta' => '{"a"']]], 'incomplete'],
            'incomplete' => [[['response.incomplete', ['type' => 'response.incomplete', 'response' => ['incomplete_details' => ['reason' => 'max_output_tokens']]]]], 'incomplete'],
            'failed' => [[['response.failed', ['type' => 'response.failed', 'response' => ['error' => ['code' => 'server_error', 'message' => 'x']]]]], 'provider_error'],
            'not json' => [[['response.completed', ['type' => 'response.completed', 'response' => ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'hello']]]]]]]], 'invalid_output'],
            'refusal' => [[['response.completed', ['type' => 'response.completed', 'response' => ['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]]]]], 'refused'],
        ];

        foreach ($cases as $label => [$events, $reason]) {
            $this->responsesHandler = fn () => $this->stream($events);
            $this->assertSame($reason, $this->failure(fn () => $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true)), $label);
        }
    }

    // ---- per employee ---------------------------------------------------------------

    public function test_each_employee_only_ever_uses_their_own_token(): void
    {
        $this->connect('pieter.aerts@electrobertels.be', 'access-pieter');
        $this->responsesHandler = fn () => $this->stream($this->completedEvents($this->proposal()));

        $this->provider('pieter.aerts@electrobertels.be')->analyze($this->input(), fn (): bool => true);
        $this->provider('lien.smet@electrobertels.be')->analyze($this->input(), fn (): bool => true);

        $tokens = array_map(fn (Request $r): string => $r->header('Authorization')[0], $this->sent(self::RESPONSES));
        $this->assertSame(['Bearer access-pieter', 'Bearer access-lien'], $tokens);
    }

    public function test_availability_is_per_employee_and_needs_connection_plan_permission_configuration_and_model(): void
    {
        $providers = app(PlanAnalysisProviders::class);
        $lien = $this->employee('lien.smet@electrobertels.be');
        $pieter = $this->employee('pieter.aerts@electrobertels.be');

        $this->assertTrue($providers->isAvailable('chatgpt', $lien));
        $this->assertFalse($providers->isAvailable('chatgpt', $pieter), 'no connection');
        $this->assertFalse($providers->isAvailable('chatgpt'), 'no employee');

        $this->connect('pieter.aerts@electrobertels.be', 'access-pieter', ['plan_permission' => false]);
        $this->assertFalse($providers->isAvailable('chatgpt', $pieter), 'no plan permission');

        KnxChatGptConnection::query()->where('employee_id', $lien->getKey())->update(['status' => 'needs_sign_in']);
        $this->assertFalse($providers->isAvailable('chatgpt', $lien), 'needs sign-in');
        KnxChatGptConnection::query()->where('employee_id', $lien->getKey())->update(['status' => 'connected']);

        config(['knx.plan_analysis.providers.chatgpt.model' => null]);
        $this->assertFalse($providers->isAvailable('chatgpt', $lien), 'no model');
        config(['knx.plan_analysis.providers.chatgpt.model' => 'gpt-test', 'knx.chatgpt.client_id' => null]);
        $this->assertFalse($providers->isAvailable('chatgpt', $lien), 'not configured');
    }

    // ---- through the API ------------------------------------------------------------

    public function test_an_authorized_analysis_is_stored_with_the_subscription_billing(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/chatgpt")->assertOk();
        $this->responsesHandler = fn () => $this->stream($this->completedEvents($this->proposal()));

        $post = $this->postJson("{$this->base($document)}/analyses", $this->apiBody())->assertCreated();

        $this->assertSame('chatgpt', $post->json('analysis.provider'));
        $this->assertSame('subscription', $post->json('analysis.billing'));
        $this->assertSame('gpt-test-2026', $post->json('analysis.model'));
        $this->assertSame(1, KnxPlanAnalysis::query()->count());
        $this->assertSame('subscription', $this->getJson("{$this->base($document)}/analyses?page=2")->json('analysis.billing'));
        $this->assertSame('Bearer access-lien', $this->sent(self::RESPONSES)[0]->header('Authorization')[0]);
    }

    public function test_the_authorization_entries_show_the_billing_and_the_per_employee_availability(): void
    {
        $document = $this->document();

        $entries = array_column($this->getJson("{$this->base($document)}/ai-authorizations")->json('providers'), null, 'provider');
        $this->assertSame(['synthetic', 'openai', 'chatgpt'], array_keys($entries));
        $this->assertSame('simulation', $entries['synthetic']['billing']);
        $this->assertSame('api', $entries['openai']['billing']);
        $this->assertSame('subscription', $entries['chatgpt']['billing']);
        $this->assertTrue($entries['chatgpt']['external']);
        $this->assertTrue($entries['chatgpt']['available']);
        $this->assertFalse($entries['chatgpt']['authorized']);

        $this->actAs('pieter.aerts@electrobertels.be');
        $this->assertFalse($this->getJson("{$this->base($document)}/ai-authorizations")->json('providers.2.available'));
    }

    public function test_an_unauthorized_document_is_never_sent_to_chatgpt(): void
    {
        $this->postJson("{$this->base($this->document())}/analyses", $this->apiBody())
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_analysis_not_authorized');

        Http::assertNothingSent();
    }

    public function test_an_employee_without_a_usable_connection_is_unavailable_and_never_falls_back_to_openai(): void
    {
        $document = $this->document();
        config([
            'knx.plan_analysis.providers.openai.enabled' => true,
            'knx.plan_analysis.providers.openai.model' => 'test-model',
            'knx.plan_analysis.providers.openai.api_key' => 'sk-test-only',
            'knx.plan_analysis.budget.max_calls_per_day' => 5,
        ]);
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();
        $this->putJson("{$this->base($document)}/ai-authorizations/chatgpt")->assertOk();

        foreach (['no connection', 'no plan permission'] as $label) {
            $this->actAs('pieter.aerts@electrobertels.be');
            if ($label === 'no plan permission') {
                $this->connect('pieter.aerts@electrobertels.be', 'access-pieter', ['plan_permission' => false]);
            }
            $this->postJson("{$this->base($document)}/analyses", $this->apiBody())
                ->assertStatus(409)
                ->assertJsonPath('code', 'plan_analysis_unavailable');
        }

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    /** @return array<string, array{callable(): mixed, int, string, ?string, ?string}> */
    public static function apiFailures(): array
    {
        $error = fn (int $status, string $code, ?string $param = null) => fn () => Http::response(
            ['error' => ['code' => $code, 'message' => 'x', 'param' => $param]], $status, ['x-request-id' => 'req_api'],
        );

        return [
            'usage limit' => [$error(429, 'subscription_sharing_usage_limit_exceeded'), 429, 'plan_analysis_plan_usage_limit', 'subscription_sharing_usage_limit_exceeded', null],
            'not eligible' => [$error(403, 'subscription_sharing_user_not_eligible'), 403, 'plan_analysis_plan_not_eligible', 'subscription_sharing_user_not_eligible', null],
            'policy' => [$error(403, 'subscription_sharing_route_not_supported'), 403, 'plan_analysis_plan_policy', 'subscription_sharing_route_not_supported', null],
            'not accepted' => [fn () => Http::response(['detail' => 'no'], 401, ['x-request-id' => 'req_api']), 403, 'plan_analysis_plan_not_accepted', null, null],
            'unavailable' => [fn () => Http::response(['detail' => 'off'], 503, ['x-request-id' => 'req_api']), 503, 'plan_analysis_plan_unavailable', null, null],
            'unsupported capability' => [$error(400, 'subscription_sharing_unsupported_capability', 'input_image'), 502, 'plan_analysis_plan_unsupported_capability', 'subscription_sharing_unsupported_capability', 'input_image'],
        ];
    }

    /**
     * @param  callable(): mixed  $respond
     */
    #[DataProvider('apiFailures')]
    public function test_provider_failures_become_distinct_http_errors_with_the_provider_diagnostic(callable $respond, int $status, string $code, ?string $providerCode, ?string $param): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/chatgpt")->assertOk();
        $this->responsesHandler = $respond;

        $response = $this->postJson("{$this->base($document)}/analyses", $this->apiBody())
            ->assertStatus($status)
            ->assertJsonPath('code', $code)
            ->assertJsonPath('requestId', 'req_api');

        $this->assertSame($providerCode, $response->json('providerCode'));
        $this->assertSame($param, $response->json('providerParam'));
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    public function test_needing_a_new_sign_in_is_a_409_with_its_own_code(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/chatgpt")->assertOk();
        KnxChatGptConnection::query()->update(['access_expires_at' => now()->subMinute()]);
        $this->tokenHandler = fn () => Http::response(['error' => 'invalid_grant'], 400);

        $this->postJson("{$this->base($document)}/analyses", $this->apiBody())
            ->assertStatus(409)
            ->assertJsonPath('code', 'plan_analysis_needs_sign_in');

        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_the_chatgpt_budget_is_its_own_and_runs_out(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/chatgpt")->assertOk();
        $this->responsesHandler = fn () => $this->stream($this->completedEvents($this->proposal()));
        // The global limit is 0 (nothing allowed for the API); chatgpt has its own limit.
        config(['knx.plan_analysis.budget.max_calls_per_day' => 0, 'knx.plan_analysis.providers.chatgpt.max_calls_per_day' => 2]);

        $this->postJson("{$this->base($document)}/analyses", $this->apiBody())->assertCreated();
        $this->postJson("{$this->base($document)}/analyses", $this->apiBody())->assertCreated();
        $this->postJson("{$this->base($document)}/analyses", $this->apiBody())
            ->assertStatus(429)
            ->assertJsonPath('code', 'plan_analysis_budget_exhausted');

        $this->assertCount(2, $this->sent(self::RESPONSES));
    }

    // ---- helpers --------------------------------------------------------------------

    private function actAs(string $email): void
    {
        $this->actingAs(User::query()->where('email', $email)->sole(), 'sanctum');
    }

    private function employee(string $email): KnxEmployee
    {
        return KnxEmployee::query()->where('user_id', User::query()->where('email', $email)->value('id'))->sole();
    }

    /** @param  array<string, mixed>  $overrides */
    private function connect(string $email, string $token, array $overrides = []): void
    {
        $employee = $this->employee($email);

        KnxChatGptConnection::query()->updateOrCreate(
            ['organization_id' => $employee->organization_id, 'employee_id' => $employee->getKey()],
            $overrides + [
                'subject' => 'sub-'.$email,
                'email' => $email,
                'name' => 'Test',
                'client_id' => 'oaiapp_test_client',
                'scopes' => self::PLAN_SCOPES,
                'access_token' => $token,
                'refresh_token' => 'refresh-'.$token,
                'access_expires_at' => now()->addHour(),
                'plan_permission' => true,
                'status' => 'connected',
            ],
        );
    }

    private function provider(string $email): PlanAnalysisProvider
    {
        return app(PlanAnalysisProviders::class)->provider('chatgpt', $this->employee($email));
    }

    private function failed(callable $call): PlanAnalysisFailed
    {
        try {
            $call();
        } catch (PlanAnalysisFailed $e) {
            return $e;
        }
        $this->fail('Expected PlanAnalysisFailed.');
    }

    private function failure(callable $call): string
    {
        return $this->failed($call)->reason;
    }

    /** @return list<Request> */
    private function sent(string $url): array
    {
        return array_values(Http::recorded()
            ->map(fn (array $pair): Request => $pair[0])
            ->filter(fn (Request $request): bool => $request->url() === $url)
            ->all());
    }

    private function fakeHttp(): void
    {
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                self::RESPONSES => $this->responsesHandler === null
                    ? throw new \LogicException('Unexpected /responses call')
                    : ($this->responsesHandler)($request),
                'https://auth.openai.com/.well-known/openid-configuration' => Http::response([
                    'issuer' => 'https://auth.openai.com',
                    'authorization_endpoint' => 'https://auth.openai.com/api/accounts/authorize',
                    'token_endpoint' => self::TOKEN_URL,
                    'jwks_uri' => 'https://auth.openai.com/.well-known/jwks.json',
                ]),
                self::TOKEN_URL => $this->tokenHandler === null
                    ? throw new \LogicException('Unexpected token call')
                    : ($this->tokenHandler)($request),
                default => throw new \LogicException('Unexpected request to '.$request->url()),
            };
        });
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $events
     * @param  array<string, string>  $headers
     */
    private function stream(array $events, array $headers = []): mixed
    {
        $body = '';
        foreach ($events as [$name, $data]) {
            $body .= "event: {$name}\ndata: ".json_encode($data)."\n\n";
        }

        return Http::response($body, 200, ['Content-Type' => 'text/event-stream'] + $headers);
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function completedEvents(array $proposal): array
    {
        return [
            ['response.created', ['type' => 'response.created', 'response' => ['status' => 'in_progress']]],
            ['response.completed', ['type' => 'response.completed', 'response' => [
                'status' => 'completed',
                'model' => 'gpt-test-2026',
                'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => json_encode($proposal)]]]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
            ]]],
        ];
    }

    /** @return array<string, mixed> */
    private function proposal(): array
    {
        $input = $this->diagnosticJson();

        return [
            'groupRoles' => [['groupId' => $input['groups'][0]['id'], 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'thick']],
            'rooms' => [['name' => 'Woonkamer', 'textId' => 't0', 'x' => 0.2, 'y' => 0.3, 'confidence' => 0.8, 'reason' => 'label']],
            'uncertain' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function diagnosticJson(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../resources/plan-analysis/diagnostic/plan-clean-page2.input.json'), true);
    }

    private function input(): PlanAnalysisInput
    {
        $json = $this->diagnosticJson();

        return new PlanAnalysisInput($json['page'], $json['pageWidthPt'], $json['pageHeightPt'], "\x89PNG-synthetic", $json['groups'], $json['texts']);
    }

    /** @return array<string, mixed> */
    private function apiBody(): array
    {
        $json = $this->diagnosticJson();

        return [
            'page' => $json['page'],
            'provider' => 'chatgpt',
            'image' => base64_encode("\x89PNG\r\n\x1a\nsynthetic"),
            'pageWidthPt' => $json['pageWidthPt'],
            'pageHeightPt' => $json['pageHeightPt'],
            'groups' => $json['groups'],
            'texts' => $json['texts'],
        ];
    }

    private function document(): KnxDocument
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->where('name', 'UV_C1618_Gelijkvloers_Wayfinding.pdf')
            ->sole();
    }

    private function base(KnxDocument $document): string
    {
        return "/api/v1/knx/projects/C1618/plans/{$document->getKey()}";
    }
}
