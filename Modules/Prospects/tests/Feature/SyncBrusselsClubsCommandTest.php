<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Prospects\Console\Commands\SyncBrusselsClubsCommand;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\DataSource\BrusselsCadastreSource;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class SyncBrusselsClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $suffix): NormalizedClub
    {
        return NormalizedClub::fromArray([
            'externalId' => "BR-CAD-{$suffix}",
            'federation' => 'BR-CAD',
            'name' => "Complex {$suffix}",
            'type' => 'sports_infrastructure',
            'language' => 'nl',
            'postalCode' => '1000',
            'venues' => [['address' => "Teststraat {$suffix}, 1000 Bruxelles", 'sourceLocationId' => $suffix]],
        ]);
    }

    private function bindSource(BrusselsCadastreSource $source): void
    {
        $this->app->instance(
            SyncBrusselsClubsCommand::class,
            new SyncBrusselsClubsCommand($source),
        );
    }

    public function test_command_creates_sync_history_with_records_count(): void
    {
        $source = Mockery::mock(BrusselsCadastreSource::class);
        $source->shouldReceive('fetchClubs')->once()->andReturn([
            $this->club('1'), $this->club('2'), $this->club('3'),
        ]);
        $this->bindSource($source);

        $this->artisan('prospects:sync-brussels-clubs')->assertExitCode(0);

        $history = SyncHistory::where('command', 'prospects:sync-brussels-clubs')->firstOrFail();
        $this->assertSame(3, $history->records_count);
        $this->assertSame('completed', $history->status);
    }

    public function test_auxiliary_semantics_no_overwrite_of_federation_address(): void
    {
        // Brussels prospects are auxiliary new coverage (own BR-CAD-* external_id
        // namespace), never a 1:1 club roster. Running the Brussels sync must
        // never touch a pre-existing federation-sourced prospect's location.
        $region = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige']);
        $federationProspect = Prospect::create([
            'external_id' => 'VL-VV-1',
            'name' => 'Federation Club',
            'type' => 'football_club',
            'federation' => 'VL-VV',
            'language' => 'nl',
            'region_id' => $region->id,
        ]);
        ProspectLocation::create([
            'prospect_id' => $federationProspect->id,
            'external_id' => 'VL-VV-1::hq',
            'contact_type' => 'headquarters',
            'address' => 'Federation Address 1, 1000 Bruxelles',
        ]);

        $source = Mockery::mock(BrusselsCadastreSource::class);
        $source->shouldReceive('fetchClubs')->once()->andReturn([$this->club('1')]);
        $this->bindSource($source);

        $this->artisan('prospects:sync-brussels-clubs')->assertExitCode(0);

        $this->assertDatabaseHas('prospects_locations', [
            'prospect_id' => $federationProspect->id,
            'external_id' => 'VL-VV-1::hq',
            'address' => 'Federation Address 1, 1000 Bruxelles',
        ]);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'BR-CAD-1']);
        $this->assertSame(2, Prospect::query()->count());
    }
}
