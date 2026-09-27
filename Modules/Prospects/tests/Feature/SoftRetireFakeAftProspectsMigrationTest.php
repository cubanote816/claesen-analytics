<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class SoftRetireFakeAftProspectsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_aft_prospects_are_soft_retired(): void
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);

        $fake = Prospect::create([
            'external_id' => 'FR-AFT-tc-de-wavre',
            'name' => 'TC de Wavre',
            'type' => 'tennis_padel_club',
            'federation' => 'FR-AFT',
            'language' => 'fr',
            'region_id' => $region->id,
        ]);

        $this->runMigration();

        $this->assertNotNull($fake->fresh()->unsubscribed_at);
    }

    public function test_unrelated_prospects_are_not_retired(): void
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);

        $real = Prospect::create([
            'external_id' => 'FR-AFTT-BBW015',
            'name' => 'Real Club',
            'type' => 'table_tennis_club',
            'federation' => 'FR-AFTT',
            'language' => 'fr',
            'region_id' => $region->id,
        ]);

        $this->runMigration();

        $this->assertNull($real->fresh()->unsubscribed_at);
    }

    private function runMigration(): void
    {
        $path = 'Modules/Prospects/database/migrations/2026_09_14_100200_soft_retire_fake_aft_prospects.php';
        DB::table('migrations')->where('migration', basename($path, '.php'))->delete();
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
}
