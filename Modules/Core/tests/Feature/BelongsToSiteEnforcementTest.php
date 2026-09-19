<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Exceptions\MissingOrganizationContext;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Modules\Core\Models\Concerns\BelongsToSite's global scope, exercised
 * directly against Project (the concrete BelongsToSite consumer). Isolates
 * the scope's own logic from OrganizationContext::site()'s two resolution
 * paths (explicit setSite() vs. organization-derived fallback) and from any
 * one HTTP route — Modules\Website\tests\Feature\PublicApiSiteScopingTest
 * covers the public API end to end instead.
 */
final class BelongsToSiteEnforcementTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_with_enforcement_off_the_scope_never_filters(): void
    {
        $bertelsSite = $this->bertelsFixtureSite();
        Project::factory()->create(['site_id' => $bertelsSite->id]);
        Project::factory()->create();

        $this->assertSame(2, Project::query()->count());
    }

    public function test_with_enforcement_on_and_an_explicitly_set_site_only_its_own_rows_are_visible(): void
    {
        $this->enableOrganizationEnforcement();

        $bertelsSite = $this->bertelsFixtureSite();
        $bertelsProject = Project::factory()->create(['site_id' => $bertelsSite->id]);
        Project::factory()->create(); // Claesen, by factory default

        app(OrganizationContext::class)->setSite($bertelsSite);

        $this->assertSame(1, Project::query()->count());
        $this->assertTrue(Project::query()->first()->is($bertelsProject));
    }

    public function test_with_enforcement_on_switching_the_explicit_site_switches_visibility(): void
    {
        $this->enableOrganizationEnforcement();

        $bertelsSite = $this->bertelsFixtureSite();
        Project::factory()->create(['site_id' => $bertelsSite->id]);
        $claesenProject = Project::factory()->create();

        $context = app(OrganizationContext::class);

        $context->setSite($bertelsSite);
        $this->assertSame(1, Project::query()->count());

        $context->setSite(Site::query()->find(Site::claesenId()));
        $this->assertSame(1, Project::query()->count());
        $this->assertTrue(Project::query()->first()->is($claesenProject));
    }

    public function test_with_enforcement_on_and_no_site_resolved_it_fails_loud_instead_of_returning_nothing(): void
    {
        $this->enableOrganizationEnforcement();

        Project::factory()->create();

        $this->expectException(MissingOrganizationContext::class);

        Project::query()->count();
    }

    public function test_with_enforcement_on_an_authenticated_claesen_user_falls_back_to_claesens_site(): void
    {
        $this->enableOrganizationEnforcement();

        $claesenProject = Project::factory()->create();
        $this->actingAs($this->claesenUser());

        // No setSite() call at all — OrganizationContext::site() derives it
        // from the authenticated user's own organization (the admin panel's
        // path, no ResolveRequestSite middleware involved).
        $this->assertSame(1, Project::query()->count());
        $this->assertTrue(Project::query()->first()->is($claesenProject));
    }
}
