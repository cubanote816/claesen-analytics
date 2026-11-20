<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Intelligence\Jobs\GenerateBudgetSimulationJob;
use Modules\Intelligence\Services\BudgetAssistantService;
use Modules\Intelligence\Services\GeminiService;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\Services\MaterialIntelligenceService;
use Modules\Intelligence\Support\GeminiEndpoint;
use Modules\Performance\Models\Mirror\MirrorMaterial;
use Tests\TestCase;

/**
 * CLA-729 S2/S3/S4: each Intelligence call site hits the builder URL and
 * sends the migrated payload (thinkingLevel MINIMAL, no temperature/topK).
 */
final class GeminiCallSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.url' => null,
            'services.gemini.location' => 'eu',
            'services.gemini.model' => 'gemini-3-test-model',
        ]);

        $this->mock(GoogleServiceAccountAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
        });

        Http::preventStrayRequests();
    }

    private function expectedUrl(): string
    {
        $url = GeminiEndpoint::generateContentUrl();
        $this->assertStringContainsString('eu-aiplatform.googleapis.com', $url);
        $this->assertStringContainsString('gemini-3-test-model', $url);

        return $url;
    }

    private function fakeAnswer(array|string $payload): void
    {
        $text = is_array($payload) ? json_encode($payload) : $payload;

        Http::fake([
            $this->expectedUrl() => Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]),
        ]);
    }

    private function assertMigratedRequest(): void
    {
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $config = $request['generationConfig'] ?? [];

            return $request->url() === $this->expectedUrl()
                && ($config['thinkingConfig']['thinkingLevel'] ?? null) === 'MINIMAL'
                && ! array_key_exists('temperature', $config)
                && ! array_key_exists('topK', $config);
        });
    }

    public function test_gemini_service_uses_builder_url_and_migrated_payload(): void
    {
        $this->fakeAnswer(['ok' => true]);

        $result = app(GeminiService::class)->generateStructuredResponse('hello');

        $this->assertSame(['ok' => true], $result);
        $this->assertMigratedRequest();
        Http::assertSent(fn (Request $r) => ($r['generationConfig']['response_mime_type'] ?? null) === 'application/json');
    }

    public function test_budget_simulation_job_uses_builder_url_and_migrated_payload(): void
    {
        $this->fakeAnswer('Analysis text.');

        (new GenerateBudgetSimulationJob(['zipcode' => '9000', 'category' => 'Sport'], 'nl', 'cla729-job'))
            ->handle(app(GoogleServiceAccountAuthService::class));

        $this->assertSame('Analysis text.', Cache::get('cla729-job'));
        $this->assertMigratedRequest();
    }

    public function test_budget_simulation_job_omits_an_empty_generation_config_for_a_2x_override_url(): void
    {
        // Vertex expects generationConfig to be an object; with nothing to
        // configure the job must leave it out instead of sending [].
        $url = 'https://us-central1-aiplatform.googleapis.com/v1/projects/p/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent';
        config(['services.gemini.url' => $url]);
        Http::fake([$url => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Legacy analysis.']]]]]])]);

        (new GenerateBudgetSimulationJob(['zipcode' => '9000', 'category' => 'Sport'], 'nl', 'cla729-job-2x'))
            ->handle(app(GoogleServiceAccountAuthService::class));

        $this->assertSame('Legacy analysis.', Cache::get('cla729-job-2x'));
        Http::assertSent(fn (Request $request): bool => $request->url() === $url
            && ! array_key_exists('generationConfig', $request->data()));
    }

    public function test_budget_assistant_uses_builder_url_and_migrated_payload(): void
    {
        $this->fakeAnswer([
            'projected_cost' => 54321,
            'budget_sections' => [],
            'intro_text' => 'intro',
        ]);

        $result = app(BudgetAssistantService::class)->simulate('LED verlichting voor sportveld met mast', 'Sport', '9000');

        $this->assertSame('intro', $result['intro_text']);
        $this->assertGreaterThanOrEqual(54321, $result['projected_cost']);
        $this->assertFalse($result['is_fallback']);
        $this->assertMigratedRequest();
        Http::assertSent(fn (Request $r) => ($r['generationConfig']['response_mime_type'] ?? null) === 'application/json');
    }

    public function test_material_intelligence_uses_builder_url_and_migrated_payload(): void
    {
        $this->fakeAnswer(['category_ai' => 'Masten', 'tags' => ['beton'], 'is_modern' => true, 'modern_id_advice' => '']);

        $material = MirrorMaterial::create(['id' => 729, 'ref' => 'R1', 'description' => 'Mast 10m', 'cost_price' => 10, 'fl_active' => true]);

        $this->assertTrue(app(MaterialIntelligenceService::class)->learn($material));
        $this->assertSame('Masten', $material->fresh()->category_ai);
        $this->assertMigratedRequest();
    }

    public function test_gemini_service_omits_thinking_config_for_a_2x_override_url(): void
    {
        $url = 'https://us-central1-aiplatform.googleapis.com/v1/projects/p/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent';
        config(['services.gemini.url' => $url]);
        Http::fake([$url => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]]])]);

        $this->assertSame(['ok' => true], app(GeminiService::class)->generateStructuredResponse('hello'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($url): bool {
            $config = $request['generationConfig'] ?? [];

            return $request->url() === $url
                && ! array_key_exists('thinkingConfig', $config)
                && ! array_key_exists('temperature', $config)
                && ($config['response_mime_type'] ?? null) === 'application/json';
        });
    }
}
