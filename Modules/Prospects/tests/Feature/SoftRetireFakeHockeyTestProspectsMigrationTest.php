<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class SoftRetireFakeHockeyTestProspectsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_hockey_test_prospects_are_soft_retired(): void
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);

        $testAssociation1 = Prospect::create([
            'external_id' => 'VL-HOCKEY-CD7MX2C',
            'name' => 'Testvereniging 1',
            'type' => 'hockey_club',
            'federation' => 'VL-VHL',
            'language' => 'nl',
            'region_id' => $region->id,
        ]);

        $testAssociation2 = Prospect::create([
            'external_id' => 'FR-HOCKEY-CD7MX4E',
            'name' => 'Testvereniging 2',
            'type' => 'hockey_club',
            'federation' => 'FR-LFH',
            'language' => 'fr',
            'region_id' => $region->id,
        ]);

        $this->runMigration();

        $this->assertNotNull($testAssociation1->fresh()->unsubscribed_at);
        $this->assertNotNull($testAssociation2->fresh()->unsubscribed_at);
    }

    public function test_unrelated_hockey_prospects_are_not_retired(): void
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);

        $real = Prospect::create([
            'external_id' => 'VL-HOCKEY-CC6VG4O',
            'name' => 'Aalst',
            'type' => 'hockey_club',
            'federation' => 'VL-VHL',
            'language' => 'nl',
            'region_id' => $region->id,
        ]);

        $this->runMigration();

        $this->assertNull($real->fresh()->unsubscribed_at);
    }

    private function runMigration(): void
    {
        $path = 'Modules/Prospects/database/migrations/2026_09_16_090000_soft_retire_fake_hockey_test_prospects.php';
        DB::table('migrations')->where('migration', basename($path, '.php'))->delete();
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
}
