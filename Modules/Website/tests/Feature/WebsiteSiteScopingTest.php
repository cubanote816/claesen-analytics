<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * F1/P3a of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers CLA-547: site_id on the shared Website domain, the site-scoped slug
 * uniqueness, and the deliberately inert BelongsToSite scope. Nothing here
 * asserts filtering or 403/404 — phase P3 restricts nothing, and asserting
 * otherwise would be asserting a feature that P5 owns.
 */
final class WebsiteSiteScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_shared_domain_tables_have_a_nullable_site_id_column(): void
    {
        foreach (['website_projects', 'website_consultation_requests'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'site_id'), "{$table} is missing site_id");

            $column = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = 'site_id'");
            $this->assertSame('YES', $column[0]->Null, "{$table}.site_id should stay nullable until phase P7");
        }
    }

    public function test_publication_states_is_deliberately_untouched_by_this_phase(): void
    {
        // P3b owns that table together with the PublicationState singleton.
        $this->assertFalse(Schema::hasColumn('website_publication_states', 'site_id'));
    }

    public function test_no_row_is_left_without_a_site_after_migrating(): void
    {
        $this->assertSame(0, DB::table('website_projects')->whereNull('site_id')->count());
        $this->assertSame(0, DB::table('website_consultation_requests')->whereNull('site_id')->count());
    }

    public function test_deleting_a_site_with_content_attached_is_restricted(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('sites')->where('id', $project->site_id)->delete();
    }

    public function test_new_records_default_to_the_claesen_site_via_the_factories(): void
    {
        $this->assertSame(Site::claesenId(), Project::factory()->create()->site_id);
        $this->assertSame(Site::claesenId(), ConsultationRequest::factory()->create()->site_id);
    }

    public function test_the_same_slug_is_allowed_on_two_different_sites(): void
    {
        $otherSite = Site::factory()->create(['key' => 'electro-bertels']);

        Project::factory()->create(['slug' => 'sportpark-noord']);
        $bertelsProject = Project::factory()->create([
            'slug' => 'sportpark-noord',
            'site_id' => $otherSite->id,
        ]);

        $this->assertSame('sportpark-noord', $bertelsProject->fresh()->slug);
        $this->assertSame(2, Project::withoutGlobalScopes()->where('slug', 'sportpark-noord')->count());
    }

    public function test_the_same_slug_is_still_rejected_within_one_site(): void
    {
        Project::factory()->create(['slug' => 'sportpark-noord']);

        $this->expectException(UniqueConstraintViolationException::class);

        Project::factory()->create(['slug' => 'sportpark-noord']);
    }

    public function test_the_site_relation_resolves(): void
    {
        $project = Project::factory()->create();

        $this->assertTrue($project->site->is(Site::query()->where('key', Site::CLAESEN_KEY)->first()));
        $this->assertSame('claesen', $project->site->organization->slug);
    }

    public function test_the_global_scope_is_inert_while_enforcement_is_off(): void
    {
        // ADR D4: structure → context → authorization → enforcement. The trait
        // ships in P3 without filtering anything; P5 turns the flag on.
        $this->assertFalse(config('organizations.enforce'));

        $otherSite = Site::factory()->create(['key' => 'electro-bertels']);
        Project::factory()->create(['site_id' => $otherSite->id]);
        Project::factory()->create();

        $this->assertSame(2, Project::query()->count());
    }
}
