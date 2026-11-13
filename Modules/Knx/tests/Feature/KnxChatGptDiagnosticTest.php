<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxChatGptConnection;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanAnalysis;
use Tests\TestCase;

/**
 * The "Sign in with ChatGPT" test run (`POST /chatgpt/diagnostic`): ordered steps that stop
 * at the first one that is not ok, say why, skip the rest WITHOUT any later HTTP call, and
 * never touch an API key or the `openai` provider. Every HTTP call is faked.
 */
final class KnxChatGptDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/knx/chatgpt/diagnostic';

    private const MODELS = 'https://api.openai.com/v1/models';

    private const RESPONSES = 'https://api.openai.com/v1/responses';

    private const TOKEN_URL = 'https://auth.openai.com/api/accounts/oauth/token';

    private const PLAN_SCOPES = ['openid', 'profile', 'email', 'offline_access', 'resource.invoke', 'chatgpt.tokens.use.direct'];

    private const KEYS = ['registration', 'sign_in', 'plan_permission', 'models', 'synthetic_analysis'];

    /** @var (callable(Request): mixed) */
    private $modelsHandler;

    /** @var (callable(Request): mixed) */
    private $responsesHandler;

    /** @var (callable(Request): mixed) */
    private $tokenHandler;

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
            'knx.plan_analysis.providers.chatgpt.model' => null,
            'knx.plan_analysis.providers.chatgpt.max_calls_per_day' => 20,
            // An API key and the openai adapter must stay out of reach of this test.
            'knx.plan_analysis.providers.openai.enabled' => true,
            'knx.plan_analysis.providers.openai.model' => 'api-model',
            'knx.plan_analysis.providers.openai.api_key' => 'sk-must-never-be-used',
            'knx.plan_analysis.budget.max_calls_per_day' => 50,
        ]);

        $this->modelsHandler = fn () => Http::response(['models' => [
            ['slug' => 'gpt-vision', 'display_name' => 'GPT Vision', 'visibility' => 'list'],
            ['slug' => 'gpt-hidden', 'display_name' => 'Hidden', 'visibility' => 'hide'],
            ['slug' => 'gpt-text', 'display_name' => 'GPT Text', 'visibility' => 'list', 'input_modalities' => ['text']],
            ['slug' => 'gpt-multi', 'display_name' => 'GPT Multi', 'visibility' => 'list', 'input_modalities' => ['text', 'image']],
        ]]);
        $this->responsesHandler = fn () => $this->completed();
        $this->tokenHandler = fn () => Http::response(['error' => 'unexpected'], 500);
        $this->fakeHttp();

        $this->connect('lien.smet@electrobertels.be');
        $this->actAs('lien.smet@electrobertels.be');
    }

    // ---- stop points ----------------------------------------------------------------

    public function test_not_configured_stops_at_registration_with_the_missing_settings_and_calls_nothing(): void
    {
        config(['knx.chatgpt.client_id' => null]);

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('registration', $json['stoppedAt']);
        $this->assertSame(['failed', 'skipped', 'skipped', 'skipped', 'skipped'], $this->statuses($json));
        $this->assertSame('not_configured', $json['steps'][0]['reason']);
        $this->assertSame(['KNX_CHATGPT_CLIENT_ID'], $json['steps'][0]['detail']['missingSettings']);
        Http::assertNothingSent();
    }

    public function test_the_response_envelope_is_fixed(): void
    {
        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame(['provider', 'billing', 'steps', 'stoppedAt'], array_keys($json));
        $this->assertSame('chatgpt', $json['provider']);
        $this->assertSame('subscription', $json['billing']);
        $this->assertSame(self::KEYS, array_column($json['steps'], 'key'));
        foreach ($json['steps'] as $step) {
            $this->assertSame(['key', 'status', 'reason', 'detail'], array_keys($step));
        }
    }

    public function test_not_signed_in_stops_at_sign_in(): void
    {
        $this->actAs('pieter.aerts@electrobertels.be');

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('sign_in', $json['stoppedAt']);
        $this->assertSame(['ok', 'failed', 'skipped', 'skipped', 'skipped'], $this->statuses($json));
        $this->assertSame('not_signed_in', $json['steps'][1]['reason']);
        Http::assertNothingSent();
    }

    public function test_a_connection_that_needs_a_new_sign_in_stops_at_sign_in(): void
    {
        KnxChatGptConnection::query()->update(['status' => 'needs_sign_in', 'access_token' => null, 'refresh_token' => null]);

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('sign_in', $json['stoppedAt']);
        $this->assertSame('needs_sign_in', $json['steps'][1]['reason']);
        $this->assertSame('lien@example.test', $json['steps'][1]['detail']['identity']['email']);
        Http::assertNothingSent();
    }

    public function test_signed_in_without_plan_permission_stops_at_plan_permission(): void
    {
        KnxChatGptConnection::query()->update(['plan_permission' => false, 'scopes' => ['openid', 'email']]);

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('plan_permission', $json['stoppedAt']);
        $this->assertSame(['ok', 'ok', 'failed', 'skipped', 'skipped'], $this->statuses($json));
        $this->assertSame('plan_permission_missing', $json['steps'][2]['reason']);
        // The sign-in step still reports who signed in.
        $this->assertSame('Lien Smet', $json['steps'][1]['detail']['identity']['name']);
        Http::assertNothingSent();
    }

    public function test_an_expired_token_is_refreshed_and_a_failed_refresh_stops_at_plan_permission(): void
    {
        KnxChatGptConnection::query()->update(['access_expires_at' => now()->subMinute()]);
        $this->tokenHandler = fn () => Http::response(['error' => 'invalid_grant'], 400);

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('plan_permission', $json['stoppedAt']);
        $this->assertSame('needs_sign_in', $json['steps'][2]['reason']);
        $this->assertSame([], $this->sent(self::MODELS));

        // A temporary refresh failure keeps the connection and says so. Restore a usable
        // connection through the model (the token columns are encrypted casts).
        KnxChatGptConnection::query()->sole()->forceFill([
            'access_expires_at' => now()->subMinute(),
            'status' => 'connected',
            'plan_permission' => true,
            'scopes' => ['openid', 'profile', 'email', 'offline_access', 'resource.invoke', 'chatgpt.tokens.use.direct'],
            'access_token' => 'a',
            'refresh_token' => 'r',
        ])->save();
        $this->tokenHandler = fn () => Http::response(['error' => 'server_error'], 500);
        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->json();
        $this->assertSame('refresh_failed', $json['steps'][2]['reason']);
    }

    public function test_a_models_error_keeps_the_provider_status_and_code_and_skips_the_analysis(): void
    {
        $this->modelsHandler = fn () => Http::response(
            ['error' => ['code' => 'subscription_sharing_user_not_eligible', 'message' => 'no']], 403, ['x-request-id' => 'req_models'],
        );

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('models', $json['stoppedAt']);
        $this->assertSame(['ok', 'ok', 'ok', 'failed', 'skipped'], $this->statuses($json));
        $this->assertSame('plan_not_eligible', $json['steps'][3]['reason']);
        $this->assertSame(
            ['status' => 403, 'code' => 'subscription_sharing_user_not_eligible', 'param' => null, 'requestId' => 'req_models'],
            $json['steps'][3]['detail'],
        );
        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_without_a_model_it_lists_them_and_asks_for_one_without_analysing(): void
    {
        $json = $this->runDiagnostic()->assertOk()->json();

        $this->assertSame('models', $json['stoppedAt']);
        $this->assertSame(['ok', 'ok', 'ok', 'needs_input', 'skipped'], $this->statuses($json));
        $this->assertSame('model_required', $json['steps'][3]['reason']);
        $this->assertSame(
            [
                ['slug' => 'gpt-vision', 'displayName' => 'GPT Vision', 'imageInput' => 'unknown'],
                ['slug' => 'gpt-text', 'displayName' => 'GPT Text', 'imageInput' => 'no'],
                ['slug' => 'gpt-multi', 'displayName' => 'GPT Multi', 'imageInput' => 'yes'],
            ],
            $json['steps'][3]['detail']['models'],
        );
        $this->assertSame('unsupported', $json['steps'][3]['detail']['imageGeneration']);
        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_a_model_that_is_not_in_the_list_stops_at_models(): void
    {
        foreach (['gpt-nope', 'gpt-hidden'] as $model) {
            $json = $this->runDiagnostic(['model' => $model])->assertOk()->json();

            $this->assertSame('models', $json['stoppedAt']);
            $this->assertSame('failed', $json['steps'][3]['status']);
            $this->assertSame('model_not_available', $json['steps'][3]['reason']);
            $this->assertSame('skipped', $json['steps'][4]['status']);
        }
        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_an_image_the_model_does_not_accept_fails_the_analysis_with_image_input_no(): void
    {
        $this->responsesHandler = fn () => Http::response(
            ['error' => ['code' => 'subscription_sharing_unsupported_capability', 'message' => 'no images', 'param' => 'input_image']],
            400,
            ['x-request-id' => 'req_img'],
        );

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertSame('synthetic_analysis', $json['stoppedAt']);
        $step = $json['steps'][4];
        $this->assertSame('failed', $step['status']);
        $this->assertSame('plan_unsupported_capability', $step['reason']);
        $this->assertSame('no', $step['detail']['imageInput']);
        $this->assertSame('input_image', $step['detail']['param']);
        $this->assertSame('req_img', $step['detail']['requestId']);
        $this->assertSame(400, $step['detail']['status']);
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    public function test_a_model_listed_as_text_only_is_not_sent_the_image(): void
    {
        $json = $this->runDiagnostic(['model' => 'gpt-text'])->assertOk()->json();

        $this->assertSame('synthetic_analysis', $json['stoppedAt']);
        $this->assertSame('plan_unsupported_capability', $json['steps'][4]['reason']);
        $this->assertSame('no', $json['steps'][4]['detail']['imageInput']);
        $this->assertSame([], $this->sent(self::RESPONSES));
    }

    public function test_a_usage_limit_on_the_analysis_stops_there_and_does_not_retry(): void
    {
        $this->responsesHandler = fn () => Http::response(
            ['error' => ['code' => 'subscription_sharing_usage_limit_exceeded', 'message' => 'limit']], 429,
        );

        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->json();

        $this->assertSame('plan_usage_limit', $json['steps'][4]['reason']);
        $this->assertSame('unknown', $json['steps'][4]['detail']['imageInput']);
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    // ---- full success ---------------------------------------------------------------

    public function test_the_full_run_analyses_only_the_synthetic_plan_and_stores_nothing(): void
    {
        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk()->json();

        $this->assertNull($json['stoppedAt']);
        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'ok'], $this->statuses($json));
        $this->assertSame('Lien Smet', $json['steps'][1]['detail']['identity']['name']);

        $detail = $json['steps'][4]['detail'];
        $this->assertSame('yes', $detail['imageInput']);
        $this->assertSame(['wall' => 1], $detail['groupRoles']);
        $this->assertSame(2, $detail['rooms']);
        $this->assertSame(1, $detail['namedRooms']);
        $this->assertSame(1, $detail['uncertain']);
        $this->assertSame(['groupRoles' => 1, 'rooms' => 0, 'uncertain' => 0, 'names' => 1], $detail['dropped']);

        $this->assertSame(0, KnxPlanAnalysis::query()->count());

        $sent = $this->sent(self::RESPONSES);
        $this->assertCount(1, $sent);
        $this->assertSame('Bearer access-lien', $sent[0]->header('Authorization')[0]);
        $this->assertSame('gpt-vision', $sent[0]->data()['model']);
        $this->assertFalse($sent[0]->data()['store']);
        $this->assertTrue($sent[0]->data()['stream']);
        // The server's own synthetic page: the real PNG, not a document.
        $png = base64_decode(substr($sent[0]->data()['input'][0]['content'][1]['image_url'], strlen('data:image/png;base64,')), true);
        $this->assertSame((string) file_get_contents(__DIR__.'/../../resources/plan-analysis/diagnostic/plan-clean-page2.png'), $png);
        $this->assertStringContainsString('Woonkamer', $sent[0]->data()['input'][0]['content'][0]['text']);

        // Only the ChatGPT endpoints were used: never the API key.
        foreach (Http::recorded() as [$request]) {
            $this->assertStringNotContainsString('sk-must-never-be-used', json_encode($request->headers()));
            $this->assertContains($request->url(), [self::MODELS, self::RESPONSES]);
        }
    }

    public function test_the_analysis_reserves_the_chatgpt_budget_and_a_used_up_budget_stops_it(): void
    {
        config(['knx.plan_analysis.providers.chatgpt.max_calls_per_day' => 1]);

        $this->assertNull($this->runDiagnostic(['model' => 'gpt-vision'])->json('stoppedAt'));
        $json = $this->runDiagnostic(['model' => 'gpt-vision'])->json();

        $this->assertSame('synthetic_analysis', $json['stoppedAt']);
        $this->assertSame('budget_exhausted', $json['steps'][4]['reason']);
        $this->assertCount(1, $this->sent(self::RESPONSES));
    }

    public function test_the_models_step_lists_each_model_with_unknown_image_input_and_never_claims_generation(): void
    {
        $this->modelsHandler = fn () => Http::response(['models' => [
            ['slug' => 'a', 'display_name' => 'A', 'visibility' => 'list'],
            ['slug' => 'b', 'visibility' => 'list', 'modalities' => ['input' => ['text', 'image'], 'output' => ['text']]],
        ]]);

        $detail = $this->runDiagnostic()->json('steps.3.detail');

        $this->assertSame([
            ['slug' => 'a', 'displayName' => 'A', 'imageInput' => 'unknown'],
            ['slug' => 'b', 'displayName' => 'b', 'imageInput' => 'yes'],
        ], $detail['models']);
        $this->assertSame('unsupported', $detail['imageGeneration']);
        foreach ($this->sent(self::MODELS) as $request) {
            $this->assertSame('GET', $request->method());
        }
    }

    public function test_the_diagnostic_uses_the_calling_employees_own_token(): void
    {
        $this->connect('pieter.aerts@electrobertels.be', 'access-pieter');
        $this->actAs('pieter.aerts@electrobertels.be');

        $this->runDiagnostic(['model' => 'gpt-vision'])->assertOk();

        foreach ([...$this->sent(self::MODELS), ...$this->sent(self::RESPONSES)] as $request) {
            $this->assertSame('Bearer access-pieter', $request->header('Authorization')[0]);
        }
    }

    public function test_guests_and_field_users_get_401_and_nothing_is_sent(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson(self::URL)->assertUnauthorized();

        $this->actAs('jan.van.dyck@electrobertels.be');
        $this->postJson(self::URL, ['model' => 'gpt-vision'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_model_must_be_a_string(): void
    {
        $this->postJson(self::URL, ['model' => ['x']])->assertUnprocessable();
        Http::assertNothingSent();
    }

    // ---- helpers --------------------------------------------------------------------

    /** @param  array<string, mixed>  $body */
    private function runDiagnostic(array $body = []): TestResponse
    {
        return $this->postJson(self::URL, $body);
    }

    /** @return list<string> */
    private function statuses(array $json): array
    {
        return array_column($json['steps'], 'status');
    }

    private function actAs(string $email): void
    {
        $this->actingAs(User::query()->where('email', $email)->sole(), 'sanctum');
    }

    private function connect(string $email, string $token = 'access-lien'): void
    {
        $employee = KnxEmployee::query()->where('user_id', User::query()->where('email', $email)->value('id'))->sole();

        KnxChatGptConnection::query()->updateOrCreate(
            ['organization_id' => $employee->organization_id, 'employee_id' => $employee->getKey()],
            [
                'subject' => 'sub-'.$email,
                'email' => 'lien@example.test',
                'name' => 'Lien Smet',
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
                self::MODELS => ($this->modelsHandler)($request),
                self::RESPONSES => ($this->responsesHandler)($request),
                'https://auth.openai.com/.well-known/openid-configuration' => Http::response([
                    'issuer' => 'https://auth.openai.com',
                    'authorization_endpoint' => 'https://auth.openai.com/api/accounts/authorize',
                    'token_endpoint' => self::TOKEN_URL,
                    'jwks_uri' => 'https://auth.openai.com/.well-known/jwks.json',
                ]),
                self::TOKEN_URL => ($this->tokenHandler)($request),
                default => throw new \LogicException('Unexpected request to '.$request->url()),
            };
        });
    }

    private function completed(): mixed
    {
        $input = json_decode((string) file_get_contents(__DIR__.'/../../resources/plan-analysis/diagnostic/plan-clean-page2.input.json'), true);
        $proposal = [
            'groupRoles' => [
                ['groupId' => $input['groups'][0]['id'], 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'thick'],
                ['groupId' => 'not-a-group', 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'invented'],
            ],
            'rooms' => [
                ['name' => 'Woonkamer', 'textId' => 't0', 'x' => 0.2, 'y' => 0.3, 'confidence' => 0.8, 'reason' => 'label'],
                ['name' => 'Invented', 'textId' => null, 'x' => 0.6, 'y' => 0.6, 'confidence' => 0.5, 'reason' => 'guess'],
            ],
            'uncertain' => [['x' => 0.5, 'y' => 0.5, 'reason' => 'open space']],
        ];
        $events = [
            ['response.created', ['type' => 'response.created', 'response' => ['status' => 'in_progress']]],
            ['response.completed', ['type' => 'response.completed', 'response' => [
                'status' => 'completed', 'model' => 'gpt-vision-2026',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($proposal)]]]],
            ]]],
        ];
        $body = '';
        foreach ($events as [$name, $data]) {
            $body .= "event: {$name}\ndata: ".json_encode($data)."\n\n";
        }

        return Http::response($body, 200, ['Content-Type' => 'text/event-stream']);
    }
}
