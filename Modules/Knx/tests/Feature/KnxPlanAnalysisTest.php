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
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanAnalysis;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * The AI proposal of a plan page (KNX-48, plano IA), end to end through the API with the
 * synthetic provider and a faked OpenAI: authorization first, no fallback, validation
 * against what was sent, and the stored proposal read back.
 */
final class KnxPlanAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
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

    /**
     * @return array<string, mixed>
     */
    private function body(string $provider = 'synthetic', array $overrides = []): array
    {
        $group = fn (string $id, ?float $w, ?string $c, int $paths) => [
            'id' => $id, 'kind' => 'style', 'name' => $id, 'paths' => $paths,
            'strokeWidth' => $w, 'strokeColor' => $c, 'closedPct' => 0, 'curvedPct' => 0,
        ];

        return array_replace([
            'page' => 2,
            'provider' => $provider,
            'image' => base64_encode("\x89PNG\r\n\x1a\nsynthetic"),
            'pageWidthPt' => 1190.55,
            'pageHeightPt' => 841.89,
            'groups' => [
                $group('style:f:#000000', null, null, 25),
                $group('style:s:#0066cc:0.5', 0.5, '#0066cc', 18),
                $group('style:s:#cc4c00:0.6', 0.6, '#cc4c00', 10),
                $group('style:s:#663399:0.4', 0.4, '#663399', 14),
                $group('style:s:#008080:0.7', 0.7, '#008080', 4),
                $group('style:s:#990033:0.35', 0.35, '#990033', 6),
            ],
            'texts' => [
                ['id' => 't0', 'text' => 'Woonkamer', 'x' => 0.19, 'y' => 0.34],
                ['id' => 't1', 'text' => 'Hal', 'x' => 0.5, 'y' => 0.19],
                ['id' => 't2', 'text' => 'Carport', 'x' => 0.8, 'y' => 0.7],
            ],
        ], $overrides);
    }

    private function enableOpenAi(): void
    {
        config([
            'knx.plan_analysis.providers.openai.enabled' => true,
            'knx.plan_analysis.providers.openai.model' => 'test-model',
            'knx.plan_analysis.providers.openai.api_key' => 'sk-test-only',
            'knx.plan_analysis.providers.openai.base_url' => 'https://openai.test/v1',
            'knx.ai_spend.daily_limit_usd' => 1.0,
            'knx.ai_spend.max_calls_per_processing' => 2,
            'knx.ai_spend.max_cost_per_processing_usd' => 0.50,
            'knx.ai_spend.models' => ['test-model' => ['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00, 'image_input_tokens_max' => 30000]],
            'knx.plan_analysis.providers.openai.retention' => [
                'policy' => 'zero_data_retention',
                'verified_at' => '2026-11-01',
                'models' => 'test-model',
                'features' => 'responses,image_input',
            ],
        ]);
    }

    public function test_an_unauthorized_document_is_never_sent_and_nothing_is_stored(): void
    {
        $document = $this->document();
        Http::fake();
        $this->enableOpenAi();

        foreach (['synthetic', 'openai'] as $provider) {
            $this->postJson("{$this->base($document)}/analyses", $this->body($provider))
                ->assertForbidden()
                ->assertJsonPath('code', 'plan_analysis_not_authorized');
        }

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_an_authorized_synthetic_analysis_is_validated_stored_and_read_back(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $post = $this->postJson("{$this->base($document)}/analyses", $this->body())->assertCreated();

        $this->assertSame((string) $document->getKey(), $post->json('documentId'));
        $this->assertSame(2, $post->json('analysis.page'));
        $this->assertSame('Rev. C', $post->json('analysis.revision'));
        $this->assertSame('synthetic', $post->json('analysis.provider'));
        $this->assertSame('simulation', $post->json('analysis.billing'));
        $this->assertSame('synthetic-examples', $post->json('analysis.model'));
        $this->assertSame('plan-analysis/2', $post->json('analysis.schemaVersion'));
        $this->assertSame('Lien Smet', $post->json('analysis.createdBy'));
        $this->assertSame(
            ['style:f:#000000' => 'wall', 'style:s:#0066cc:0.5' => 'window', 'style:s:#cc4c00:0.6' => 'door',
                'style:s:#663399:0.4' => 'stair', 'style:s:#008080:0.7' => 'lift', 'style:s:#990033:0.35' => 'void'],
            array_column($post->json('analysis.proposal.groupRoles'), 'role', 'groupId'),
        );
        $this->assertSame(['Woonkamer', 'Hal'], array_column($post->json('analysis.proposal.rooms'), 'name'));
        $this->assertSame(['t0', 't1'], array_column($post->json('analysis.proposal.rooms'), 'textId'));
        $this->assertCount(1, $post->json('analysis.proposal.uncertain'));
        // The stored JSON column orders object keys its own way: compare objects by content.
        $this->assertEquals(['groupRoles' => 0, 'rooms' => 0, 'uncertain' => 0, 'names' => 0, 'legend' => 0, 'symbols' => 0], $post->json('analysis.proposal.dropped'));

        $get = $this->getJson("{$this->base($document)}/analyses?page=2")->assertOk();
        $this->assertSame($post->json('analysis'), $get->json('analysis'));
        // Other pages have none.
        $this->assertNull($this->getJson("{$this->base($document)}/analyses?page=1")->assertOk()->json('analysis'));
    }

    public function test_an_unavailable_provider_is_a_409_without_fallback(): void
    {
        $document = $this->document();
        // As shipped (adapter off), never whatever the developer's .env enables.
        config(['knx.plan_analysis.providers.openai.enabled' => false]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'plan_analysis_unavailable');

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_a_revoked_authorization_stops_the_next_analysis(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();
        $this->deleteJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body())->assertForbidden();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_the_provider_answer_is_checked_against_what_was_sent(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();
        $answer = [
            'groupRoles' => [
                ['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 1.4, 'reason' => 'fills'],
                ['groupId' => 'style:never-sent', 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'x'],
                ['groupId' => 'style:s:#0066cc:0.5', 'role' => 'balcony', 'confidence' => 0.9, 'reason' => 'x'],
            ],
            'rooms' => [
                ['name' => 'Living room', 'textId' => 't0', 'x' => 0.2, 'y' => 0.35, 'confidence' => 0.8, 'reason' => 'label'],
                ['name' => 'Garage', 'textId' => null, 'x' => 0.6, 'y' => 0.6, 'confidence' => 0.6, 'reason' => 'looks like one'],
                ['name' => null, 'textId' => 't9', 'x' => 0.3, 'y' => 0.3, 'confidence' => 0.5, 'reason' => 'unknown text'],
                ['name' => 'Outside', 'textId' => 't1', 'x' => 1.5, 'y' => 0.2, 'confidence' => 0.5, 'reason' => 'off page'],
            ],
            'uncertain' => [['x' => 0.8, 'y' => 0.7, 'reason' => 'open'], ['x' => -1, 'y' => 0, 'reason' => 'bad']],
        ];
        Http::fake(['https://openai.test/v1/responses' => Http::response([
            'status' => 'completed',
            'model' => 'test-model',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($answer)]]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ])]);

        $analysis = $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertCreated()
            ->json('analysis');
        $proposal = $analysis['proposal'];

        // The stored usage keeps the real cost: (10 * 1.00 + 20 * 5.00) / 1e6.
        $this->assertEquals(['inputTokens' => 10, 'cachedTokens' => null, 'outputTokens' => 20, 'costUsd' => 0.00011], $analysis['usage']);
        $entry = KnxAiSpendEntry::query()->sole();
        $this->assertSame('settled', $entry->status);
        $this->assertSame($document->project_id, $entry->project_id);
        $this->assertSame($document->getKey(), $entry->document_id);
        $this->assertSame(2, $entry->page);
        $this->assertNotNull($entry->processing_id);

        // A confidence above 1 is clamped to 1 (JSON does not keep 1.0 apart from 1).
        $this->assertEquals([['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 1, 'reason' => 'fills']], $proposal['groupRoles']);
        // The name IS the PDF text behind `textId`; without evidence there is no name.
        $this->assertSame([['Woonkamer', 't0'], [null, null], [null, null]], array_map(fn ($r) => [$r['name'], $r['textId']], $proposal['rooms']));
        $this->assertEquals([['x' => 0.8, 'y' => 0.7, 'reason' => 'open']], $proposal['uncertain']);
        $this->assertEquals(['groupRoles' => 2, 'rooms' => 1, 'uncertain' => 1, 'names' => 1], $proposal['dropped']);
        Http::assertSentCount(1);
    }

    public function test_a_spent_daily_budget_is_a_429_with_the_resume_time_and_stores_nothing(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        config(['knx.ai_spend.daily_limit_usd' => 0]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        $response = $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(429)
            ->assertJsonPath('code', 'plan_analysis_daily_budget_exhausted');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T00:00:00[+-]\d{2}:\d{2}$/', $response->json('resumesAt'));
        $this->assertArrayNotHasKey('remainingUsd', $response->json());
        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
        $this->assertSame(0, KnxAiSpendEntry::query()->count());
    }

    public function test_a_model_without_a_price_is_a_409_and_nothing_is_sent(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        config(['knx.ai_spend.models' => []]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'plan_analysis_pricing_unknown');

        Http::assertNothingSent();
    }

    public function test_an_unverified_retention_policy_is_a_409_and_nothing_is_sent(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        config(['knx.plan_analysis.providers.openai.retention.verified_at' => null]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(409)
            ->assertJsonPath('code', 'plan_analysis_retention_not_verified');

        Http::assertNothingSent();
        $providers = $this->getJson("{$this->base($document)}/ai-authorizations")->json('providers');
        $this->assertFalse(array_column($providers, 'available', 'provider')['openai']);
    }

    public function test_the_processing_call_cap_is_a_429(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        config(['knx.ai_spend.max_calls_per_processing' => 0]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(429)
            ->assertJsonPath('code', 'plan_analysis_processing_call_limit');
        Http::assertNothingSent();

        config(['knx.ai_spend.max_calls_per_processing' => 2, 'knx.ai_spend.max_cost_per_processing_usd' => 0.000001]);
        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(429)
            ->assertJsonPath('code', 'plan_analysis_processing_cost_limit');
        Http::assertNothingSent();
    }

    public function test_a_failing_provider_is_a_502_stores_nothing_and_falls_back_to_nothing(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();
        // The synthetic provider is authorized too: it must NOT be used as a fallback.
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();
        Http::fake(['https://openai.test/v1/responses' => Http::response(['error' => 'boom'], 500)]);

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(502)
            ->assertJsonPath('code', 'plan_analysis_provider_error');

        Http::assertSentCount(1);
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
        $this->assertSame('uncertain', KnxAiSpendEntry::query()->sole()->status);
    }

    public function test_bad_input_is_a_422(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();
        $url = "{$this->base($document)}/analyses";

        $this->postJson($url, $this->body('gemini'))->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->postJson($url, $this->body('synthetic', ['image' => base64_encode('GIF89a')]))->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson($url, $this->body('synthetic', ['texts' => [['id' => 't0', 'text' => 'X', 'x' => 2, 'y' => 0]]]))->assertUnprocessable()->assertJsonValidationErrors('texts.0.x');

        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_a_guest_cannot_analyse_and_another_projects_document_is_a_404(): void
    {
        $document = $this->document();
        $foreign = KnxDocument::query()->whereHas('project', fn ($q) => $q->where('code', '!=', 'C1618'))->firstOrFail();

        $this->postJson("/api/v1/knx/projects/C1618/plans/{$foreign->getKey()}/analyses", $this->body())->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->postJson("{$this->base($document)}/analyses", $this->body())->assertUnauthorized();
        $this->getJson("{$this->base($document)}/analyses")->assertUnauthorized();

        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    // ---- plan-analysis/2 (symbols, legend, devices) -----------------------------------

    /**
     * @return array<string, mixed>
     */
    private function symbol(string $id, float $x = 0.4, float $y = 0.3, array $near = []): array
    {
        return ['id' => $id, 'group' => 'style:s:#ff0000:0.25', 'x' => $x, 'y' => $y, 'w' => 0.006, 'h' => 0.006, 'paths' => 4, 'nearTextIds' => $near];
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyV2(string $provider = 'synthetic', array $overrides = []): array
    {
        $body = $this->body($provider);
        $body['texts'][] = ['id' => 't3', 'text' => 'Drukknop', 'x' => 0.85, 'y' => 0.1];
        $body['texts'][] = ['id' => 't4', 'text' => 'BORD 1', 'x' => 0.5, 'y' => 0.5];

        return array_replace($body, [
            'symbols' => [$this->symbol('s10'), $this->symbol('s20', 0.5, 0.5, ['t4']), $this->symbol('s30'), $this->symbol('s40'), $this->symbol('s50')],
            'legend' => ['x' => 0.8, 'y' => 0.05, 'w' => 0.18, 'h' => 0.4],
        ], $overrides);
    }

    /**
     * Runs an OpenAI (faked) answer through the whole pipeline and returns the stored proposal.
     *
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function askOpenAi(array $answer, ?array $body = null): array
    {
        $document = $this->document();
        $this->enableOpenAi();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();
        $answer += ['groupRoles' => [], 'rooms' => [], 'uncertain' => []];
        Http::fake(['https://openai.test/v1/responses' => Http::response([
            'status' => 'completed',
            'model' => 'test-model',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($answer)]]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ])]);

        return $this->postJson("{$this->base($document)}/analyses", $body ?? $this->bodyV2('openai'))
            ->assertCreated()
            ->json('analysis.proposal');
    }

    /**
     * @return array<string, mixed>
     */
    private function device(string $id, string $type, ?string $label = null, float $confidence = 0.9): array
    {
        return ['symbolId' => $id, 'role' => 'device', 'deviceType' => $type, 'labelTextId' => $label, 'confidence' => $confidence, 'reason' => 'legend'];
    }

    public function test_the_request_accepts_symbols_and_a_legend_and_a_v1_request_still_works(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();
        $url = "{$this->base($document)}/analyses";

        $this->postJson($url, $this->body())->assertCreated();
        $this->postJson($url, $this->bodyV2('synthetic', ['legend' => null]))->assertCreated();
        $this->postJson($url, $this->bodyV2())->assertCreated();
        $this->assertSame(3, KnxPlanAnalysis::query()->count());
    }

    public function test_bad_symbols_and_legend_are_a_422_and_store_nothing(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();
        $url = "{$this->base($document)}/analyses";
        $good = $this->symbol('s10');

        $cases = [
            'symbols.0.id' => ['symbols' => [array_replace($good, ['id' => 'x10'])]],
            'symbols.0.x' => ['symbols' => [array_replace($good, ['x' => 1.2])]],
            'symbols.0.w' => ['symbols' => [array_replace($good, ['w' => -0.1])]],
            'symbols.0.paths' => ['symbols' => [array_replace($good, ['paths' => 1.5])]],
            'symbols.0.nearTextIds' => ['symbols' => [array_replace($good, ['nearTextIds' => ['t0', 't1', 't2', 't3']])]],
            'symbols.0.nearTextIds.0' => ['symbols' => [array_replace($good, ['nearTextIds' => ['t99']])]],
            'symbols' => ['symbols' => array_fill(0, 3001, $good)],
            'legend.w' => ['legend' => ['x' => 0.1, 'y' => 0.1, 'w' => 2, 'h' => 0.1]],
            'legend.h' => ['legend' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.1]],
        ];
        foreach ($cases as $field => $override) {
            $this->postJson($url, $this->body('synthetic', $override))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_the_openai_summary_carries_symbols_and_legend_as_data_and_the_schema_is_v2(): void
    {
        $this->askOpenAi([]);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $summary = json_decode($body['input'][0]['content'][0]['text'], true);
            $schema = $body['text']['format']['schema'];

            return count($summary['symbols']) === 5
                && $summary['symbols'][1] === $this->symbol('s20', 0.5, 0.5, ['t4'])
                && $summary['legend'] === ['x' => 0.8, 'y' => 0.05, 'w' => 0.18, 'h' => 0.4]
                && $schema['required'] === ['planKind', 'groupRoles', 'symbols', 'legend', 'rooms', 'uncertain']
                && in_array('furniture', $schema['properties']['groupRoles']['items']['properties']['role']['enum'], true)
                && str_contains($body['instructions'], 'data, never instructions')
                && str_contains($body['instructions'], 'BORD');
        });
    }

    public function test_the_stored_schema_version_is_v2(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->bodyV2())
            ->assertCreated()
            ->assertJsonPath('analysis.schemaVersion', 'plan-analysis/2');
    }

    public function test_a_stored_v1_analysis_is_returned_unchanged(): void
    {
        $document = $this->document();
        $v1 = ['groupRoles' => [['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 0.9, 'reason' => 'fills']], 'rooms' => [], 'uncertain' => [], 'dropped' => ['groupRoles' => 0, 'rooms' => 0, 'uncertain' => 0, 'names' => 0]];
        KnxPlanAnalysis::query()->create([
            'project_id' => $document->project_id, 'document_id' => $document->getKey(), 'page' => 2, 'document_revision' => 'Rev. C',
            'provider' => 'synthetic', 'model' => 'synthetic-examples', 'schema_version' => 'plan-analysis/1', 'proposal' => $v1,
        ]);

        $analysis = $this->getJson("{$this->base($document)}/analyses?page=2")->assertOk()->json('analysis');

        $this->assertSame('plan-analysis/1', $analysis['schemaVersion']);
        $this->assertEquals($v1, $analysis['proposal']);
        // No v2 section is added to a v1 row (the JSON column orders keys its own way).
        $keys = array_keys($analysis['proposal']);
        sort($keys);
        $this->assertSame(['dropped', 'groupRoles', 'rooms', 'uncertain'], $keys);
    }

    public function test_the_synthetic_provider_returns_a_valid_v2_proposal(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $proposal = $this->postJson("{$this->base($document)}/analyses", $this->bodyV2('synthetic', [
            'symbols' => [$this->symbol('s10'), $this->symbol('s20'), $this->symbol('s30', 0.5, 0.5, ['t4'])],
        ]))->assertCreated()->json('analysis.proposal');

        $this->assertSame('dwelling', $proposal['planKind']);
        $this->assertSame(['furniture', 'device', 'device'], array_column($proposal['symbols'], 'role'));
        $this->assertSame([null, 'pushbutton', 'bord'], array_column($proposal['symbols'], 'deviceType'));
        // Labels are the exact PDF text; the legend explains the pushbutton but not the board.
        $this->assertSame([null, null, 'BORD 1'], array_column($proposal['symbols'], 'label'));
        $this->assertSame([false, false, true], array_column($proposal['symbols'], 'needsReview'));
        $this->assertSame([['no_legend_entry']], array_values(array_filter(array_column($proposal['symbols'], 'doubts'))));
        $this->assertSame([['s20', 't3', 'Drukknop', 'pushbutton']], array_map(fn ($l) => [$l['symbolId'], $l['textId'], $l['text'], $l['deviceType']], $proposal['legend']));
        $this->assertSame('room', $proposal['rooms'][0]['kind']);
        $this->assertSame([0, 0, 0, 0, 0, 0], array_values($proposal['dropped']));
    }

    public function test_symbols_only_refer_to_symbols_and_texts_that_were_sent_and_the_first_answer_wins(): void
    {
        $proposal = $this->askOpenAi([
            'planKind' => 'dwelling',
            'symbols' => [
                $this->device('s10', 'pushbutton', 't3'),
                $this->device('s10', 'bord', 't4'),                         // second answer for s10
                $this->device('s99', 'pushbutton', null),                   // never sent
                ['symbolId' => 's30', 'role' => 'lamp', 'deviceType' => null, 'labelTextId' => null, 'confidence' => 0.9, 'reason' => 'x'], // role outside the enum
                $this->device('s40', 'socket', null),                       // deviceType outside the enum
                ['symbolId' => 's50', 'role' => 'furniture', 'deviceType' => 'bord', 'labelTextId' => 't99', 'confidence' => 1.7, 'reason' => 'sofa'],
            ],
            'legend' => [['symbolId' => 's10', 'textId' => 't3', 'deviceType' => 'pushbutton']],
        ]);

        $this->assertSame(['s10', 's50'], array_column($proposal['symbols'], 'symbolId'));
        $this->assertSame('pushbutton', $proposal['symbols'][0]['deviceType']);
        // Furniture is not a device: it carries no device type, no label and no doubts; confidence is clamped.
        $this->assertSame(['furniture', null, null, 1], [$proposal['symbols'][1]['role'], $proposal['symbols'][1]['deviceType'], $proposal['symbols'][1]['labelTextId'], $proposal['symbols'][1]['confidence']]);
        $this->assertSame(4, $proposal['dropped']['symbols']);
        $this->assertSame('dwelling', $proposal['planKind']);
    }

    public function test_a_label_is_the_exact_text_of_the_id_never_the_models_wording(): void
    {
        $proposal = $this->askOpenAi([
            'symbols' => [
                $this->device('s10', 'bord', 't4') + ['label' => 'Hoofdbord invented'],
                $this->device('s20', 'bord', 't99'),
            ],
            'legend' => [['symbolId' => null, 'textId' => 't4', 'deviceType' => 'bord']],
        ]);

        $this->assertSame(['t4', 'BORD 1'], [$proposal['symbols'][0]['labelTextId'], $proposal['symbols'][0]['label']]);
        $this->assertSame([null, null], [$proposal['symbols'][1]['labelTextId'], $proposal['symbols'][1]['label']]);
        $this->assertSame(1, $proposal['dropped']['names']);
    }

    public function test_a_symbol_inside_the_legend_region_is_never_a_device(): void
    {
        // The legend shows ONE sample of each symbol: it explains the plan, it is not installed
        // (doc 108, 2026-11-16: the model proposed both legend samples as devices).
        $body = $this->bodyV2('openai', ['symbols' => [$this->symbol('s10'), $this->symbol('s60', 0.85, 0.12)]]);
        $proposal = $this->askOpenAi([
            'symbols' => [$this->device('s10', 'pushbutton'), $this->device('s60', 'pushbutton')],
            'legend' => [['symbolId' => 's60', 'textId' => 't3', 'deviceType' => 'pushbutton']],
        ], $body);

        $by = array_column($proposal['symbols'], null, 'symbolId');
        $this->assertSame(['device', 'pushbutton', []], [$by['s10']['role'], $by['s10']['deviceType'], $by['s10']['doubts']]);
        $this->assertSame(['hidden', null, false, []], [$by['s60']['role'], $by['s60']['deviceType'], $by['s60']['needsReview'], $by['s60']['doubts']]);
        // The legend entry itself is kept: it is what explains s10.
        $this->assertSame(['s60'], array_column($proposal['legend'], 'symbolId'));
    }

    public function test_a_device_without_a_valid_legend_entry_for_its_type_needs_review(): void
    {
        $proposal = $this->askOpenAi([
            'symbols' => [
                $this->device('s10', 'pushbutton', 't3'),        // legend explains pushbutton
                $this->device('s20', 'bord', 't4'),              // no legend entry for bord
                $this->device('s30', 'detector_360', null),      // legend entry exists but its text is unknown
                $this->device('s40', 'pushbutton', null, 0.3),   // legend ok, confidence low
                $this->device('s50', 'detector_180', null, 0.2), // both
            ],
            'legend' => [
                ['symbolId' => 's10', 'textId' => 't3', 'deviceType' => 'pushbutton'],
                ['symbolId' => null, 'textId' => 't77', 'deviceType' => 'detector_360'],
            ],
        ]);

        $by = array_column($proposal['symbols'], null, 'symbolId');
        $this->assertSame([false, []], [$by['s10']['needsReview'], $by['s10']['doubts']]);
        $this->assertSame([true, ['no_legend_entry']], [$by['s20']['needsReview'], $by['s20']['doubts']]);
        $this->assertSame([true, ['no_legend_entry']], [$by['s30']['needsReview'], $by['s30']['doubts']]);
        $this->assertSame([true, ['low_confidence']], [$by['s40']['needsReview'], $by['s40']['doubts']]);
        $this->assertSame([true, ['no_legend_entry', 'low_confidence']], [$by['s50']['needsReview'], $by['s50']['doubts']]);
        // The invalid legend entry was dropped and counted.
        $this->assertSame(1, $proposal['dropped']['legend']);
        $this->assertCount(1, $proposal['legend']);
    }

    public function test_the_confidence_threshold_is_exactly_point_six(): void
    {
        $proposal = $this->askOpenAi([
            'symbols' => [$this->device('s10', 'pushbutton', null, 0.6), $this->device('s20', 'pushbutton', null, 0.59)],
            'legend' => [['symbolId' => null, 'textId' => 't3', 'deviceType' => 'pushbutton']],
        ]);

        $this->assertSame([false, true], array_column($proposal['symbols'], 'needsReview'));
    }

    public function test_legend_entries_are_checked_against_the_request(): void
    {
        $proposal = $this->askOpenAi([
            'legend' => [
                ['symbolId' => 's10', 'textId' => 't3', 'deviceType' => 'pushbutton'],
                ['symbolId' => 's10', 'textId' => 't4', 'deviceType' => 'bord'],        // same symbol: first wins
                ['symbolId' => 's99', 'textId' => 't3', 'deviceType' => 'pushbutton'],  // symbol never sent
                ['symbolId' => null, 'textId' => 't99', 'deviceType' => 'bord'],        // text never sent
                ['symbolId' => null, 'textId' => 't4', 'deviceType' => 'lamp'],         // type outside the enum
                ['symbolId' => null, 'textId' => 't4', 'deviceType' => 'bord'],
            ],
        ]);

        $this->assertSame(
            [['s10', 't3', 'Drukknop', 'pushbutton'], [null, 't4', 'BORD 1', 'bord']],
            array_map(fn ($l) => [$l['symbolId'], $l['textId'], $l['text'], $l['deviceType']], $proposal['legend']),
        );
        $this->assertSame(4, $proposal['dropped']['legend']);
    }

    public function test_rooms_carry_a_kind_a_null_name_when_unreadable_and_the_reason_for_a_missing_geometry(): void
    {
        $proposal = $this->askOpenAi([
            'groupRoles' => [['groupId' => 'style:s:#cc4c00:0.6', 'role' => 'furniture', 'confidence' => 0.8, 'reason' => 'sofa']],
            'rooms' => [
                ['name' => 'Hal', 'textId' => 't1', 'x' => 0.5, 'y' => 0.2, 'kind' => 'hall', 'confidence' => 0.9, 'reason' => 'label', 'geometryNullReason' => null],
                ['name' => 'Sofa room', 'textId' => null, 'x' => 0.3, 'y' => 0.6, 'kind' => 'corridor', 'confidence' => 0.4, 'reason' => 'open', 'geometryNullReason' => 'Open to the outside; no closed contour.'],
                ['name' => null, 'textId' => null, 'x' => 0.1, 'y' => 0.1, 'kind' => 'pantry', 'confidence' => 0.4, 'reason' => 'x', 'geometryNullReason' => null], // kind outside the enum
            ],
        ]);

        $this->assertSame('furniture', $proposal['groupRoles'][0]['role']);
        $this->assertSame([['Hal', 'hall', null], [null, 'corridor', 'Open to the outside; no closed contour.']], array_map(fn ($r) => [$r['name'], $r['kind'], $r['geometryNullReason']], $proposal['rooms']));
        $this->assertSame(1, $proposal['dropped']['rooms']);
        $this->assertSame(1, $proposal['dropped']['names']);
    }

    public function test_an_unknown_plan_kind_becomes_unknown(): void
    {
        $this->assertSame('unknown', $this->askOpenAi(['planKind' => 'castle'])['planKind']);
    }

    public function test_a_valid_plan_kind_is_kept(): void
    {
        $this->assertSame('other', $this->askOpenAi(['planKind' => 'other'])['planKind']);
    }

    // ---- S10: reference image ----------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function bodyRef(string $provider = 'openai', array $overrides = []): array
    {
        $body = $this->bodyV2($provider);
        foreach ($body['groups'] as $i => $group) {
            $body['groups'][$i]['ref'] = $i + 1;
        }
        foreach ($body['symbols'] as $i => $symbol) {
            $body['symbols'][$i]['ref'] = $i + 1;
        }
        $body['referenceImage'] = base64_encode("\x89PNG\r\n\x1a\nreference");

        return array_replace($body, $overrides);
    }

    private function authorizeOpenAi(): string
    {
        $document = $this->document();
        $this->enableOpenAi();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        return "{$this->base($document)}/analyses";
    }

    public function test_a_reference_image_goes_second_with_its_note_and_the_ref_table(): void
    {
        $this->askOpenAi([], $this->bodyRef());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['input'][0]['content'];
            $images = array_values(array_filter($content, fn (array $p): bool => $p['type'] === 'input_image'));
            $summary = json_decode($content[0]['text'], true);

            return count($images) === 2
                && $images[0]['image_url'] === 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nsynthetic")
                && $images[1]['image_url'] === 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nreference")
                && $content[1] === $images[0]
                && $content[2]['type'] === 'input_text'
                && str_starts_with($content[2]['text'], 'Image 2: the same page dimmed, with labels S<ref> on symbol candidates and G<ref> on paths of each group')
                && $content[3] === $images[1]
                && $summary['refTable']['symbols']['S2'] === 's20'
                && $summary['refTable']['groups']['G1'] === 'style:f:#000000'
                && $summary['symbols'][1]['ref'] === 2
                && str_contains($request->data()['instructions'], 'refTable');
        });
    }

    public function test_without_a_reference_image_exactly_one_image_is_sent(): void
    {
        $this->askOpenAi([]);

        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['input'][0]['content'];
            $summary = json_decode($content[0]['text'], true);

            return count($content) === 2
                && $content[1]['type'] === 'input_image'
                && ! array_key_exists('refTable', $summary)
                && ! array_key_exists('ref', $summary['symbols'][0])
                && ! array_key_exists('ref', $summary['groups'][0]);
        });
    }

    public function test_a_reference_image_without_refs_is_a_422_and_nothing_is_stored_or_sent(): void
    {
        $url = $this->authorizeOpenAi();
        Http::fake();
        $body = $this->bodyRef();

        $noGroupRef = $body;
        unset($noGroupRef['groups'][2]['ref']);
        $noSymbolRef = $body;
        unset($noSymbolRef['symbols'][3]['ref']);
        $noRefs = $this->bodyV2('openai', ['referenceImage' => $body['referenceImage']]);

        $this->postJson($url, $noGroupRef)->assertUnprocessable()->assertJsonValidationErrors('groups.2.ref');
        $this->postJson($url, $noSymbolRef)->assertUnprocessable()->assertJsonValidationErrors('symbols.3.ref');
        $this->postJson($url, $noRefs)->assertUnprocessable()->assertJsonValidationErrors(['groups.0.ref', 'symbols.0.ref']);

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
        $this->assertSame(0, KnxAiSpendEntry::query()->count());
    }

    public function test_a_duplicate_or_invalid_ref_is_a_422(): void
    {
        $url = $this->authorizeOpenAi();
        Http::fake();
        $body = $this->bodyRef();

        $dupGroup = $body;
        $dupGroup['groups'][1]['ref'] = 1;
        $dupSymbol = $body;
        $dupSymbol['symbols'][2]['ref'] = 1;
        $zero = $body;
        $zero['symbols'][0]['ref'] = 0;

        $this->postJson($url, $dupGroup)->assertUnprocessable()->assertJsonValidationErrors('groups.1.ref');
        $this->postJson($url, $dupSymbol)->assertUnprocessable()->assertJsonValidationErrors('symbols.2.ref');
        $this->postJson($url, $zero)->assertUnprocessable()->assertJsonValidationErrors('symbols.0.ref');

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_a_request_with_the_maximum_symbols_validates_quickly(): void
    {
        // doc 108 (2026-11-16): 664 symbols with refs hit the 30 s execution limit inside the request
        // validation (500) before anything was sent. 3000 symbols is the contract maximum.
        $url = $this->authorizeOpenAi();
        Http::fake();
        $body = $this->bodyRef();
        $texts = [];
        for ($t = 0; $t < 400; $t++) {
            $texts[] = ['id' => "t{$t}", 'text' => "T{$t}", 'x' => 0.5, 'y' => 0.5];
        }
        $body['texts'] = $texts;
        $symbols = [];
        for ($i = 0; $i < 3000; $i++) {
            $symbols[] = ['id' => "s{$i}", 'group' => 'style:s:#ff0000:0.25', 'x' => 0.1, 'y' => 0.1, 'w' => 0.01, 'h' => 0.01,
                'paths' => 3, 'ref' => $i + 1, 'nearTextIds' => ['t1', 't2', 't3']];
        }
        $body['symbols'] = $symbols;
        $body['symbols'][2999]['ref'] = 7; // one duplicate: still caught

        $started = microtime(true);
        $this->postJson($url, $body)->assertUnprocessable()->assertJsonValidationErrors('symbols.2999.ref');
        $seconds = microtime(true) - $started;

        $this->assertLessThan(5, $seconds, "validation took {$seconds} s");
        Http::assertNothingSent();
    }

    public function test_a_symbol_fraction_out_of_range_says_between_and_a_non_number_says_number(): void
    {
        // E20 (2026-11-17): x = -0.00004 answered "must be a number", which hid the real cause.
        $url = $this->authorizeOpenAi();
        Http::fake();
        $out = $this->bodyRef();
        $out['symbols'][0]['x'] = -0.00004;
        $text = $this->bodyRef();
        $text['symbols'][0]['x'] = 'left';

        $this->postJson($url, $out)->assertUnprocessable()
            ->assertJsonValidationErrors(['symbols.0.x' => trans('validation.between.numeric', ['attribute' => 'symbols.0.x', 'min' => 0, 'max' => 1])]);
        $this->postJson($url, $text)->assertUnprocessable()
            ->assertJsonValidationErrors(['symbols.0.x' => trans('validation.numeric', ['attribute' => 'symbols.0.x'])]);
        Http::assertNothingSent();
    }

    public function test_a_reference_image_that_is_not_a_png_is_a_422(): void
    {
        $url = $this->authorizeOpenAi();
        Http::fake();

        foreach ([base64_encode('GIF89a-not-a-png'), '***not base64***'] as $bad) {
            $this->postJson($url, $this->bodyRef('openai', ['referenceImage' => $bad]))
                ->assertUnprocessable()->assertJsonValidationErrors('referenceImage');
        }

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_an_oversized_reference_image_is_a_413_and_nothing_is_sent(): void
    {
        $url = $this->authorizeOpenAi();
        Http::fake();
        config(['knx.plan_analysis.budget.max_image_bytes' => 40]);

        $this->postJson($url, $this->bodyRef('openai', ['referenceImage' => base64_encode("\x89PNG".str_repeat('x', 60))]))
            ->assertStatus(413)->assertJsonPath('code', 'plan_analysis_image_too_large');

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
    }

    public function test_the_synthetic_provider_ignores_the_reference_image(): void
    {
        $document = $this->document();
        $this->putJson("{$this->base($document)}/ai-authorizations/synthetic")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->bodyRef('synthetic'))
            ->assertCreated()
            ->assertJsonPath('analysis.schemaVersion', 'plan-analysis/2');
        $this->assertSame(1, KnxPlanAnalysis::query()->count());
    }

    public function test_the_reservation_with_a_reference_image_counts_two_images(): void
    {
        $this->askOpenAi([], $this->bodyRef());
        $withRef = (float) KnxAiSpendEntry::query()->sole()->reserved_usd;
        KnxAiSpendEntry::query()->delete();
        KnxPlanAnalysis::query()->delete();

        $url = "{$this->base($this->document())}/analyses";
        $this->postJson($url, $this->bodyV2('openai'))->assertCreated();
        $without = (float) KnxAiSpendEntry::query()->sole()->reserved_usd;

        // 30000 more image tokens at 1 USD / 1M, plus the (small) note and refs.
        $this->assertGreaterThan($without + 0.03, $withRef);
        $this->assertLessThan($without + 0.031, $withRef);
    }
}
