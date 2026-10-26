<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * End-to-end HTTP coverage of Modules\Core\Http\Middleware\ResolveRequestSite
 * on the real /v1/website/* routes: no authenticated user exists on this
 * path (it's intentionally public/unthrottled, per
 * tests/Feature/ClaesenBaseline/AccessControlContractTest), so this is the
 * only place a site gets resolved from the request itself rather than from
 * OrganizationContext's organization-derived fallback (see
 * Modules\Core\tests\Feature\BelongsToSiteEnforcementTest for that path).
 *
 * "Cero datos cruzados" (CLA-471's own acceptance wording) is the specific
 * claim every cross-site test below exists to back.
 */
final class PublicApiSiteScopingTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableOrganizationEnforcement();
    }

    public function test_the_default_response_with_no_site_hint_stays_scoped_to_claesen(): void
    {
        // No ?site= param, Host defaults to the test client's own (never a
        // configured domain) — this is exactly what the real Astro frontend
        // sends today, so this pins zero behaviour change for it.
        $claesenProject = Project::factory()->create();
        $bertelsSite = $this->bertelsFixtureSite();
        Project::factory()->create(['site_id' => $bertelsSite->id]);

        $this->getJson('/v1/website/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $claesenProject->id);
    }

    public function test_an_explicit_site_query_param_scopes_the_index_to_that_site_only(): void
    {
        Project::factory()->create(); // Claesen
        $bertelsSite = $this->bertelsFixtureSite();
        $bertelsProject = Project::factory()->create(['site_id' => $bertelsSite->id]);

        $this->getJson('/v1/website/projects?site='.$bertelsSite->key)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bertelsProject->id);
    }

    public function test_a_project_detail_from_another_site_is_not_reachable_by_slug_even_via_the_wrong_sites_query(): void
    {
        $claesenSite = Site::query()->find(Site::claesenId());
        $bertelsSite = $this->bertelsFixtureSite();
        $bertelsProject = Project::factory()->create(['site_id' => $bertelsSite->id, 'slug' => 'cross-site-project']);

        // Asking for Bertels' slug while resolved as Claesen (no ?site=)
        // must 404, not leak the other site's row by slug collision.
        $this->getJson('/v1/website/projects/cross-site-project')
            ->assertNotFound();

        // The same slug, correctly scoped, resolves.
        $this->getJson('/v1/website/projects/cross-site-project?site='.$bertelsSite->key)
            ->assertOk()
            ->assertJsonPath('data.id', $bertelsProject->id);

        $this->assertNotNull($claesenSite);
    }

    public function test_a_domain_matching_a_configured_site_resolves_it_without_any_query_param(): void
    {
        $bertelsSite = $this->bertelsFixtureSite(null, ['domain' => 'bertels-fixture.test']);
        $bertelsProject = Project::factory()->create(['site_id' => $bertelsSite->id]);
        Project::factory()->create(); // Claesen, must not appear

        // Laravel's json()/call() always overwrites HTTP_HOST from the parsed
        // URI's own host (Symfony\Component\HttpFoundation\Request::create()),
        // ignoring a 'Host' header passed alongside a relative path — a full
        // absolute URL is the only way to make $request->getHost() actually
        // return something other than config('app.url')'s host in a test.
        $this->getJson('http://bertels-fixture.test/v1/website/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bertelsProject->id);
    }

    public function test_a_suspended_site_falls_back_to_claesen_instead_of_serving_its_data(): void
    {
        $claesenProject = Project::factory()->create();
        $suspendedSite = $this->bertelsFixtureSite(null, ['status' => Site::STATUS_SUSPENDED]);
        Project::factory()->create(['site_id' => $suspendedSite->id]);

        $this->getJson('/v1/website/projects?site='.$suspendedSite->key)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $claesenProject->id);
    }

    public function test_categories_and_years_endpoints_are_also_scoped(): void
    {
        Project::factory()->create(['category' => 'sport', 'year' => 2020]);
        $bertelsSite = $this->bertelsFixtureSite();
        Project::factory()->create(['site_id' => $bertelsSite->id, 'category' => 'industrial', 'year' => 2025]);

        $this->getJson('/v1/website/projects/categories?site='.$bertelsSite->key)
            ->assertOk()
            ->assertJson(['data' => ['industrial']]);

        $this->getJson('/v1/website/projects/years?site='.$bertelsSite->key)
            ->assertOk()
            ->assertJson(['data' => [2025]]);
    }
}
