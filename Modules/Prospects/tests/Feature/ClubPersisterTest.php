<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
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

    public function test_persist_does_not_overwrite_existing_prospect_values(): void
    {
        // CLA-655 S3 (was: test_persist_updates_existing_prospect): fill-only.
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['name' => 'Original Name']));
        $again = $persister->persist($this->club('VL-RBFA-1', ['name' => 'Renamed Club']));

        $this->assertDatabaseCount('prospects_prospects', 1);
        $this->assertSame('Original Name', $again->fresh()->name);
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

    public function test_address_change_does_not_overwrite_existing_location_nor_duplicate(): void
    {
        // CLA-655 S3 (was: test_address_change_updates_existing_location_not_duplicate): fill-only.
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));
        $persister->persist($this->club('VL-RBFA-1', [
            'postalCode' => '2000',
            'headquarters' => ['address' => 'New Address 99, 2000 Antwerpen'],
        ]));

        $this->assertSame(1, ProspectLocation::query()->where('external_id', 'VL-RBFA-1::hq')->count());
        $this->assertDatabaseHas('prospects_locations', ['external_id' => 'VL-RBFA-1::hq', 'address' => 'Main Street 1, 2000 Antwerpen']);
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

    // ------------------------------------------------------------------
    // CLA-655 — reconcile with existing clubs, fill-only
    // ------------------------------------------------------------------

    public function test_s1_legacy_row_matched_by_vat_digits_gets_no_new_row_and_keeps_null_external_id(): void
    {
        $legacy = $this->legacy(['name' => 'Old Name', 'vat_number' => 'BE 0123.456.789', 'website' => null]);

        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', [
            'name' => 'New Name',
            'vatNumber' => '0123456789',
            'website' => 'https://club.test',
        ]));

        $this->assertDatabaseCount('prospects_prospects', 1);
        $this->assertSame($legacy->id, $prospect->id);
        $fresh = $legacy->fresh();
        $this->assertNull($fresh->external_id);
        $this->assertSame('Old Name', $fresh->name);
        $this->assertSame('https://club.test', $fresh->website);
    }

    public function test_s1_legacy_row_matched_by_normalized_name_when_vat_is_missing(): void
    {
        $legacy = $this->legacy(['name' => '  k.  Test   club ', 'vat_number' => null]);

        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'K. TEST CLUB', 'vatNumber' => '0123456789']));

        $this->assertDatabaseCount('prospects_prospects', 1);
        $this->assertSame($legacy->id, $prospect->id);
        $this->assertNull($legacy->fresh()->external_id);
        $this->assertSame('0123456789', $legacy->fresh()->vat_number);
    }

    public function test_s1_both_vat_present_but_different_does_not_match_by_name(): void
    {
        $this->legacy(['name' => 'Test Club', 'vat_number' => '0999999999']);

        (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'Test Club', 'vatNumber' => '0123456789']));

        $this->assertDatabaseCount('prospects_prospects', 2);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-1']);
    }

    public function test_s1_other_federation_tester_or_identified_rows_are_not_candidates(): void
    {
        $this->legacy(['name' => 'Test Club', 'federation' => 'FR-ACFF']);
        $this->legacy(['name' => 'Test Club', 'is_tester' => true]);
        $this->legacy(['name' => 'Test Club', 'external_id' => 'ARBH-X']);

        (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'Test Club']));

        $this->assertDatabaseCount('prospects_prospects', 4);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-1']);
    }

    public function test_s2_ambiguous_legacy_candidates_create_nothing_and_are_recorded(): void
    {
        $a = $this->legacy(['name' => 'Test Club']);
        $b = $this->legacy(['name' => 'TEST  club']);
        Log::spy();

        $report = (new ClubPersister)->reconcile($this->club('VL-RBFA-1', ['name' => 'Test Club']));

        $this->assertDatabaseCount('prospects_prospects', 2);
        $this->assertDatabaseCount('prospects_locations', 0);
        $this->assertNull($report['prospect']);
        $this->assertTrue($report['ambiguous']);
        $this->assertNull($a->fresh()->external_id);
        $this->assertNull($b->fresh()->external_id);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c = []) => str_contains($m.json_encode($c), 'VL-RBFA-1'))->atLeast()->once();
    }

    public function test_s2_persist_returns_null_for_ambiguous_club(): void
    {
        $this->legacy(['name' => 'Test Club']);
        $this->legacy(['name' => 'Test Club']);

        $this->assertNull((new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'Test Club'])));
    }

    public function test_s3_existing_external_id_row_only_fills_empty_columns(): void
    {
        $region = Region::firstOrCreate(['name' => 'Limburg'], ['slug' => 'limburg']);
        $existing = Prospect::create([
            'external_id' => 'VL-RBFA-1', 'name' => 'Kept', 'type' => 'football_club', 'federation' => 'VL-VV',
            'language' => 'nl', 'region_id' => $region->id, 'website' => 'https://kept.test',
            'logo_url' => '', 'vat_number' => null, 'contact_person' => 'Kept Person',
        ]);

        (new ClubPersister)->persist($this->club('VL-RBFA-1', [
            'name' => 'Other', 'website' => 'https://other.test', 'logoUrl' => 'https://logo.test/a.png',
            'vatNumber' => '0123456789', 'contactPerson' => 'Other Person', 'channel' => 'CH',
        ]));

        $fresh = $existing->fresh();
        $this->assertSame('Kept', $fresh->name);
        $this->assertSame('https://kept.test', $fresh->website);
        $this->assertSame('Kept Person', $fresh->contact_person);
        $this->assertSame('https://logo.test/a.png', $fresh->logo_url);
        $this->assertSame('0123456789', $fresh->vat_number);
        $this->assertSame('CH', $fresh->channel);
        $this->assertSame('Limburg', $fresh->region->name);
    }

    public function test_s3_existing_location_only_fills_empty_columns(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['headquarters' => ['address' => 'Kept 1', 'email' => 'kept@club.test']]));
        ProspectLocation::query()->where('external_id', 'VL-RBFA-1::hq')->update(['phone' => null]);

        $persister->persist($this->club('VL-RBFA-1', ['headquarters' => [
            'address' => 'Other 2', 'email' => 'other@club.test', 'phone' => '012', 'contactName' => 'Jan',
        ]]));

        $this->assertDatabaseCount('prospects_locations', 1);
        $this->assertDatabaseHas('prospects_locations', [
            'external_id' => 'VL-RBFA-1::hq', 'address' => 'Kept 1', 'email' => 'kept@club.test',
            'phone' => '012', 'contact_name' => 'Jan',
        ]);
    }

    public function test_s4_new_row_without_postal_uses_province_hint(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => null, 'regionHint' => 'Limburg']));

        $this->assertSame('Limburg', $prospect->region->name);
    }

    public function test_s4_postal_code_wins_over_province_hint(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => '2000', 'regionHint' => 'Limburg']));

        $this->assertSame('Antwerpen', $prospect->region->name);
    }

    public function test_s4_unknown_hint_and_no_postal_falls_back_to_overige(): void
    {
        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => null, 'regionHint' => 'Nowhere']));

        $this->assertSame('Overige', $prospect->region->name);
    }

    public function test_s4_existing_real_region_is_never_downgraded_to_overige(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => '2000']));

        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => null]));
        $this->assertSame('Antwerpen', Prospect::where('external_id', 'VL-RBFA-1')->firstOrFail()->region->name);

        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => '3500']));
        $this->assertSame('Antwerpen', Prospect::where('external_id', 'VL-RBFA-1')->firstOrFail()->region->name);
    }

    public function test_s4_overige_is_upgraded_to_a_real_region(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => null]));
        $this->assertSame('Overige', Prospect::where('external_id', 'VL-RBFA-1')->firstOrFail()->region->name);

        $persister->persist($this->club('VL-RBFA-1', ['postalCode' => '3500']));

        $this->assertSame('Limburg', Prospect::where('external_id', 'VL-RBFA-1')->firstOrFail()->region->name);
    }

    public function test_s4_legacy_overige_row_is_upgraded_via_hint(): void
    {
        $legacy = $this->legacy(['name' => 'Test Club'], 'Overige');

        (new ClubPersister)->persist($this->club('VL-RBFA-1', ['postalCode' => null, 'regionHint' => 'Limburg']));

        $this->assertSame('Limburg', $legacy->fresh()->region->name);
    }

    public function test_s5_email_collision_skips_location_keeps_prospect_and_records_it(): void
    {
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['headquarters' => ['address' => 'A 1', 'email' => 'dup@club.test']]));
        Log::spy();

        $report = $persister->reconcile($this->club('VL-RBFA-2', [
            'headquarters' => ['address' => 'B 2', 'email' => 'dup@club.test'],
            'venues' => [['address' => 'Venue 3', 'email' => 'venue@club.test', 'sourceLocationId' => 'V1']],
        ]));

        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-RBFA-2']);
        $this->assertDatabaseMissing('prospects_locations', ['external_id' => 'VL-RBFA-2::hq']);
        $this->assertDatabaseHas('prospects_locations', ['external_id' => 'VL-RBFA-2::V1']);
        $this->assertSame(['VL-RBFA-2::hq'], array_column($report['skipped_locations'], 'location'));
        $this->assertFalse($report['ambiguous']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c = []) => str_contains($m.json_encode($c), 'dup@club.test'))->atLeast()->once();
    }

    public function test_s5_legacy_row_skips_hq_when_email_exists_but_creates_it_when_email_is_new(): void
    {
        $legacy = $this->legacy(['name' => 'Test Club']);
        $other = $this->legacy(['name' => 'Another Club']);
        ProspectLocation::create(['prospect_id' => $other->id, 'contact_type' => 'headquarters', 'address' => 'x', 'email' => 'taken@club.test']);

        $report = (new ClubPersister)->reconcile($this->club('VL-RBFA-1', [
            'name' => 'Test Club', 'headquarters' => ['address' => 'A 1', 'email' => 'taken@club.test'],
        ]));
        $this->assertCount(1, $report['skipped_locations']);
        $this->assertSame(0, $legacy->locations()->count());

        (new ClubPersister)->persist($this->club('VL-RBFA-1', [
            'name' => 'Test Club', 'headquarters' => ['address' => 'A 1', 'email' => 'fresh@club.test'],
        ]));
        $this->assertDatabaseHas('prospects_locations', [
            'prospect_id' => $legacy->id, 'external_id' => 'VL-RBFA-1::hq', 'email' => 'fresh@club.test',
        ]);
        $this->assertNull($legacy->fresh()->external_id);
    }

    public function test_s5_email_held_by_the_same_prospect_is_not_a_conflict_and_records_nothing(): void
    {
        $legacy = $this->legacy(['name' => 'Test Club']);
        ProspectLocation::create(['prospect_id' => $legacy->id, 'contact_type' => 'headquarters', 'address' => 'x', 'email' => 'same@club.test']);
        $club = $this->club('VL-RBFA-1', ['name' => 'Test Club', 'headquarters' => ['address' => 'A 1', 'email' => 'same@club.test']]);
        Log::spy();

        $first = (new ClubPersister)->reconcile($club);
        $second = (new ClubPersister)->reconcile($club);

        $this->assertSame([], $first['skipped_locations']);
        $this->assertSame([], $second['skipped_locations']);
        $this->assertSame(1, $legacy->locations()->count());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_s1_name_match_ignores_case_and_inner_whitespace_in_sql_narrowing(): void
    {
        $legacy = $this->legacy(['name' => "\tk.  voetbal\u{00A0}club  ", 'vat_number' => null]);
        $this->legacy(['name' => 'Unrelated Club', 'vat_number' => null]);

        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'K. Voetbal Club']));

        $this->assertSame($legacy->id, $prospect?->id);
        $this->assertDatabaseCount('prospects_prospects', 2);
    }

    public function test_s1b_historical_nine_digit_vat_matches_incoming_vat_with_leading_zero(): void
    {
        $legacy = $this->legacy(['name' => 'Old Name', 'vat_number' => 'BE0421.753.822']);
        $token = $legacy->getUnsubscribeToken();
        $club = $this->club('VL-RBFA-1', ['name' => 'Different Name', 'vatNumber' => '421753822']);

        $first = (new ClubPersister)->persist($club);
        $second = (new ClubPersister)->persist($club);

        $this->assertSame($legacy->id, $first?->id);
        $this->assertSame($legacy->id, $second?->id);
        $this->assertDatabaseCount('prospects_prospects', 1);
        $this->assertNull($legacy->fresh()->external_id);
        $this->assertSame($token, $legacy->fresh()->getUnsubscribeToken());
    }

    public function test_s1b_leading_zero_vat_matches_in_the_sql_narrowing_path(): void
    {
        $legacy = $this->legacy(['name' => 'Old Name', 'vat_number' => '0421753822']);

        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'Different Name', 'vatNumber' => 'BE 0421 753 822']));

        $this->assertSame($legacy->id, $prospect?->id);
        $this->assertDatabaseCount('prospects_prospects', 1);
    }

    public function test_s1b_all_zero_vat_counts_as_no_vat_and_falls_back_to_name(): void
    {
        $legacy = $this->legacy(['name' => 'Test Club', 'vat_number' => '0421753822']);

        $prospect = (new ClubPersister)->persist($this->club('VL-RBFA-1', ['name' => 'Test Club', 'vatNumber' => '000']));

        $this->assertSame($legacy->id, $prospect?->id);
    }

    public function test_s5_existing_location_keeps_colliding_email_empty_but_fills_other_fields_and_records_email_skip(): void
    {
        $holder = $this->legacy(['name' => 'Holder']);
        ProspectLocation::create(['prospect_id' => $holder->id, 'contact_type' => 'headquarters', 'address' => 'x', 'email' => 'taken@club.test']);
        $persister = new ClubPersister;
        $persister->persist($this->club('VL-RBFA-1', ['headquarters' => ['address' => 'A 1']]));
        Log::spy();

        $report = $persister->reconcile($this->club('VL-RBFA-1', ['headquarters' => [
            'address' => 'Other 9', 'email' => 'taken@club.test', 'phone' => '012', 'contactName' => 'Jan',
        ]]));

        $this->assertDatabaseHas('prospects_locations', [
            'external_id' => 'VL-RBFA-1::hq', 'email' => null, 'phone' => '012', 'contact_name' => 'Jan', 'address' => 'A 1',
        ]);
        $this->assertSame([], $report['skipped_locations']);
        $this->assertSame([['location' => 'VL-RBFA-1::hq', 'email' => 'taken@club.test']], $report['skipped_emails']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c = []) => str_contains($m, 'Email') && str_contains($m, 'taken@club.test'))->atLeast()->once();
    }

    public function test_s5_non_collision_failure_rolls_back_the_whole_club(): void
    {
        ProspectLocation::creating(function (): void {
            throw new RuntimeException('forced location failure');
        });

        try {
            (new ClubPersister)->persist($this->club('VL-RBFA-1'));
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced location failure', $e->getMessage());
        }

        $this->assertDatabaseCount('prospects_prospects', 0);
        $this->assertDatabaseCount('prospects_locations', 0);
    }

    public function test_s6_persist_all_logs_each_failure_with_external_id_and_message(): void
    {
        Prospect::creating(function (): void {
            throw new RuntimeException('forced failure');
        });
        Log::spy();

        $tally = (new ClubPersister)->persistAll([$this->club('VL-RBFA-1')]);

        $this->assertSame(['persisted' => 0, 'failed' => 1], $tally);
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($m, $c = []) => str_contains($m.json_encode($c), 'VL-RBFA-1') && str_contains($m.json_encode($c), 'forced failure')
        )->once();
    }

    public function test_s8_persisting_the_same_clubs_twice_changes_nothing(): void
    {
        $this->legacy(['name' => 'Legacy Club', 'vat_number' => '0123456789']);
        $clubs = fn () => [
            $this->club('VL-RBFA-1', ['vatNumber' => '0123456789', 'name' => 'Legacy Club', 'headquarters' => ['address' => 'A 1', 'email' => 'a@club.test']]),
            $this->club('VL-RBFA-2', ['postalCode' => null, 'regionHint' => 'Limburg', 'headquarters' => ['address' => 'B 2', 'email' => 'b@club.test'],
                'venues' => [['address' => 'V 1']]]),
        ];
        $snapshot = fn () => [
            Prospect::query()->orderBy('id')->get()->toArray(),
            ProspectLocation::query()->orderBy('id')->get()->toArray(),
        ];

        (new ClubPersister)->persistAll($clubs());
        $first = $snapshot();
        (new ClubPersister)->persistAll($clubs());

        $this->assertSame($first, $snapshot());
    }

    private function legacy(array $attributes = [], string $region = 'Antwerpen'): Prospect
    {
        $regionId = Region::firstOrCreate(['name' => $region], ['slug' => str($region)->slug()])->id;

        return Prospect::create(array_replace([
            'name' => 'Legacy', 'type' => 'football_club', 'federation' => 'VL-VV', 'language' => 'nl',
            'region_id' => $regionId, 'external_id' => null, 'is_tester' => false,
        ], $attributes));
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
