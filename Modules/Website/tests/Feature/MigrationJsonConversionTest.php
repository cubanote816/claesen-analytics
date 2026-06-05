<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for 2026_06_02_000001_convert_location_client_to_json_in_website_projects.
 *
 * Uses the real migration file via require + down()/up() — no SQL copied to the test.
 * DatabaseMigrations is required because DDL changes (ALTER TABLE) cannot be
 * rolled back by RefreshDatabase's wrapping transaction in MySQL.
 */
class MigrationJsonConversionTest extends TestCase
{
    use DatabaseMigrations;

    private function getMigration(): object
    {
        return require base_path(
            'Modules/Website/Database/Migrations/2026_06_02_000001_convert_location_client_to_json_in_website_projects.php'
        );
    }

    private function insertProjectRow(string $location, string $client, string $slug = 'test-proj'): void
    {
        DB::table('website_projects')->insert([
            'slug'       => $slug,
            'title'      => json_encode(['nl' => 'Test Project', 'en' => 'Test Project']),
            'location'   => $location,
            'client'     => $client,
            'published'  => 0,
            'featured'   => 0,
            'order_index'=> 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // up(): plain string → {"nl": "value"}
    // =========================================================================

    public function test_up_wraps_plain_string_location_and_client_in_nl_json(): void
    {
        $migration = $this->getMigration();

        // Revert migration so location/client are plain VARCHAR columns
        $migration->down();

        $this->insertProjectRow('Hasselt, België', 'KV Mechelen');

        // Re-apply migration
        $migration->up();

        $row = DB::table('website_projects')->where('slug', 'test-proj')->first();

        $this->assertEquals(
            ['nl' => 'Hasselt, België'],
            json_decode($row->location, true)
        );
        $this->assertEquals(
            ['nl' => 'KV Mechelen'],
            json_decode($row->client, true)
        );
    }

    // =========================================================================
    // up(): already valid JSON object is preserved as-is
    // =========================================================================

    public function test_up_preserves_existing_json_object(): void
    {
        $migration = $this->getMigration();

        $migration->down();

        $this->insertProjectRow(
            json_encode(['nl' => 'Gent', 'en' => 'Ghent']),
            json_encode(['nl' => 'RSC Anderlecht', 'en' => 'RSC Anderlecht']),
            'test-proj-json'
        );

        $migration->up();

        $row = DB::table('website_projects')->where('slug', 'test-proj-json')->first();

        $this->assertEquals(
            ['nl' => 'Gent', 'en' => 'Ghent'],
            json_decode($row->location, true)
        );
        $this->assertEquals(
            ['nl' => 'RSC Anderlecht', 'en' => 'RSC Anderlecht'],
            json_decode($row->client, true)
        );
    }

    // =========================================================================
    // up(): NULL rows are not affected
    // =========================================================================

    public function test_up_leaves_null_columns_as_null(): void
    {
        $migration = $this->getMigration();

        $migration->down();

        DB::table('website_projects')->insert([
            'slug'       => 'test-null',
            'title'      => json_encode(['nl' => 'No Location']),
            'location'   => null,
            'client'     => null,
            'published'  => 0,
            'featured'   => 0,
            'order_index'=> 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $row = DB::table('website_projects')->where('slug', 'test-null')->first();

        $this->assertNull($row->location);
        $this->assertNull($row->client);
    }

    // =========================================================================
    // down(): JSON → plain nl string
    // =========================================================================

    public function test_down_extracts_nl_string_from_json(): void
    {
        // At this point all migrations have run; location/client are JSON columns.
        // Insert using Project::factory() or raw JSON directly.
        DB::table('website_projects')->insert([
            'slug'       => 'test-down',
            'title'      => json_encode(['nl' => 'Down Test']),
            'location'   => json_encode(['nl' => 'Brugge', 'en' => 'Bruges']),
            'client'     => json_encode(['nl' => 'Club Brugge', 'en' => 'Club Brugge']),
            'published'  => 0,
            'featured'   => 0,
            'order_index'=> 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = $this->getMigration();
        $migration->down();

        $row = DB::table('website_projects')->where('slug', 'test-down')->first();

        $this->assertSame('Brugge', $row->location);
        $this->assertSame('Club Brugge', $row->client);

        // Restore schema for clean tearDown
        $migration->up();
    }
}
