<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Tests\TestCase;

/**
 * F0/P1 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers CLA-458's acceptance criteria directly: schema shape, stable
 * public keys, idempotent Claesen seed, factories, and that the down()
 * guards actually hold rather than just reading well in the migration file.
 */
final class OrganizationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_organizations_table_has_the_expected_columns(): void
    {
        foreach (['id', 'slug', 'name', 'status', 'retention_policy', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('organizations', $column),
                "organizations is missing column [{$column}]"
            );
        }
    }

    public function test_the_sites_table_has_the_expected_columns(): void
    {
        foreach (['id', 'organization_id', 'key', 'domain', 'default_locale', 'locales', 'status', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('sites', $column),
                "sites is missing column [{$column}]"
            );
        }
    }

    public function test_claesen_organization_and_site_exist_after_migrating(): void
    {
        $organization = Organization::query()->where('slug', 'claesen')->first();

        $this->assertNotNull($organization, 'The Claesen organization bootstrap row is missing.');
        $this->assertSame('Claesen Verlichting', $organization->name);
        $this->assertSame(Organization::STATUS_ACTIVE, $organization->status);
        $this->assertNull($organization->retention_policy);

        $site = Site::query()->where('key', 'claesen-verlichting')->first();

        $this->assertNotNull($site, 'The Claesen site bootstrap row is missing.');
        $this->assertTrue($site->organization->is($organization));
        $this->assertSame('nl', $site->default_locale);
        $this->assertSame(['nl', 'en', 'fr', 'de'], $site->locales);
        $this->assertSame(Site::STATUS_ACTIVE, $site->status);
        $this->assertNull($site->domain);
    }

    public function test_public_keys_are_the_stable_identifier_not_the_sequential_id(): void
    {
        // CLA-458: "Claves públicas no dependientes de IDs secuenciales."
        // Asserting this holds even if a fresh migrate ever assigns Claesen
        // a different auto-increment id than 1.
        $organization = Organization::query()->where('slug', 'claesen')->firstOrFail();
        $site = Site::query()->where('key', 'claesen-verlichting')->firstOrFail();

        $this->assertSame('claesen', $organization->slug);
        $this->assertSame('claesen-verlichting', $site->key);
        $this->assertSame($organization->id, $site->organization_id);
    }

    public function test_rerunning_the_seed_migration_up_stays_idempotent(): void
    {
        $before = [
            'organizations' => Organization::query()->count(),
            'sites' => Site::query()->count(),
        ];

        (require base_path('Modules/Core/database/migrations/2026_09_17_100100_seed_claesen_organization_and_site.php'))->up();

        $this->assertSame($before['organizations'], Organization::query()->count());
        $this->assertSame($before['sites'], Site::query()->count());
        $this->assertSame(1, Organization::query()->where('slug', 'claesen')->count());
        $this->assertSame(1, Site::query()->where('key', 'claesen-verlichting')->count());
    }

    public function test_the_seed_migration_down_is_a_non_destructive_no_op(): void
    {
        (require base_path('Modules/Core/database/migrations/2026_09_17_100100_seed_claesen_organization_and_site.php'))->down();

        $this->assertNotNull(Organization::query()->where('slug', 'claesen')->first());
        $this->assertNotNull(Site::query()->where('key', 'claesen-verlichting')->first());
    }

    public function test_the_structure_migration_down_refuses_to_run_with_a_foreign_organization_present(): void
    {
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to drop organizations/sites');

        (require base_path('Modules/Core/database/migrations/2026_09_17_100000_create_organizations_and_sites_tables.php'))->down();

        $this->assertTrue(Schema::hasTable('organizations'), 'organizations must not have been dropped.');
        $this->assertTrue(Schema::hasTable('sites'), 'sites must not have been dropped.');
    }

    public function test_organization_slug_is_unique(): void
    {
        Organization::factory()->create(['slug' => 'duplicate-slug']);

        $this->expectException(UniqueConstraintViolationException::class);

        Organization::factory()->create(['slug' => 'duplicate-slug']);
    }

    public function test_site_key_is_unique(): void
    {
        Site::factory()->create(['key' => 'duplicate-key']);

        $this->expectException(UniqueConstraintViolationException::class);

        Site::factory()->create(['key' => 'duplicate-key']);
    }

    public function test_deleting_an_organization_with_a_site_is_restricted(): void
    {
        $site = Site::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('organizations')->where('id', $site->organization_id)->delete();
    }

    public function test_factories_build_valid_organizations_and_sites(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->for($organization)->create();

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'organization_id' => $organization->id]);
        $this->assertTrue($site->organization->is($organization));
    }

    public function test_the_suspended_factory_states_set_the_expected_status(): void
    {
        $this->assertSame(Organization::STATUS_SUSPENDED, Organization::factory()->suspended()->create()->status);
        $this->assertSame(Site::STATUS_SUSPENDED, Site::factory()->suspended()->create()->status);
    }

    public function test_this_migration_never_added_an_organization_id_column_to_website(): void
    {
        // CLA-458 (P1): "Ningún dato Website se mezcla durante el despliegue."
        // organization_id was never, and per D3 never will be, added directly
        // to site-owned tables — the organization is always derived through
        // sites.organization_id.
        //
        // site_id is a DIFFERENT phase's promise (P3a/CLA-547) and IS present
        // on these two tables since then — declared inversion, found stale in
        // CLA-549 (docs/ai/known-risks.md → "Deuda técnica"): this test
        // originally asserted site_id's absence too, which stopped being true
        // the moment CLA-547 shipped without anyone updating this assertion.
        $this->assertFalse(Schema::hasColumn('website_projects', 'organization_id'));
        $this->assertFalse(Schema::hasColumn('website_consultation_requests', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('website_projects', 'site_id'));
        $this->assertTrue(Schema::hasColumn('website_consultation_requests', 'site_id'));
    }

    public function test_the_organizations_config_scaffolding_defaults_to_disabled_and_empty(): void
    {
        // ADR D4/D5: enforcement off, no platform abilities registered yet
        // — every ability check in the repo still passes a model or a
        // class-string. `owned_modules` (D9) is the one exception, declared
        // inverted here on purpose: it stopped being an empty placeholder
        // the moment CLA-552 (P5b) populated it with the Claesen-only
        // module list. Same pattern as the site_id assertion above (CLA-547
        // invalidating a P1-era assumption) — a real, later phase legitimately
        // changing what a structure-only P1 test could assert.
        $this->assertFalse(config('organizations.enforce'));
        $this->assertSame([], config('organizations.platform_abilities'));
        $this->assertSame([
            'fieldops', 'safety', 'employees', 'mailing',
            'intelligence', 'performance', 'prospects', 'cafca',
        ], config('organizations.owned_modules.claesen'));
    }
}
