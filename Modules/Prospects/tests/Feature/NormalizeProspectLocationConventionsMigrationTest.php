<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class NormalizeProspectLocationConventionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_address_federations_get_headquarters_contact_type(): void
    {
        $prospect = $this->prospect('VL-TPV-1', 'VL-TPV');
        $location = ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'venue_name',
            'address' => 'Club Street 1',
        ]);

        $this->rerunConventionsMigration();

        $this->assertSame('headquarters', $location->fresh()->contact_type);
    }

    public function test_val_venue_locations_are_not_re_stamped(): void
    {
        $prospect = $this->prospect('VL-slug', 'VL-VAL');
        $location = ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'venue_name',
            'address' => 'Terrein 1',
        ]);

        $this->rerunConventionsMigration();

        $this->assertSame('venue_name', $location->fresh()->contact_type);
    }

    public function test_legacy_arbh_external_id_gains_region_prefix(): void
    {
        $prospect = $this->prospect('HOCKEY-CG4TQ46', 'ARBH-KBHB');

        $this->rerunConventionsMigration();

        $this->assertSame('ARBH-HOCKEY-CG4TQ46', $prospect->fresh()->external_id);
    }

    public function test_duplicate_val_locations_by_address_are_deduplicated(): void
    {
        $prospect = $this->prospect('VL-dup-slug', 'VL-VAL');
        $keep = ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'venue_name',
            'address' => 'Same Address 1',
        ]);
        ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'venue_name',
            'address' => 'Same Address 1',
        ]);

        $this->rerunConventionsMigration();

        $this->assertSame(1, ProspectLocation::query()->where('prospect_id', $prospect->id)->count());
        $this->assertTrue(ProspectLocation::query()->whereKey($keep->id)->exists());
    }

    private function prospect(string $externalId, string $federation): Prospect
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);

        return Prospect::create([
            'external_id' => $externalId,
            'name' => 'Test Club',
            'type' => 'football_club',
            'federation' => $federation,
            'language' => 'nl',
            'region_id' => $region->id,
        ]);
    }

    private function rerunConventionsMigration(): void
    {
        $path = 'Modules/Prospects/database/migrations/2026_09_14_100100_normalize_prospect_location_conventions.php';
        DB::table('migrations')->where('migration', basename($path, '.php'))->delete();
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
}
