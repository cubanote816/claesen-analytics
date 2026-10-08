<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxRoomAlias;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Tests\TestCase;

/**
 * KNX-44 — the canonical room.
 *
 * The measurement found three unreconciled room vocabularies on the same project
 * (ETS 85 / report 79 / worklist 25), so the office's own room is canonical and the
 * others are aliases. Zona = habitación: a room is born with its readiness zone and
 * its eight checks, by every path that creates one.
 */
final class KnxRoomsTest extends TestCase
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

    public function test_the_room_list_has_the_contract_shape(): void
    {
        $rooms = $this->getJson('/api/v1/knx/projects/C1618/rooms')->assertOk()->json();

        // The fixture models six named rooms for every project, plus the ones the field
        // notifications create on the fly, so the count is "at least the six".
        $this->assertGreaterThanOrEqual(6, count($rooms));
        $this->assertSame(['id', 'name', 'floor', 'zoneId', 'aliases'], array_keys($rooms[0]));

        // The demo fixture predates the 1:1 rule, so only the rooms it gave a zone
        // (by name) have one; the rest answer a null zoneId, which is the nullable
        // state the contract allows.
        $this->assertTrue(collect($rooms)->contains(fn ($room) => $room['zoneId'] !== null));
        $this->assertTrue(collect($rooms)->contains(fn ($room) => $room['zoneId'] === null));

        // An unknown project, and a project of another organization, are 404.
        $this->getJson('/api/v1/knx/projects/BESTAAT-NIET/rooms')->assertNotFound();
    }

    public function test_creating_a_room_creates_its_zone_with_the_eight_checks(): void
    {
        $zonesBefore = KnxZone::query()->count();

        $response = $this->postJson('/api/v1/knx/projects/C1618/rooms', [
            'name' => 'Snoezelruimte',
            'floor' => '1e verdieping',
            'aliases' => [
                ['source' => 'plan', 'name' => 'snoezelruimte'],
                ['source' => 'ets', 'name' => 'snoezelruimte_1'],
            ],
        ])->assertCreated();

        $this->assertSame('Snoezelruimte', $response->json('name'));
        $this->assertNotNull($response->json('zoneId'));
        $this->assertCount(2, $response->json('aliases'));

        $room = KnxProjectRoom::query()->where('name', 'Snoezelruimte')->sole();

        // Zona = habitación, and the eight checks come with it in config order.
        $this->assertSame($zonesBefore + 1, KnxZone::query()->count());
        $zone = KnxZone::query()->where('room_id', $room->getKey())->sole();
        $this->assertSame($room->getKey(), $zone->room_id);
        $this->assertSame('Snoezelruimte', $zone->name);
        $this->assertSame(
            KnxZoneCheck::keys(),
            $zone->orderedChecks()->pluck('key')->all(),
        );
        $this->assertSame(
            [KnxZoneCheck::STATUS_PENDING],
            $zone->orderedChecks()->pluck('status')->unique()->values()->all(),
        );

        // The aliases are readable on the room list right away.
        $listed = collect($this->getJson('/api/v1/knx/projects/C1618/rooms')->json())
            ->firstWhere('name', 'Snoezelruimte');
        $this->assertSame(
            [['source' => 'plan', 'name' => 'snoezelruimte'], ['source' => 'ets', 'name' => 'snoezelruimte_1']],
            $listed['aliases'],
        );
    }

    public function test_aliases_are_replaced_wholesale(): void
    {
        $room = KnxProjectRoom::query()
            ->where('project_id', KnxProject::query()->where('code', 'C1618')->sole()->getKey())
            ->where('name', 'Vergaderzaal')
            ->sole();

        $this->patchJson("/api/v1/knx/rooms/{$room->getKey()}", [
            'aliases' => [['source' => 'worklist', 'name' => 'Vergaderzaal']],
        ])->assertOk()->assertJsonPath('aliases.0.source', 'worklist');

        // Replacing the set drops the old alias: a rename that left it behind would
        // keep resolving the old name to this room forever.
        $response = $this->patchJson("/api/v1/knx/rooms/{$room->getKey()}", [
            'name' => 'Vergaderzaal 2',
            'aliases' => [['source' => 'manual', 'name' => 'Vergaderzaal 2']],
        ])->assertOk();

        $this->assertSame('Vergaderzaal 2', $response->json('name'));
        $this->assertSame(
            [['source' => 'manual', 'name' => 'Vergaderzaal 2']],
            $response->json('aliases'),
        );
        $this->assertSame(
            1,
            KnxRoomAlias::query()->where('room_id', $room->getKey())->count(),
        );

        // An unknown source is a field error, not a silent drop.
        $this->patchJson("/api/v1/knx/rooms/{$room->getKey()}", [
            'aliases' => [['source' => 'ets-import', 'name' => 'x']],
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['aliases.0.source']]);
    }

    public function test_a_room_of_another_organization_is_a_404(): void
    {
        // `knx_project_rooms` has no tenant trait, so the boundary is explicit in the
        // controller: the room is resolved through its project and the organization.
        $other = Organization::factory()->create(['slug' => 'other-rooms']);
        $foreignProject = KnxProject::factory()->create(['organization_id' => $other->id]);
        $foreignRoom = KnxProjectRoom::create([
            'project_id' => $foreignProject->getKey(),
            'name' => 'Vreemde kamer',
        ]);

        $this->patchJson("/api/v1/knx/rooms/{$foreignRoom->getKey()}", ['name' => 'x'])->assertNotFound();

        // And the project-code route does not leak it either.
        $this->getJson("/api/v1/knx/projects/{$foreignProject->code}/rooms")->assertNotFound();
    }

    public function test_the_room_endpoints_refuse_a_field_account(): void
    {
        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->getJson('/api/v1/knx/projects/C1618/rooms')->assertUnauthorized();
        $this->postJson('/api/v1/knx/projects/C1618/rooms', ['name' => 'x'])->assertUnauthorized();
    }
}
