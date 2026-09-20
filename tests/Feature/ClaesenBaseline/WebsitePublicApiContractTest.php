<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * Freezes the wire contract of the public website API that the Claesen Astro
 * frontend builds against.
 *
 * This is the contract most at risk in the multi-organization program: the read
 * endpoints have no notion of a site today, so binding them to the Claesen site
 * (phase P3) must keep the payload byte-compatible, and adding a Bertels project
 * must not change what these endpoints return.
 *
 * Complements Modules/Website/tests/Feature/PortfolioApiTest.php, which covers
 * filtering and locale behaviour. Here we pin the response *shape* and status
 * codes instead.
 */
final class WebsitePublicApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HasAiTranslations calls Gemini on save; keep the baseline offline and
        // deterministic (same approach as PortfolioApiTest).
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        config(['static_site.enabled' => false]);
    }

    public function test_the_public_read_endpoints_need_no_authentication(): void
    {
        Project::factory()->create(['slug' => 'baseline-project', 'published' => true]);

        $this->getJson('/v1/website/projects')->assertOk();
        $this->getJson('/v1/website/projects/categories')->assertOk();
        $this->getJson('/v1/website/projects/years')->assertOk();
        $this->getJson('/v1/website/projects/baseline-project')->assertOk();
    }

    public function test_the_project_index_payload_shape_is_frozen(): void
    {
        Project::factory()->create(['slug' => 'baseline-project', 'published' => true]);

        $this->getJson('/v1/website/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'slug', 'title', 'description',
                        'work_story', 'challenge', 'solution', 'result',
                        'category', 'location', 'year', 'featured', 'order_index',
                        // F3/CLA-467: 'original' deliberately dropped — the
                        // original file now lives on a private disk
                        // (Modules\Website\Models\Project::registerMediaCollections())
                        // and is never exposed by the public API. avif twins added.
                        'featured_image' => ['optimized', 'thumb', 'optimized_avif', 'thumb_avif'],
                        'gallery', 'detail_gallery',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_the_project_detail_payload_exposes_related_projects(): void
    {
        Project::factory()->create(['slug' => 'baseline-project', 'category' => 'sport', 'published' => true]);
        Project::factory()->count(2)->create(['category' => 'sport', 'published' => true]);

        $this->getJson('/v1/website/projects/baseline-project')
            ->assertOk()
            ->assertJsonPath('data.slug', 'baseline-project')
            ->assertJsonStructure(['data' => ['id', 'slug', 'title', 'category', 'related']]);
    }

    public function test_the_index_only_exposes_published_projects(): void
    {
        Project::factory()->create(['published' => true]);
        Project::factory()->draft()->create();

        $this->getJson('/v1/website/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_catalogue_endpoints_return_a_plain_data_array(): void
    {
        Project::factory()->create(['category' => 'sport', 'year' => 2024, 'published' => true]);

        $this->getJson('/v1/website/projects/categories')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/v1/website/projects/years')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_a_public_lead_is_accepted_with_201_and_persisted(): void
    {
        $response = $this->postJson('/v1/website/consultations', [
            'name' => 'Baseline Visitor',
            'email' => 'baseline@example.test',
            'message' => 'Ik heb interesse in een offerte.',
            'type' => 'quote',
        ]);

        $response->assertCreated()->assertJsonStructure(['message', 'data']);

        // F4/CLA-477 declared inversion: the lead-inbox workflow replaced
        // the old pending/contacted/in_progress/completed/cancelled status
        // set — a fresh lead now starts 'new', not 'pending'.
        $this->assertDatabaseHas('website_consultation_requests', [
            'email' => 'baseline@example.test',
            'status' => 'new',
        ]);

        // Phase P3a (CLA-547) gave a lead an explicit site. The intake endpoint
        // itself is still site-agnostic — it has no way to tell one website from
        // another, so it assigns Claesen (per-site routing is F3/F4). What
        // changed is that the ownership is now recorded instead of implied.
        $lead = ConsultationRequest::query()->firstOrFail();
        $this->assertTrue($lead->getConnection()->getSchemaBuilder()->hasColumn('website_consultation_requests', 'site_id'));
        $this->assertSame(Site::claesenId(), $lead->site_id);
    }

    public function test_an_invalid_public_lead_is_rejected_with_422(): void
    {
        $this->postJson('/v1/website/consultations', ['name' => 'No Email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'message']);
    }

    public function test_the_public_intake_endpoints_have_no_rate_limit_today(): void
    {
        // Documented gap: 12 consecutive submissions all succeed. When antispam
        // and throttling land (doc F4), this expectation must be replaced by the
        // new limit — deliberately, in that ticket.
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/v1/website/consultations', [
                'name' => "Visitor {$i}",
                'email' => "visitor{$i}@example.test",
                'message' => 'Baseline throttle probe.',
            ])->assertCreated();
        }

        $this->assertSame(12, ConsultationRequest::query()->count());
    }
}
