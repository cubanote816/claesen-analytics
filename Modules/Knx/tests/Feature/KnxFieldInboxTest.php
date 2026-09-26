<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Spatie\Permission\Models\Role;
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

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        Role::findOrCreate('knx_office', 'web');
        $user = User::factory()->create([
            'email' => 'lead@electrobertels.be',
            'password' => Hash::make('Secret1234!'),
            'is_active' => true,
            'organization_id' => $organization->id,
        ]);
        $user->assignRole('knx_office');
        KnxEmployee::factory()->office()->forOrganization($organization)->create(['user_id' => $user->id]);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs($user, 'sanctum');
    }

    public function test_the_inbox_has_the_contract_shape(): void
    {
        $inbox = $this->getJson('/api/v1/knx/notifications')->assertOk()->json();

        $this->assertCount(2, $inbox);
        $this->assertSame(
            ['id', 'address', 'type', 'room', 'board', 'boardName', 'serial', 'projectCode', 'projectName', 'reportedBy', 'reportedAt', 'acked'],
            array_keys($inbox[0]),
        );

        $first = $inbox[0];

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
