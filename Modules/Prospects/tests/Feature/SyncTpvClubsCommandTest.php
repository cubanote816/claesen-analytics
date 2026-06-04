<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Console\Commands\SyncTpvClubsCommand;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class SyncTpvClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // HTML fixtures
    // -------------------------------------------------------------------------

    private function listPageHtml(array $clubs): string
    {
        $cards = '';
        foreach ($clubs as $club) {
            $cards .= <<<HTML
            <div class="result-card--club--info">
                <h5>{$club['title']}</h5>
                <a class="tvl-cta-btn" href="/nl/clubdashboard/over-club?clubId={$club['clubId']}">Dashboard</a>
            </div>
            HTML;
        }
        return "<html><body>{$cards}</body></html>";
    }

    private function emptyListPageHtml(): string
    {
        return '<html><body></body></html>';
    }

    private function detailPageHtml(array $data): string
    {
        $items = '';
        foreach ($data as $label => $value) {
            $val = match ($label) {
                'Email'   => "<a href=\"mailto:{$value}\">{$value}</a>",
                'Website' => "<a href=\"{$value}\">{$value}</a>",
                default   => $value,
            };
            $items .= <<<HTML
            <li class="clearfix">
                <span class="list-label">{$label}</span>
                <span class="list-value">{$val}</span>
            </li>
            HTML;
        }
        return "<html><body><ul>{$items}</ul></body></html>";
    }

    private function emptyDetailPageHtml(): string
    {
        return '<html><body><p>Club not found</p></body></html>';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeClient(array $responses): Client
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        return new Client(['handler' => $stack]);
    }

    private function runCommand(Client $client): void
    {
        $this->app->bind(SyncTpvClubsCommand::class, fn () => new SyncTpvClubsCommand($client));
        $this->artisan('prospects:sync-tpv-clubs');
    }

    // -------------------------------------------------------------------------
    // Test 1 — Club con datos completos
    // -------------------------------------------------------------------------

    public function test_club_with_full_data_is_upserted_correctly(): void
    {

        $client = $this->makeClient([
            new Response(200, [], $this->listPageHtml([
                ['title' => 'TC Testclub (TC001)', 'clubId' => '9999'],
            ])),
            new Response(200, [], $this->emptyListPageHtml()),   // terminates pagination
            new Response(200, [], $this->detailPageHtml([
                'Adres (hoofdlocatie)' => 'Teststraat 1 1000 Brussel',
                'Email'                => 'info@testclub.be',
                'Telefoonnummer'       => '02 123 45 67',
                'Website'              => 'https://www.testclub.be',
            ])),
        ]);

        $this->runCommand($client);

        $prospect = Prospect::where('external_id', 'VL-TPV-TC001')->first();
        $this->assertNotNull($prospect);
        $this->assertSame('TC Testclub', $prospect->name);
        $this->assertSame('https://www.testclub.be', $prospect->website);

        $region = Region::where('name', 'Brussel')->first();
        $this->assertSame($region->id, $prospect->region_id);

        $location = $prospect->locations()->where('contact_type', 'venue_name')->first();
        $this->assertNotNull($location);
        $this->assertSame('Teststraat 1 1000 Brussel', $location->address);
        $this->assertSame('info@testclub.be', $location->email);
        $this->assertSame('02 123 45 67', $location->phone);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Club obsoleto (sin li.clearfix) — no rompe el sync
    // -------------------------------------------------------------------------

    public function test_obsolete_club_page_is_handled_gracefully(): void
    {
        $client = $this->makeClient([
            new Response(200, [], $this->listPageHtml([
                ['title' => 'TC Fantasma', 'clubId' => '0001'],
            ])),
            new Response(200, [], $this->emptyListPageHtml()),
            new Response(200, [], $this->emptyDetailPageHtml()),
        ]);

        $this->runCommand($client);

        // Prospect created with placeholder website (no real website extracted)
        $prospect = Prospect::where('external_id', 'VL-TPV-0001')->first();
        $this->assertNotNull($prospect);
        $this->assertStringContainsString('clubId=0001', $prospect->website);

        // No location created (no address extracted)
        $this->assertSame(0, $prospect->locations()->count());

        // Overige fallback region assigned
        $overige = Region::where('name', 'Overige')->first();
        $this->assertNotNull($overige);
        $this->assertSame($overige->id, $prospect->region_id);

        // SyncHistory completed — no crash
        $history = SyncHistory::where('command', 'prospects:sync-tpv-clubs')->first();
        $this->assertSame('completed', $history->status);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Timeout/exception en un club — capturado y logeado, sync continúa
    // -------------------------------------------------------------------------

    public function test_connect_exception_is_caught_and_sync_continues(): void
    {

        $client = $this->makeClient([
            new Response(200, [], $this->listPageHtml([
                ['title' => 'TC Timeout (FAIL)', 'clubId' => '1111'],
                ['title' => 'TC OK (OK01)',       'clubId' => '2222'],
            ])),
            new Response(200, [], $this->emptyListPageHtml()),
            // Club 1111: timeout
            new ConnectException('Connection timed out', new Request('GET', 'test')),
            // Club 2222: full data
            new Response(200, [], $this->detailPageHtml([
                'Adres (hoofdlocatie)' => 'Kerkstraat 5 2000 Antwerpen',
                'Website'              => 'https://www.tcok.be',
            ])),
        ]);

        $this->runCommand($client);

        // Club 2222 synced despite club 1111 failing
        $this->assertDatabaseHas('prospects_prospects', ['external_id' => 'VL-TPV-OK01']);

        // Error logged for club 1111
        $history = SyncHistory::where('command', 'prospects:sync-tpv-clubs')->first();
        $errorLogs = array_filter($history->logs ?? [], fn ($l) => $l['type'] === 'error');
        $this->assertNotEmpty($errorLogs);
        $this->assertStringContainsString('TC Timeout', array_values($errorLogs)[0]['message']);

        // Sync completed (not failed)
        $this->assertSame('completed', $history->status);
    }

    // -------------------------------------------------------------------------
    // Test 4 — Quality metrics log escrito antes de finishSyncLog
    // -------------------------------------------------------------------------

    public function test_quality_metrics_are_logged_at_end(): void
    {

        $client = $this->makeClient([
            new Response(200, [], $this->listPageHtml([
                ['title' => 'TC Metriek (MET01)', 'clubId' => '3333'],
            ])),
            new Response(200, [], $this->emptyListPageHtml()),
            new Response(200, [], $this->detailPageHtml([
                'Adres (hoofdlocatie)' => 'Wetstraat 10 1000 Brussel',
                'Website'              => 'https://www.tcmetriek.be',
            ])),
        ]);

        $this->runCommand($client);

        $history = SyncHistory::where('command', 'prospects:sync-tpv-clubs')->first();
        $qualityLogs = array_filter(
            $history->logs ?? [],
            fn ($l) => str_starts_with($l['message'], 'Quality:')
        );

        $this->assertNotEmpty($qualityLogs, 'Quality metrics log entry missing');
        $qualityMsg = array_values($qualityLogs)[0]['message'];
        $this->assertStringContainsString('processed', $qualityMsg);
        $this->assertStringContainsString('non-fallback region', $qualityMsg);
        $this->assertStringContainsString('real website', $qualityMsg);
    }

    // -------------------------------------------------------------------------
    // Test 5 — SyncHistory status y records_count correctos al finalizar
    // -------------------------------------------------------------------------

    public function test_sync_history_completed_with_correct_records_count(): void
    {
        $client = $this->makeClient([
            new Response(200, [], $this->listPageHtml([
                ['title' => 'TC Alpha', 'clubId' => '4444'],
                ['title' => 'TC Beta',  'clubId' => '5555'],
            ])),
            new Response(200, [], $this->emptyListPageHtml()),
            new Response(200, [], $this->emptyDetailPageHtml()),
            new Response(200, [], $this->emptyDetailPageHtml()),
        ]);

        $this->runCommand($client);

        $history = SyncHistory::where('command', 'prospects:sync-tpv-clubs')->first();
        $this->assertSame('completed', $history->status);
        $this->assertSame(2, $history->records_count);
        $this->assertNotNull($history->finished_at);
    }
}
