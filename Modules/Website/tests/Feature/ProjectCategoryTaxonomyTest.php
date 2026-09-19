<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use App\Filament\Clusters\Website\Resources\ProjectResource\Pages\ListProjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\Project;
use Modules\Website\Models\ProjectCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F3/CLA-468 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Replaces the hardcoded, global Modules\Website\App\Enums\ProjectCategory
 * (deleted by this ticket) with a site-owned catalog table. No real Bertels
 * site exists yet (ADR D10) — its six categories (residencial/empresa/
 * industria/KNX-Qbus/iluminación/renovación) are exercised here only
 * through a throwaway fixture site (InteractsWithOrganizationFixtures,
 * CLA-457), proving the mechanism supports an arbitrary, independent list
 * per site rather than seeding anything real.
 */
final class ProjectCategoryTaxonomyTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_claesens_three_categories_are_preserved_with_their_existing_translations(): void
    {
        $categories = ProjectCategory::query()
            ->where('site_id', Site::claesenId())
            ->ordered()
            ->get();

        $this->assertSame(['sport', 'industrial', 'public'], $categories->pluck('slug')->all());

        $sport = $categories->firstWhere('slug', 'sport');
        // assertEquals, not assertSame: PHP's === on arrays also compares key
        // order, which the JSON column's own round-trip does not preserve —
        // only the key/value pairs matter here.
        $this->assertEquals([
            'nl' => 'Sport', 'en' => 'Sport', 'fr' => 'Sport', 'de' => 'Sport',
        ], $sport->getTranslations('name'));

        $industrial = $categories->firstWhere('slug', 'industrial');
        $this->assertSame('Industrieel', $industrial->getTranslation('name', 'nl'));
        $this->assertSame('Industrial', $industrial->getTranslation('name', 'en'));
        $this->assertSame('Industriel', $industrial->getTranslation('name', 'fr'));
        $this->assertSame('Industrie', $industrial->getTranslation('name', 'de'));

        $public = $categories->firstWhere('slug', 'public');
        $this->assertSame('Openbaar', $public->getTranslation('name', 'nl'));
        $this->assertSame('Öffentlich', $public->getTranslation('name', 'de'));
    }

    public function test_a_fixture_bertels_site_can_have_its_own_independent_six_categories(): void
    {
        $bertelsSite = $this->bertelsFixtureSite();

        $slugs = ['residencial', 'empresa', 'industria', 'knx-qbus', 'iluminacion', 'renovacion'];

        foreach ($slugs as $index => $slug) {
            ProjectCategory::factory()->create([
                'site_id' => $bertelsSite->id,
                'slug' => $slug,
                'name' => ['nl' => $slug, 'en' => $slug],
                'order_index' => $index,
            ]);
        }

        $bertelsCategories = ProjectCategory::query()
            ->where('site_id', $bertelsSite->id)
            ->ordered()
            ->pluck('slug');

        $this->assertSame($slugs, $bertelsCategories->all());

        // Claesen's own catalog is completely unaffected by Bertels having a
        // same-shaped-but-different list — no shared global enum forcing
        // either site to see the other's categories.
        $this->assertSame(
            ['sport', 'industrial', 'public'],
            ProjectCategory::query()->where('site_id', Site::claesenId())->ordered()->pluck('slug')->all()
        );
    }

    public function test_the_same_slug_is_allowed_on_two_different_sites_with_independent_translations(): void
    {
        $bertelsSite = $this->bertelsFixtureSite();

        // "public" already exists for Claesen (seeded) — proving the unique
        // constraint is scoped per site, not global, and that the same slug
        // can carry a genuinely different translation per site.
        ProjectCategory::factory()->create([
            'site_id' => $bertelsSite->id,
            'slug' => 'public',
            'name' => ['nl' => 'Openbare ruimte', 'en' => 'Public space'],
        ]);

        $claesenPublic = ProjectCategory::query()->where('site_id', Site::claesenId())->where('slug', 'public')->firstOrFail();
        $bertelsPublic = ProjectCategory::query()->where('site_id', $bertelsSite->id)->where('slug', 'public')->firstOrFail();

        $this->assertSame('Openbaar', $claesenPublic->getTranslation('name', 'nl'));
        $this->assertSame('Openbare ruimte', $bertelsPublic->getTranslation('name', 'nl'));
    }

    public function test_the_global_project_category_enum_no_longer_exists(): void
    {
        // F3/CLA-468's own point: no global enum should exist to force both
        // sites' categories into the same fixed list.
        $this->assertFalse(class_exists(\Modules\Website\App\Enums\ProjectCategory::class));
    }

    public function test_projects_category_column_stays_a_plain_slug_string_not_an_enum(): void
    {
        $project = Project::factory()->create(['category' => 'sport']);

        $this->assertIsString($project->category);
        $this->assertSame('sport', $project->category);
    }

    public function test_existing_project_category_values_remain_valid_after_the_migration(): void
    {
        // "Migración compatible con datos actuales" — the column's values
        // were never touched, only what governs which slugs are valid moved
        // from a hardcoded enum to catalog rows carrying the exact same
        // three slugs. A project created with any of them stays queryable
        // and filterable exactly as before.
        foreach (['sport', 'industrial', 'public'] as $slug) {
            Project::factory()->create(['category' => $slug]);
        }

        $this->assertSame(3, Project::query()->whereIn('category', ['sport', 'industrial', 'public'])->count());
    }

    // ─── Filament: filtering and ordering are driven by the live catalog ────

    public function test_the_category_select_filter_lists_claesens_categories_in_order(): void
    {
        app()->setLocale('nl');

        $user = $this->claesenUser();
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        $this->actingAs($user);

        Livewire::test(ListProjects::class)
            ->assertOk();

        // Same mechanism ProjectResource::form()/table() call — invoked via
        // reflection since it's a private implementation detail, not a
        // public contract (same pattern already used elsewhere in this repo
        // for protected Filament page methods, e.g. UserProvisioningTest).
        $method = new \ReflectionMethod(ProjectResource::class, 'categoryOptions');
        $method->setAccessible(true);

        $this->assertSame(
            ['sport' => 'Sport', 'industrial' => 'Industrieel', 'public' => 'Openbaar'],
            $method->invoke(null)
        );
    }

    public function test_reordering_a_categorys_order_index_changes_the_options_order(): void
    {
        // order_index is unsigned (no legitimate use for a negative
        // position) — swap 'public' (seeded at 2) ahead of 'sport' (seeded
        // at 0) using only valid non-negative values.
        $public = ProjectCategory::query()->where('site_id', Site::claesenId())->where('slug', 'public')->firstOrFail();
        $sport = ProjectCategory::query()->where('site_id', Site::claesenId())->where('slug', 'sport')->firstOrFail();

        $public->update(['order_index' => 0]);
        $sport->update(['order_index' => 2]);

        $this->assertSame(
            ['public', 'industrial', 'sport'],
            ProjectCategory::query()->where('site_id', Site::claesenId())->ordered()->pluck('slug')->all()
        );
    }
}
