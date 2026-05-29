<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Models\TrackedLink;
use Tests\TestCase;

class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // MAI-013 — Open pixel
    // -------------------------------------------------------------------------

    public function test_open_pixel_returns_gif_for_unknown_token(): void
    {
        $response = $this->get('/mailing/track/open/' . Str::random(64));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
    }

    public function test_open_pixel_with_gif_suffix_also_works(): void
    {
        $token = Str::random(64);

        $response = $this->get("/mailing/track/open/{$token}.gif");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');
    }

    public function test_open_pixel_creates_opened_event(): void
    {
        $message = CampaignMessage::factory()->sent()->create();

        $this->get('/mailing/track/open/' . $message->tracking_token);

        $this->assertDatabaseHas('mailing_message_events', [
            'message_id' => $message->id,
            'event_type' => MessageEventType::OPENED->value,
        ]);
    }

    public function test_open_pixel_sets_cache_control_no_store(): void
    {
        $response = $this->get('/mailing/track/open/' . Str::random(64));

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_open_pixel_ignores_duplicate_from_same_ip_within_30_seconds(): void
    {
        $message = CampaignMessage::factory()->sent()->create();
        $ip      = '203.0.113.10';

        MessageEvent::factory()->opened($ip)->create([
            'message_id'  => $message->id,
            'occurred_at' => now()->subSeconds(10),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
             ->get('/mailing/track/open/' . $message->tracking_token);

        $this->assertSame(
            1,
            MessageEvent::where('message_id', $message->id)
                ->where('event_type', MessageEventType::OPENED->value)
                ->count()
        );
    }

    public function test_open_pixel_records_new_open_after_30_second_window(): void
    {
        $message = CampaignMessage::factory()->sent()->create();
        $ip      = '203.0.113.20';

        MessageEvent::factory()->opened($ip)->create([
            'message_id'  => $message->id,
            'occurred_at' => now()->subSeconds(35),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
             ->get('/mailing/track/open/' . $message->tracking_token);

        $this->assertSame(
            2,
            MessageEvent::where('message_id', $message->id)
                ->where('event_type', MessageEventType::OPENED->value)
                ->count()
        );
    }

    // -------------------------------------------------------------------------
    // MAI-014 — Click redirect
    // -------------------------------------------------------------------------

    public function test_click_redirect_falls_back_to_app_url_for_unknown_token(): void
    {
        $response = $this->get('/mailing/track/click/' . Str::random(64) . '/abcdef123456');

        $response->assertRedirect(config('app.url'));
    }

    public function test_click_redirect_falls_back_for_unknown_hash(): void
    {
        $message = CampaignMessage::factory()->sent()->create();

        $response = $this->get('/mailing/track/click/' . $message->tracking_token . '/000000000000');

        $response->assertRedirect(config('app.url'));
    }

    public function test_click_redirect_creates_clicked_event_and_redirects_to_original_url(): void
    {
        $message     = CampaignMessage::factory()->sent()->create();
        $originalUrl = 'https://claesen-verlichting.be/projecten';
        $hash        = substr(md5((string) $message->campaign_id . $originalUrl), 0, 12);

        TrackedLink::create([
            'campaign_id'  => $message->campaign_id,
            'original_url' => $originalUrl,
            'hash'         => $hash,
            'created_at'   => now(),
        ]);

        $response = $this->get('/mailing/track/click/' . $message->tracking_token . '/' . $hash);

        $response->assertRedirect($originalUrl);

        $this->assertDatabaseHas('mailing_message_events', [
            'message_id' => $message->id,
            'event_type' => MessageEventType::CLICKED->value,
        ]);
    }
}
