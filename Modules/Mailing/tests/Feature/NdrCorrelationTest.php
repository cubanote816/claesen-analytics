<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Services\MicrosoftGraphService;
use Tests\TestCase;

/**
 * Integration tests for ParseNdrBouncesCommand — verifies that the token-first
 * correlation strategy (MAI-029) attaches bounce events to the correct message.
 */
class NdrCorrelationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'bounce@example.com';

    private function makeNdrMessage(string $graphId, string $bouncedEmail, ?string $mailingToken, string $bounceType = 'hard'): array
    {
        $statusLine = $bounceType === 'hard'
            ? "Status: 5.1.1\nDiagnostic-Code: smtp; 550 user does not exist"
            : "Status: 4.2.2\nDiagnostic-Code: smtp; 452 mailbox full";

        $tokenLine = $mailingToken !== null
            ? "X-Mailing-Token: {$mailingToken}"
            : '';

        $body = <<<NDR
        Delivery has failed to these recipients or groups:

        {$bouncedEmail}

        {$statusLine}

        Original message headers:

        From: campaigns@claesen-verlichting.be
        To: {$bouncedEmail}
        {$tokenLine}
        Subject: Test Campaign
        NDR;

        return [
            'id'      => $graphId,
            'subject' => 'Undeliverable: Test Campaign',
            'body'    => ['content' => $body],
        ];
    }

    // -------------------------------------------------------------------------
    // Token-first correlation
    // -------------------------------------------------------------------------

    public function test_command_attaches_hard_bounce_to_exact_message_when_token_present(): void
    {
        // message1 sent earlier — this is the one the NDR refers to
        $message1 = CampaignMessage::factory()->sent()->create([
            'email'    => self::EMAIL,
            'sent_at'  => now()->subHours(2),
        ]);

        // message2 sent more recently — findLatestMessage() would return this without a token
        CampaignMessage::factory()->sent()->create([
            'email'   => self::EMAIL,
            'sent_at' => now()->subHour(),
        ]);

        $ndr = $this->makeNdrMessage('graph-001', self::EMAIL, $message1->tracking_token);

        $graph = Mockery::mock(MicrosoftGraphService::class);
        $graph->shouldReceive('fetchUnreadMessages')->once()->andReturn([$ndr]);
        $graph->shouldReceive('markMessageRead')->once()->with(Mockery::any(), 'graph-001');

        $this->app->instance(MicrosoftGraphService::class, $graph);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        $this->assertDatabaseHas('mailing_message_events', [
            'message_id' => $message1->id,
            'event_type' => MessageEventType::BOUNCED_HARD->value,
        ]);

        // The more recent message must NOT have received the bounce event
        $this->assertDatabaseMissing('mailing_message_events', [
            'message_id' => CampaignMessage::where('email', self::EMAIL)
                ->orderByDesc('sent_at')
                ->value('id'),
            'event_type' => MessageEventType::BOUNCED_HARD->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Fallback by email when no token
    // -------------------------------------------------------------------------

    public function test_command_falls_back_to_latest_message_when_no_token(): void
    {
        CampaignMessage::factory()->sent()->create([
            'email'   => self::EMAIL,
            'sent_at' => now()->subHours(2),
        ]);

        $latestMessage = CampaignMessage::factory()->sent()->create([
            'email'   => self::EMAIL,
            'sent_at' => now()->subHour(),
        ]);

        $ndr = $this->makeNdrMessage('graph-002', self::EMAIL, mailingToken: null);

        $graph = Mockery::mock(MicrosoftGraphService::class);
        $graph->shouldReceive('fetchUnreadMessages')->once()->andReturn([$ndr]);
        $graph->shouldReceive('markMessageRead')->once()->with(Mockery::any(), 'graph-002');

        $this->app->instance(MicrosoftGraphService::class, $graph);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        $this->assertDatabaseHas('mailing_message_events', [
            'message_id' => $latestMessage->id,
            'event_type' => MessageEventType::BOUNCED_HARD->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Token present but message not found → fallback
    // -------------------------------------------------------------------------

    public function test_command_falls_back_to_email_when_token_not_in_db(): void
    {
        $latestMessage = CampaignMessage::factory()->sent()->create([
            'email'   => self::EMAIL,
            'sent_at' => now()->subHour(),
        ]);

        $orphanToken = Str::random(64); // valid format but not in DB
        $ndr = $this->makeNdrMessage('graph-003', self::EMAIL, $orphanToken);

        $graph = Mockery::mock(MicrosoftGraphService::class);
        $graph->shouldReceive('fetchUnreadMessages')->once()->andReturn([$ndr]);
        $graph->shouldReceive('markMessageRead')->once()->with(Mockery::any(), 'graph-003');

        $this->app->instance(MicrosoftGraphService::class, $graph);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        $this->assertDatabaseHas('mailing_message_events', [
            'message_id' => $latestMessage->id,
            'event_type' => MessageEventType::BOUNCED_HARD->value,
        ]);
    }

    // CLA-656 / S4 — non-bounce messages must stay unread; recognised bounces are still marked read.
    public function test_non_bounce_message_is_not_marked_read_but_bounce_is(): void
    {
        config([
            'mailing.ndr_inbox' => 'sport@claesen-verlichting.be',
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);
        \Illuminate\Support\Facades\Cache::put('microsoft_graph_token', 'fake-token', 60);

        $human = ['id' => 'human-1', 'subject' => 'Hallo, vraag over verlichting', 'body' => ['content' => 'Kunnen jullie mij bellen?']];
        $ndr   = $this->makeNdrMessage('ndr-1', self::EMAIL, null);

        \Illuminate\Support\Facades\Http::fake([
            '*/mailFolders/inbox/messages*' => \Illuminate\Support\Facades\Http::response(['value' => [$human, $ndr]], 200),
            '*/mailFolders?*'               => \Illuminate\Support\Facades\Http::response(['value' => [['id' => 'folder-1']]], 200),
            '*/messages/*/move'             => \Illuminate\Support\Facades\Http::response([], 201),
            '*/messages/*'                  => \Illuminate\Support\Facades\Http::response([], 200),
        ]);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/messages/ndr-1'));
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/messages/human-1'));
    }

    // CLA-656 / S4 — non-bounce messages are moved to the unprocessed folder (unread), the folder is resolved once per run.
    public function test_non_bounce_messages_are_moved_to_unprocessed_folder_once_per_run(): void
    {
        $Http = \Illuminate\Support\Facades\Http::class;
        config([
            'mailing.ndr_inbox' => 'sport@claesen-verlichting.be',
            'mailing.ndr_unprocessed_folder' => 'Mailing - not processed',
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);
        \Illuminate\Support\Facades\Cache::put('microsoft_graph_token', 'fake-token', 60);

        $a = ['id' => 'human-1', 'subject' => 'Automatisch antwoord: afwezig', 'body' => ['content' => 'Out of office']];
        $b = ['id' => 'human-2', 'subject' => 'Vraag', 'body' => ['content' => 'Bel mij']];
        $ndr = $this->makeNdrMessage('ndr-1', self::EMAIL, null);

        $Http::fake([
            '*/mailFolders/inbox/messages*' => $Http::response(['value' => [$a, $b, $ndr]], 200),
            '*/mailFolders?*'               => $Http::sequence()->push(['value' => []], 200),
            '*/mailFolders'                 => $Http::response(['id' => 'folder-new', 'displayName' => 'Mailing - not processed'], 201),
            '*/messages/*/move'             => $Http::response([], 201),
            '*/messages/*'                  => $Http::response([], 200),
        ]);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        foreach (['human-1', 'human-2'] as $id) {
            $Http::assertSent(fn ($r) => $r->method() === 'POST'
                && str_ends_with($r->url(), "/users/sport@claesen-verlichting.be/messages/{$id}/move")
                && $r['destinationId'] === 'folder-new');
            $Http::assertNotSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), "/messages/{$id}"));
        }
        $Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/messages/ndr-1'));
        $Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/messages/ndr-1/move'));

        $lookups = $Http::recorded(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/mailFolders?'));
        $creates = $Http::recorded(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/mailFolders'));
        $this->assertCount(1, $lookups);
        $this->assertCount(1, $creates);
        $this->assertSame('Mailing - not processed', $creates->first()[0]['displayName'] ?? $creates->first()[0]->data()['displayName']);
    }

    public function test_move_failure_is_logged_and_run_continues(): void
    {
        $Http = \Illuminate\Support\Facades\Http::class;
        config([
            'mailing.ndr_inbox' => 'sport@claesen-verlichting.be',
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);
        \Illuminate\Support\Facades\Cache::put('microsoft_graph_token', 'fake-token', 60);

        $human = ['id' => 'human-1', 'subject' => 'Vraag', 'body' => ['content' => 'Bel mij']];
        $ndr   = $this->makeNdrMessage('ndr-1', self::EMAIL, null);

        $Http::fake([
            '*/mailFolders/inbox/messages*' => $Http::response(['value' => [$human, $ndr]], 200),
            '*/mailFolders?*'               => $Http::response([], 403),
            '*/messages/*'                  => $Http::response([], 200),
        ]);

        $this->artisan('mailing:parse-bounces')->assertSuccessful();

        $Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/messages/ndr-1'));
        $Http::assertNotSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/messages/human-1'));
    }
}
