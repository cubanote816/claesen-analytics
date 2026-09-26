<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\EventStreamService;
use Tests\TestCase;

/**
 * K10 — server-sent events (docs/BACKEND-API.md §6).
 *
 * §6 is optional ("si no se implementa, el polling actual sigue funcionando"), so
 * what is worth testing is not the socket but the two decisions: a connection starts
 * at the present instead of replaying history, and it ends by itself instead of
 * holding a worker forever.
 */
final class KnxEventStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    public function test_a_fresh_cursor_starts_at_the_present_and_replays_nothing(): void
    {
        $stream = app(EventStreamService::class);
        $cursor = $stream->currentCursor();

        // The fixture has notifications and conflicts, and none of them is "news".
        $this->assertGreaterThan(0, $cursor['notification']);
        $this->assertGreaterThan(0, $cursor['conflict']);
        $this->assertSame([], $stream->pendingSince($cursor)['events']);
    }

    public function test_it_emits_the_two_events_of_the_contract_in_order(): void
    {
        $stream = app(EventStreamService::class);
        $cursor = $stream->currentCursor();

        $project = KnxProject::query()->where('code', 'C1618')->sole();

        $notification = KnxNotification::factory()->create([
            'project_id' => $project->getKey(),
            'address' => '1.9.001',
            'room' => 'Nieuwe ruimte',
        ]);
        $conflict = KnxConflict::factory()->create([
            'project_id' => $project->getKey(),
            'address' => '1.9.002',
        ]);

        $pending = $stream->pendingSince($cursor);

        $this->assertSame(['field.device.created', 'conflict.created'], array_column($pending['events'], 'event'));

        $device = $pending['events'][0]['data'];
        $this->assertSame((string) $notification->getKey(), $device['notificationId']);
        $this->assertSame('C1618', $device['projectCode']);
        $this->assertSame('1.9.001', $device['address']);
        $this->assertSame('Nieuwe ruimte', $device['room']);

        $opened = $pending['events'][1]['data'];
        $this->assertSame((string) $conflict->getKey(), $opened['conflictId']);
        $this->assertSame('C1618', $opened['projectCode']);
        $this->assertSame('1.9.002', $opened['address']);

        // The cursor moved past both, so the same caller does not see them twice.
        $this->assertSame($notification->getKey(), $pending['cursor']['notification']);
        $this->assertSame($conflict->getKey(), $pending['cursor']['conflict']);
        $this->assertSame([], $stream->pendingSince($pending['cursor'])['events']);
    }

    public function test_the_stream_answers_with_the_sse_headers_and_closes_by_itself(): void
    {
        // A real connection would hold a worker for `stream_seconds`; the loop is
        // bounded by config exactly so that this can be set to zero in tests.
        config(['knx.events.stream_seconds' => 0]);

        $response = $this->get('/api/v1/knx/events')->assertOk();

        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('content-type'));
        // Laravel appends `private`; what matters is that a proxy never caches it.
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('cache-control'));
        // Without this, a proxy buffers the stream and the events arrive in one lump.
        $this->assertSame('no', $response->headers->get('x-accel-buffering'));

        // It tells the client how long to wait before reconnecting, then says goodbye.
        $body = $response->streamedContent();

        $this->assertStringContainsString('retry:', $body);
        $this->assertStringContainsString(': bye', $body);
    }

    public function test_the_stream_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        // Bearer, and not a query token: a token in the URL ends up in access logs and
        // browser history. That is why the client reads the stream from fetch() rather
        // than the native EventSource, which cannot send headers.
        $this->getJson('/api/v1/knx/events')->assertUnauthorized();
    }
}
