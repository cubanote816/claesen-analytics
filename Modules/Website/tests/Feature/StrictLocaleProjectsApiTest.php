<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * CLA-611 gap G9, site-scoped (approver decision 3): the projects public
 * API serves a STRICT locale policy for opted-in sites — a field without a
 * translation in the requested locale is null, never Dutch/English.
 *
 * Claesen's tolerant fallback (locale → nl → en → first) is intentionally
 * UNCHANGED and stays frozen by PortfolioApiTest — this file only adds the
 * strict side, it does not replace anything.
 */
final class StrictLocaleProjectsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        app()->setLocale('nl');
    }

    public function test_a_strict_site_returns_null_for_a_missing_requested_locale_never_dutch(): void
    {
        $site = Site::factory()->create(['key' => 'bertels-strict-projects']);

        // Only NL filled — no FR translation exists.
        $project = Project::factory()->create([
            'site_id' => $site->id,
            'slug' => 'strict-project',
            'title' => ['nl' => 'Verdeelbord installatie'],
            'description' => ['nl' => 'Nederlandse omschrijving.'],
            'location' => ['nl' => 'Balen, België'],
            'client' => ['nl' => 'Klant BV'],
        ]);

        config(['website.public_api.strict_locale_site_keys' => [$site->key]]);

        $this->getJson('/v1/website/projects/strict-project?site=bertels-strict-projects', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'strict-project')
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.client', null);

        // The same strict site WITH the FR translation serves it normally.
        $project->setTranslation('title', 'fr', 'Installation du tableau de distribution');
        $project->save();
        $project->refresh();

        $this->getJson('/v1/website/projects/strict-project?site=bertels-strict-projects', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Installation du tableau de distribution');
    }

    public function test_a_tolerant_site_keeps_the_frozen_fallback_chain(): void
    {
        // Not on the strict list (Claesen and any default site): the
        // historical behaviour must survive untouched — this is the
        // zero-regression mirror of the strict test above.
        $site = Site::factory()->create(['key' => 'tolerant-projects-site']);

        Project::factory()->create([
            'site_id' => $site->id,
            'slug' => 'tolerant-project',
            'title' => ['nl' => 'Verdeelbord installatie'],
        ]);

        $this->getJson('/v1/website/projects/tolerant-project?site=tolerant-projects-site', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Verdeelbord installatie');
    }

    public function test_strictness_does_not_leak_into_the_claesen_default_response(): void
    {
        // Even with a strict site configured, a request resolved to Claesen
        // (the default) keeps the fallback: the policy is per REQUEST site.
        config(['website.public_api.strict_locale_site_keys' => ['bertels-strict-projects']]);

        Project::factory()->create([
            'slug' => 'claesen-fallback-project',
            'title' => ['nl' => 'Claesen project'],
        ]);

        $this->getJson('/v1/website/projects/claesen-fallback-project', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Claesen project');
    }
}
