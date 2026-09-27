<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Console\Commands\SyncAftClubsCommand;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\DataSource\AfttPdfSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\SyncHistory;
use Mockery;
use Tests\TestCase;

class SyncAftClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $suffix): NormalizedClub
    {
        return NormalizedClub::fromArray([
            'externalId' => "FR-AFTT-{$suffix}",
            'federation' => 'FR-AFTT',
            'name' => "Club {$suffix}",
            'type' => 'table_tennis_club',
            'language' => 'fr',
            'postalCode' => '6000',
        ]);
    }

    private function bindSource(AfttPdfSource $source): void
    {
        $this->app->instance(
            SyncAftClubsCommand::class,
            new SyncAftClubsCommand($source),
        );
    }

    public function test_command_uses_injected_aftt_source_not_fake_array(): void
    {
        $source = Mockery::mock(AfttPdfSource::class);
        $source->shouldReceive('fetchClubs')->once()->andReturn([
            $this->club('1'), $this->club('2'), $this->club('3'), $this->club('4'), $this->club('5'),
        ]);
        $this->bindSource($source);

        $this->artisan('prospects:sync-aft-clubs')->assertExitCode(0);

        $this->assertSame(5, Prospect::query()->where('federation', 'FR-AFTT')->count());
        $this->assertDatabaseMissing('prospects_prospects', ['name' => 'TC de Wavre']);
        $this->assertDatabaseMissing('prospects_prospects', ['name' => 'Royal Leopold Club']);
    }

    public function test_zero_clubs_parsed_fails_the_run(): void
    {
        $source = Mockery::mock(AfttPdfSource::class);
        $source->shouldReceive('fetchClubs')->once()->andThrow(
            new DataSourceException('No AFTT clubs could be parsed from the annuaire.')
        );
        $this->bindSource($source);

        $this->artisan('prospects:sync-aft-clubs')->assertExitCode(1);

        $history = SyncHistory::where('command', 'prospects:sync-aft-clubs')->firstOrFail();
        $this->assertSame('failed', $history->status);
    }

    public function test_per_club_persist_failure_is_logged_and_others_still_persist(): void
    {
        $source = Mockery::mock(AfttPdfSource::class);
        $source->shouldReceive('fetchClubs')->once()->andReturn([
            $this->club('FAIL'), $this->club('OK'),
        ]);
        $this->bindSource($source);

        $attempt = 0;
        Prospect::creating(function () use (&$attempt): void {
            $attempt++;
            if ($attempt === 1) {
                throw new \RuntimeException('write failed');
            }
        });

        $this->artisan('prospects:sync-aft-clubs')->assertExitCode(0);

        $history = SyncHistory::where('command', 'prospects:sync-aft-clubs')->firstOrFail();
        $messages = implode("\n", array_column($history->logs ?? [], 'message'));

        $this->assertStringContainsString('FR-AFTT-FAIL', $messages);
        $this->assertStringContainsString('write failed', $messages);
        $this->assertSame(1, $history->records_count);
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'FR-AFTT-OK']);
    }
}
