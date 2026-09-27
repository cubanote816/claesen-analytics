<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Models\SyncHistory;
use RuntimeException;
use Tests\TestCase;

class SyncValClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_failure_returns_failure_and_marks_history_failed(): void
    {
        Http::fake([
            'https://www.atletiek.be/organisatie/clubs' => Http::response([], 503),
        ]);

        $this->artisan('prospects:sync-val-clubs')->assertExitCode(1);

        $history = SyncHistory::where('command', 'prospects:sync-val-clubs')->firstOrFail();
        $this->assertSame('failed', $history->status);
        $this->assertNotNull($history->finished_at);
    }

    public function test_terreinen_section_persists_only_venue_nodes_and_parses_later_contact(): void
    {
        Http::fake([
            'https://www.atletiek.be/organisatie/clubs' => Http::response(
                '<a href="/organisatie/clubs/venue-club">Venue Club</a>'
            ),
            'https://www.atletiek.be/organisatie/clubs/venue-club' => Http::response(<<<'HTML'
                <h1>Venue VAL Club</h1>
                <h5>Terreinen</h5>
                <div>North Track&#10;Trackstraat 1&#10;2000 Antwerpen&#10;Type: Outdoor</div>
                <div>South Track&#10;Sportlaan 2&#10;2018 Antwerpen&#10;Type: Indoor</div>
                <h5>Contact</h5>
                <div>Type: Administration&#10;<a href="mailto:club@example.test">club@example.test</a></div>
                HTML),
        ]);

        $this->artisan('prospects:sync-val-clubs')->assertExitCode(0);

        $this->assertDatabaseCount('prospects_locations', 2);
        $this->assertDatabaseHas('prospects_locations', [
            'address' => 'Trackstraat 1, 2000 Antwerpen',
            'email' => 'club@example.test',
            'contact_type' => 'venue_name',
        ]);
        $this->assertDatabaseHas('prospects_locations', [
            'address' => 'Sportlaan 2, 2018 Antwerpen',
            'email' => null,
            'contact_type' => 'venue_name',
        ]);
    }

    public function test_club_detail_exception_is_logged_and_later_club_persists(): void
    {
        Http::fake([
            'https://www.atletiek.be/organisatie/clubs' => Http::response(<<<'HTML'
                <a href="/organisatie/clubs/failing-club">Failing</a>
                <a href="/organisatie/clubs/surviving-club">Surviving</a>
                HTML),
            'https://www.atletiek.be/organisatie/clubs/failing-club' => fn (Request $request) => throw new RuntimeException('detail timeout'),
            'https://www.atletiek.be/organisatie/clubs/surviving-club' => Http::response('<h1>Surviving VAL Club</h1>'),
        ]);

        $this->artisan('prospects:sync-val-clubs')->assertExitCode(0);

        $history = SyncHistory::query()
            ->where('command', 'prospects:sync-val-clubs')
            ->firstOrFail();
        $messages = implode("\n", array_column($history->logs, 'message'));

        $this->assertStringContainsString('failing-club', $messages);
        $this->assertStringContainsString('detail timeout', $messages);
        $this->assertSame(1, $history->records_count);
        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'VL-surviving-club',
            'name' => 'Surviving VAL Club',
        ]);
    }
}
