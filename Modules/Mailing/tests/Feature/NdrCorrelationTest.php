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
}
