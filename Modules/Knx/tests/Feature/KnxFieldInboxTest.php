<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxNotification;
use Tests\TestCase;

/**
 * K4 — the field inbox (docs/BACKEND-API.md §4.8).
 *
 * The rule worth pinning is not the 204: it is that confirming a registration
 * also confirms the apparatus it created, because that is what stops the dossier
 * from flagging something the office already triaged.
 */
final class KnxFieldInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The notification title is translated and STORED when the device is registered below: pin the
        // locale so the test never depends on the environment's APP_LOCALE (CI runs in English).
        app()->setLocale('nl');
        // The tenant has to exist before the fixture runs (KnxTenant refuses to
        // invent one), and it is the fixture that creates the office account:
        // seeding *after* creating our own person would delete it, because the
        // demo seed replaces the tenant's rows.
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );

    }

    public function test_the_inbox_has_the_contract_shape(): void
    {
        $inbox = $this->getJson('/api/v1/knx/notifications')->assertOk()->json();

        $this->assertCount(2, $inbox);
        $this->assertSame(
            ['id', 'kind', 'projectCode', 'title', 'summary', 'roomId', 'boardId', 'moduleSlot', 'deviceId', 'issueId', 'actor', 'target', 'capturedAt', 'receivedAt', 'acked', 'address', 'type', 'room', 'board', 'boardName', 'serial', 'projectName', 'reportedBy', 'reportedAt'],
            array_keys($inbox[0]),
        );

        $first = $inbox[0];

        // KNX-54: the typed block, and the legacy one still there.
        $this->assertSame('device_registered', $first['kind']);
        $this->assertSame('Nieuw toestel geregistreerd', $first['title']);
        $this->assertSame('M. Claes', $first['actor']['name']);
        $this->assertNotNull($first['actor']['id']);
        $this->assertSame('device', $first['target']['type']);
        $this->assertNotNull($first['target']['id']);
        $this->assertNotNull($first['receivedAt']);

        $this->assertSame('1.2.047', $first['address']);
        $this->assertSame('Zaal 2.07', $first['room']);
        $this->assertSame('E21', $first['board']);
        $this->assertSame('Verdeelbord E21', $first['boardName']);
        $this->assertSame('239870', $first['projectCode']);
        $this->assertSame('Kantoor Hectaar · 2e verdieping', $first['projectName']);
        $this->assertSame('M. Claes', $first['reportedBy']);
        $this->assertFalse($first['acked']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $first['reportedAt']);
    }

    public function test_the_inbox_filters_by_kind_project_and_unacked(): void
    {
        // The fixture's two notices are both device registrations, both unacked.
        $this->assertCount(2, $this->getJson('/api/v1/knx/notifications?kind=device_registered')->assertOk()->json());
        $this->assertCount(0, $this->getJson('/api/v1/knx/notifications?kind=board_created')->assertOk()->json());
        $this->assertCount(2, $this->getJson('/api/v1/knx/notifications?unacked=1')->assertOk()->json());

        $byProject = $this->getJson('/api/v1/knx/notifications?project=C1618')->assertOk()->json();
        $this->assertNotEmpty($byProject);
        $this->assertTrue(collect($byProject)->every(fn (array $n): bool => $n['projectCode'] === 'C1618'));

        // An unknown kind is a field error, not a silently empty list.
        $this->getJson('/api/v1/knx/notifications?kind=nope')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['kind']]);
    }

    public function test_the_inbox_is_most_recent_first(): void
    {
        $timestamps = array_column($this->getJson('/api/v1/knx/notifications')->json(), 'reportedAt');
        $sorted = $timestamps;
        rsort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    public function test_an_unknown_board_is_reported_as_null_and_not_as_an_error(): void
    {
        // A registration naming a board the office has never modelled: the point of
        // the inbox is that this case is normal.
        KnxNotification::factory()->create([
            'project_id' => KnxNotification::query()->first()->project_id,
            'board_id' => null,
            'address' => '9.9.999',
            'room' => 'Onbekende ruimte',
        ]);

        $entry = collect($this->getJson('/api/v1/knx/notifications')->json())
            ->firstWhere('address', '9.9.999');

        $this->assertNotNull($entry);
        $this->assertNull($entry['board']);
        $this->assertNull($entry['boardName']);
    }

    public function test_confirming_a_registration_also_confirms_the_apparatus_it_created(): void
    {
        $notification = KnxNotification::query()->where('address', '1.1.131')->sole();
        $device = $notification->device;

        $this->assertNotNull($device);
        $this->assertTrue($device->isNew(), 'the fixture starts unconfirmed');
        $this->assertContains(
            '1.1.131',
            collect($this->getJson('/api/v1/knx/projects/C1618/devices')->json())->where('isNew', true)->pluck('address')->all(),
        );

        $this->postJson("/api/v1/knx/notifications/{$notification->id}/ack")->assertNoContent();

        $this->assertTrue($notification->fresh()->isAcked());
        $this->assertFalse($device->fresh()->isNew(), 'confirming the event confirms the registry');

        // …and the dossier no longer flags it.
        $this->assertNotContains(
            '1.1.131',
            collect($this->getJson('/api/v1/knx/projects/C1618/devices')->json())->where('isNew', true)->pluck('address')->all(),
        );
    }

    public function test_confirming_twice_is_not_an_error(): void
    {
        $notification = KnxNotification::query()->firstOrFail();

        // A front that polls every 5 seconds will do this, so it must be harmless.
        $this->postJson("/api/v1/knx/notifications/{$notification->id}/ack")->assertNoContent();
        $this->postJson("/api/v1/knx/notifications/{$notification->id}/ack")->assertNoContent();
    }

    public function test_confirming_an_unknown_item_is_a_404_with_the_contract_envelope(): void
    {
        $this->postJson('/api/v1/knx/notifications/999999/ack')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_confirming_everything_clears_the_inbox_and_the_dossier_flags(): void
    {
        $this->assertTrue(KnxNotification::query()->pending()->exists());
        // Only field registrations carry that flag: an ETS device is planned, never
        // "registered", so it stays without acknowledged_at forever.
        $this->assertTrue(KnxDevice::query()->fromField()->whereNull('acknowledged_at')->exists());

        $this->postJson('/api/v1/knx/notifications/ack-all')->assertNoContent();

        $this->assertFalse(KnxNotification::query()->pending()->exists());
        $this->assertFalse(KnxDevice::query()->fromField()->whereNull('acknowledged_at')->exists());

        // Nothing is deleted: the inbox still lists them, marked as confirmed.
        $inbox = $this->getJson('/api/v1/knx/notifications')->assertOk()->json();

        $this->assertCount(2, $inbox);
        $this->assertSame([true, true], array_column($inbox, 'acked'));
    }

    public function test_the_inbox_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/knx/notifications/ack-all')->assertUnauthorized();
    }
}
