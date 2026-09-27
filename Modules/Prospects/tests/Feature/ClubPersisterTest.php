<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Services\ClubPersister;
use RuntimeException;
use Tests\TestCase;

class ClubPersisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_upserts_prospect_by_external_id(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));

        $this->assertSame('VL-RBFA-1', $prospect->external_id);
        $this->assertDatabaseCount('prospects_prospects', 1);
    }

    public function test_persist_updates_existing_prospect(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['name' => 'Original Name']));
        $updated = $persister->persist($this->club('VL-RBFA-1', ['name' => 'Renamed Club']));

        $this->assertDatabaseCount('prospects_prospects', 1);
        $this->assertSame('Renamed Club', $updated->fresh()->name);
    }

    public function test_persist_uses_region_from_postal_code(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));

        $this->assertSame('Antwerpen', $prospect->region->name);
    }

    public function test_persist_falls_back_to_overige_on_null_postal(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => null]));

        $this->assertSame('Overige', $prospect->region->name);
    }

    public function test_persist_headquarters_location_uses_external_id_key(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));

        $this->assertDatabaseHas('prospects_locations', [
            'prospect_id' => $prospect->id,
            'external_id' => 'VL-RBFA-1::hq',
            'contact_type' => 'headquarters',
        ]);
    }

    public function test_persist_venue_locations_use_external_id_key(): void
    {
        $club = $this->club('VL-RBFA-1', [
            'venues' => [
                ['address' => 'Venue Street 1, 2000 Antwerpen', 'sourceLocationId' => 'V1'],
                ['address' => 'Venue Street 2, 2000 Antwerpen'],
            ],
        ]);

        $prospect = (new ClubPersister)->persist($club);

        $this->assertDatabaseHas('prospects_locations', [
            'prospect_id' => $prospect->id,
            'external_id' => 'VL-RBFA-1::V1',
            'contact_type' => 'venue_name',
        ]);
        $this->assertDatabaseHas('prospects_locations', [
            'prospect_id' => $prospect->id,
            'external_id' => 'VL-RBFA-1::v2',
            'contact_type' => 'venue_name',
        ]);
    }

    public function test_address_change_updates_existing_location_not_duplicate(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));
        $persister->persist($this->club('VL-RBFA-1', [
            'postalCode' => '2000',
            'headquarters' => ['address' => 'New Address 99, 2000 Antwerpen'],
        ]));

        $this->assertSame(1, ProspectLocation::query()->where('external_id', 'VL-RBFA-1::hq')->count());
        $this->assertDatabaseHas('prospects_locations', ['external_id' => 'VL-RBFA-1::hq', 'address' => 'New Address 99, 2000 Antwerpen']);
    }

    public function test_persist_all_returns_correct_tallies(): void
    {
        $attempt = 0;
        Prospect::creating(function () use (&$attempt): void {
            $attempt++;
            if ($attempt === 2) {
                throw new RuntimeException('forced failure');
            }
        });

        $tally = (new ClubPersister)->persistAll([
            $this->club('VL-RBFA-1'),
            $this->club('VL-RBFA-2'),
            $this->club('VL-RBFA-3'),
        ]);

        $this->assertSame(['persisted' => 2, 'failed' => 1], $tally);
    }

    private function club(string $externalId, array $overrides = []): NormalizedClub
    {
        return NormalizedClub::fromArray(array_replace([
            'externalId' => $externalId,
            'federation' => 'VL-VV',
            'name' => 'Test Club',
            'type' => 'football_club',
            'language' => 'nl',
            'postalCode' => '2000',
            'headquarters' => ['address' => 'Main Street 1, 2000 Antwerpen'],
        ], $overrides));
    }
}
