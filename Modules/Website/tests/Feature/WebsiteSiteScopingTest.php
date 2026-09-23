<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Site;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Tests\TestCase;

/**
 * F1/P3a+P3b of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers CLA-547 (P3a): site_id on the shared Website domain, the site-scoped
 * slug uniqueness, and BelongsToSite's global scope. Also covers CLA-548
 * (P3b): site_id on website_publication_states and the singleton-per-site
 * rework of PublicationState::current(). The scope's real filtering
 * behaviour (once F3/CLA-471 gave it an implementation) is covered by
 * Modules\Website\tests\Feature\PublicApiSiteScopingTest and
 * Modules\Core\tests\Feature\BelongsToSiteEnforcementTest instead — this
 * file keeps only the "still inert with the flag off" case below.
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

    public function test_publication_states_has_a_nullable_unique_site_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('website_publication_states', 'site_id'));

        $column = DB::select("SHOW COLUMNS FROM website_publication_states WHERE Field = 'site_id'");
        $this->assertSame('YES', $column[0]->Null, 'website_publication_states.site_id should stay nullable until phase P7');

        $indexes = collect(DB::select('SHOW INDEX FROM website_publication_states WHERE Column_name = "site_id"'));
        $this->assertTrue($indexes->contains(fn ($index) => (int) $index->Non_unique === 0), 'site_id must carry a UNIQUE index (D3: one publication-state row per site)');
    }

    public function test_current_without_a_site_argument_resolves_to_the_claesen_row(): void
    {
        $state = PublicationState::current();

        $this->assertSame(Site::claesenId(), $state->site_id);
        $this->assertSame(PublicationStatus::IDLE, $state->status);
        $this->assertSame(1, PublicationState::query()->count());

        // Idempotent: calling it again returns the same row, not a new one.
        $this->assertTrue($state->is(PublicationState::current()));
        $this->assertSame(1, PublicationState::query()->count());
    }

    public function test_current_with_a_different_site_creates_a_distinct_row(): void
    {
        $otherSite = Site::factory()->create(['key' => 'electro-bertels']);

        $claesenState = PublicationState::current();
        $bertelsState = PublicationState::current($otherSite->id);

        $this->assertFalse($claesenState->is($bertelsState));
        $this->assertSame($otherSite->id, $bertelsState->site_id);
        $this->assertSame(2, PublicationState::query()->count());
    }

    public function test_deleting_a_site_with_a_publication_state_attached_is_restricted(): void
    {
        $state = PublicationState::current();

        $this->expectException(QueryException::class);

        DB::table('sites')->where('id', $state->site_id)->delete();
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
