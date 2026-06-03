<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * Tests for the Portfolio API endpoints.
 * Covers WEB-008 — multilingual portfolio (nl/en/fr/de) via Accept-Language header.
 */
class PortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HasAiTranslations fires on saving and overwrites factory-set translations
        // via GeminiService::translateAndDetect(). Return empty so values are preserved.
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });
    }

    // =========================================================================
    // GET /v1/website/projects
    // =========================================================================

    public function test_index_returns_only_published_projects(): void
    {
        Project::factory()->count(2)->create(['published' => true]);
        Project::factory()->draft()->count(3)->create();

        $this->getJson('/v1/website/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_category(): void
    {
        Project::factory()->create(['category' => 'sport']);
        Project::factory()->count(2)->create(['category' => 'industrial']);

        $this->getJson('/v1/website/projects?filter[category]=sport')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // =========================================================================
    // GET /v1/website/projects/{slug} — locale resolution (WEB-008)
    // =========================================================================

    public function test_show_returns_nl_translation_with_nl_accept_language(): void
    {
        $project = Project::factory()->create([
            'slug'  => 'nl-test',
            'title' => ['nl' => 'NL Titel', 'en' => 'EN Title', 'fr' => 'FR Titre'],
        ]);

        $this->getJson('/v1/website/projects/nl-test', ['Accept-Language' => 'nl'])
            ->assertOk()
            ->assertJsonPath('data.title', 'NL Titel');
    }

    public function test_show_returns_en_translation_with_en_accept_language(): void
    {
        $project = Project::factory()->create([
            'slug'  => 'en-test',
            'title' => ['nl' => 'NL Titel', 'en' => 'EN Title', 'fr' => 'FR Titre'],
        ]);

        $this->getJson('/v1/website/projects/en-test', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.title', 'EN Title');
    }

    public function test_show_returns_fr_translation_with_fr_accept_language(): void
    {
        $project = Project::factory()->create([
            'slug'  => 'fr-test',
            'title' => ['nl' => 'NL Titel', 'en' => 'EN Title', 'fr' => 'FR Titre'],
        ]);

        $this->getJson('/v1/website/projects/fr-test', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', 'FR Titre');
    }

    public function test_show_falls_back_to_en_when_no_accept_language_header(): void
    {
        // SetPanelLocale DEFAULT_LOCALE is 'en'
        $project = Project::factory()->create([
            'slug'  => 'default-test',
            'title' => ['nl' => 'NL Titel', 'en' => 'EN Title'],
        ]);

        $this->getJson('/v1/website/projects/default-test')
            ->assertOk()
            ->assertJsonPath('data.title', 'EN Title');
    }

    public function test_show_falls_back_to_nl_when_requested_locale_is_missing(): void
    {
        // Title only has 'nl' — requesting 'de' must fall back through nl→en chain
        $project = Project::factory()->create([
            'slug'  => 'fallback-test',
            'title' => ['nl' => 'NL Only'],
        ]);

        $this->getJson('/v1/website/projects/fallback-test', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('data.title', 'NL Only');
    }

    // =========================================================================
    // GET /v1/website/projects/{slug} — 404 for unpublished / missing
    // =========================================================================

    public function test_show_returns_404_for_unpublished_project(): void
    {
        Project::factory()->draft()->create(['slug' => 'hidden-project']);

        $this->getJson('/v1/website/projects/hidden-project')
            ->assertNotFound();
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/v1/website/projects/does-not-exist')
            ->assertNotFound();
    }

    // =========================================================================
    // GET /v1/website/projects/categories
    // =========================================================================

    public function test_categories_endpoint_returns_unique_published_categories(): void
    {
        Project::factory()->create(['category' => 'sport',      'published' => true]);
        Project::factory()->create(['category' => 'sport',      'published' => true]);
        Project::factory()->create(['category' => 'industrial', 'published' => true]);
        Project::factory()->create(['category' => 'public',     'published' => false]);

        $response = $this->getJson('/v1/website/projects/categories')->assertOk();
        $data     = $response->json('data');

        // Only published categories; no duplicates; 'public' excluded (unpublished)
        $this->assertCount(2, $data);
        $this->assertContains('sport', $data);
        $this->assertContains('industrial', $data);
        $this->assertNotContains('public', $data);
    }
}
