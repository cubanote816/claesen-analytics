<?php

namespace Modules\Prospects\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Console\Commands\SyncHockeyClubsCommand;
use Modules\Prospects\Models\SyncHistory;
use Tests\TestCase;

class SyncHockeyClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function commandWith(array $clubs, array $responses): SyncHockeyClubsCommand
    {
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);
        $command = new SyncHockeyClubsCommand($client);

        $property = new \ReflectionProperty($command, 'clubsData');
        $property->setAccessible(true);
        $property->setValue($command, $clubs);

        return $command;
    }

    public function test_default_client_uses_verified_tls(): void
    {
        $command = new SyncHockeyClubsCommand;
        $property = new \ReflectionProperty($command, 'client');
        $property->setAccessible(true);

        /** @var Client $client */
        $client = $property->getValue($command);

        $this->assertNotFalse($client->getConfig('verify'));
    }

    public function test_francophone_hockey_club_gets_language_fr(): void
    {
        $command = $this->commandWith(
            [['Francophone Hockey', 'FR0001', 'Ligue Francophone de Hockey']],
            [new Response(200, [], '<html><body></body></html>')]
        );
        $this->app->instance(SyncHockeyClubsCommand::class, $command);

        $this->artisan('prospects:sync-hockey-clubs')->assertExitCode(0);

        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'FR-HOCKEY-FR0001',
            'language' => 'fr',
        ]);
    }

    public function test_flemish_hockey_club_gets_language_nl(): void
    {
        $command = $this->commandWith(
            [['Flemish Hockey', 'VL0001', 'Vlaamse Hockey Liga']],
            [new Response(200, [], '<html><body></body></html>')]
        );
        $this->app->instance(SyncHockeyClubsCommand::class, $command);

        $this->artisan('prospects:sync-hockey-clubs')->assertExitCode(0);

        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'VL-HOCKEY-VL0001',
            'language' => 'nl',
        ]);
    }

    public function test_arbh_club_external_id_uses_arbh_prefix(): void
    {
        $command = $this->commandWith(
            [['National Hockey', 'ARBH01', 'Royal Belgian Hockey Association']],
            [new Response(200, [], '<html><body></body></html>')]
        );
        $this->app->instance(SyncHockeyClubsCommand::class, $command);

        $this->artisan('prospects:sync-hockey-clubs')->assertExitCode(0);

        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'ARBH-HOCKEY-ARBH01',
            'federation' => 'ARBH-KBHB',
        ]);
    }

    public function test_club_failure_is_logged_and_later_club_is_persisted(): void
    {
        $command = $this->commandWith(
            [
                ['Failing Hockey', 'FAIL01', 'Vlaamse Hockey Liga'],
                ['Surviving Hockey', 'OK0001', 'Vlaamse Hockey Liga'],
            ],
            [
                new ConnectException('detail timeout', new Request('GET', 'test')),
                new Response(200, [], '<html><body></body></html>'),
            ]
        );
        $this->app->instance(SyncHockeyClubsCommand::class, $command);

        $this->artisan('prospects:sync-hockey-clubs')->assertExitCode(0);

        $history = SyncHistory::where('command', 'prospects:sync-hockey-clubs')->firstOrFail();
        $messages = implode("\n", array_column($history->logs ?? [], 'message'));

        $this->assertStringContainsString('Failing Hockey', $messages);
        $this->assertStringContainsString('detail timeout', $messages);
        $this->assertSame(1, $history->records_count);
        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'VL-HOCKEY-OK0001',
            'name' => 'Surviving Hockey',
        ]);
    }

    public function test_real_clubs_data_contains_no_test_placeholder_entries(): void
    {
        $command = new SyncHockeyClubsCommand;
        $property = new \ReflectionProperty($command, 'clubsData');
        $property->setAccessible(true);

        /** @var array<int, array{0: string, 1: string, 2: string}> $clubsData */
        $clubsData = $property->getValue($command);

        $this->assertNotEmpty($clubsData);

        foreach ($clubsData as $club) {
            $this->assertStringNotContainsStringIgnoringCase(
                'test',
                $club[0],
                "Real hockey club list must not ship placeholder/test entries to production (found \"{$club[0]}\")."
            );
        }
    }
}
