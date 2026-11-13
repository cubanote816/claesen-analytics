<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
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
            'knx.plan_analysis.budget.max_calls_per_day' => 5,
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
        $this->assertSame('synthetic-examples', $post->json('analysis.model'));
        $this->assertSame('plan-analysis/1', $post->json('analysis.schemaVersion'));
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
        $this->assertEquals(['groupRoles' => 0, 'rooms' => 0, 'uncertain' => 0, 'names' => 0], $post->json('analysis.proposal.dropped'));

        $get = $this->getJson("{$this->base($document)}/analyses?page=2")->assertOk();
        $this->assertSame($post->json('analysis'), $get->json('analysis'));
        // Other pages have none.
        $this->assertNull($this->getJson("{$this->base($document)}/analyses?page=1")->assertOk()->json('analysis'));
    }

    public function test_an_unavailable_provider_is_a_409_without_fallback(): void
    {
        $document = $this->document();
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

        $proposal = $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertCreated()
            ->json('analysis.proposal');

        // A confidence above 1 is clamped to 1 (JSON does not keep 1.0 apart from 1).
        $this->assertEquals([['groupId' => 'style:f:#000000', 'role' => 'wall', 'confidence' => 1, 'reason' => 'fills']], $proposal['groupRoles']);
        // The name IS the PDF text behind `textId`; without evidence there is no name.
        $this->assertSame([['Woonkamer', 't0'], [null, null], [null, null]], array_map(fn ($r) => [$r['name'], $r['textId']], $proposal['rooms']));
        $this->assertEquals([['x' => 0.8, 'y' => 0.7, 'reason' => 'open']], $proposal['uncertain']);
        $this->assertEquals(['groupRoles' => 2, 'rooms' => 1, 'uncertain' => 1, 'names' => 1], $proposal['dropped']);
        Http::assertSentCount(1);
    }

    public function test_a_spent_budget_is_a_429_and_stores_nothing(): void
    {
        $document = $this->document();
        $this->enableOpenAi();
        config(['knx.plan_analysis.budget.max_calls_per_day' => 0]);
        Http::fake();
        $this->putJson("{$this->base($document)}/ai-authorizations/openai")->assertOk();

        $this->postJson("{$this->base($document)}/analyses", $this->body('openai'))
            ->assertStatus(429)
            ->assertJsonPath('code', 'plan_analysis_budget_exhausted');

        Http::assertNothingSent();
        $this->assertSame(0, KnxPlanAnalysis::query()->count());
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
}
