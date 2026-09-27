<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

class ProspectLocationKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_index_allows_null_external_id(): void
    {
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);
        $prospect = Prospect::create(['name' => 'Lead Prospect', 'type' => 'lead', 'channel' => 'website_contact', 'region_id' => $region->id]);

        ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'primary',
            'email' => 'lead-one@example.test',
        ]);
        ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'contact_type' => 'venue_name',
            'email' => 'lead-two@example.test',
        ]);

        $this->assertDatabaseCount('prospects_locations', 2);
    }

    public function test_unique_index_rejects_duplicate_external_id(): void
    {
        $region = Region::firstOrCreate(['name' => 'Antwerpen'], ['slug' => 'antwerpen']);
        $prospect = Prospect::create(['name' => 'Duplicate Test', 'type' => 'football_club', 'federation' => 'VL-VV', 'language' => 'nl', 'external_id' => 'VL-RBFA-DUP', 'region_id' => $region->id]);

        ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'external_id' => 'VL-RBFA-DUP::hq',
            'contact_type' => 'headquarters',
            'email' => 'dup-one@example.test',
        ]);

        $this->expectException(QueryException::class);

        ProspectLocation::create([
            'prospect_id' => $prospect->id,
            'external_id' => 'VL-RBFA-DUP::hq',
            'contact_type' => 'venue_name',
            'email' => 'dup-two@example.test',
        ]);
    }
}
