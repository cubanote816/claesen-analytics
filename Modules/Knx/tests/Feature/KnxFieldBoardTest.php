<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * KNX-53 — the technician creates a cabinet or a module from site.
 *
 * The two rules: every field write is idempotent by the app's own `clientId`, and what
 * the field creates is born `unreviewed` (the office confirms it). A slot already taken
 * is an explicit `409`, never a silent overwrite.
 */
final class KnxFieldBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);
    }

    private function technician(string $name = 'Jan Van Dyck'): KnxEmployee
    {
        return KnxEmployee::query()->where('name', $name)->sole();
    }

    private function assignToday(KnxEmployee $technician, string $code): void
    {
        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $technician->getKey(), 'date' => now()->toDateString()],
            ['project_id' => KnxProject::query()->where('code', $code)->sole()->getKey()],
        );
    }

    private function asField(string $email = 'jan.van.dyck@electrobertels.be'): void
    {
        $this->actingAs(User::query()->where('email', $email)->sole(), 'sanctum');
    }

    private function createBoard(string $clientId = 'cb-1'): TestResponse
    {
        return $this->postJson('/api/v1/knx/field/projects/C1618/boards', [
            'clientId' => $clientId,
            'code' => 'E77',
            'name' => 'Nieuw verdeelbord (werf)',
            'floor' => 'Gelijkvloers',
            'capturedAt' => '2026-10-08T09:00:00+02:00',
        ]);
    }

    public function test_a_board_created_from_site_is_born_unreviewed_and_is_idempotent(): void
    {
        $this->assignToday($this->technician(), 'C1618');
        $this->asField();

        $created = $this->createBoard()->assertCreated();

        $this->assertSame('nieuw-verdeelbord-werf', $created->json('id'));
        $this->assertSame('field', $created->json('origin'));
        $this->assertSame('unreviewed', $created->json('reviewState'));
        $this->assertSame('E77', $created->json('code'));

        // A retry with the same clientId answers the same board, never a second one.
        $replay = $this->createBoard()->assertOk();
        $this->assertSame($created->json('id'), $replay->json('id'));
        $this->assertSame(1, KnxBoard::query()->where('slug', 'nieuw-verdeelbord-werf')->count());

        // KNX-54: the office inbox gets the typed notice, and the retry does not duplicate it.
        $this->assertSame(1, KnxNotification::query()->where('kind', KnxNotification::KIND_BOARD_CREATED)->count());
    }

    public function test_the_same_client_id_in_another_project_is_rejected(): void
    {
        $technician = $this->technician();
        $this->assignToday($technician, 'C1618');
        $this->asField();

        $this->createBoard('cb-reuse')->assertCreated();

        // A technician is planned on ONE project per day, so the reuse is tested by
        // moving them to the other project for the same day.
        $this->assignToday($technician, '239870');

        $this->postJson('/api/v1/knx/field/projects/239870/boards', [
            'clientId' => 'cb-reuse',
            'code' => 'E88',
            'name' => 'Ander bord',
            'capturedAt' => '2026-10-08T09:00:00+02:00',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['clientId']]);
    }

    public function test_a_module_created_from_site_is_unreviewed_and_a_taken_slot_is_a_conflict(): void
    {
        $this->assignToday($this->technician(), 'C1618');
        $this->asField();

        $board = $this->createBoard()->assertCreated();

        $module = $this->postJson("/api/v1/knx/field/projects/C1618/boards/{$board->json('id')}/modules", [
            'clientId' => 'cm-1',
            'device' => 'SA/S8.16.2.2',
            'slot' => 3,
            'capturedAt' => '2026-10-08T09:05:00+02:00',
        ])->assertCreated();

        $this->assertSame('field', $module->json('origin'));
        $this->assertSame('unreviewed', $module->json('reviewState'));
        $this->assertSame(3, $module->json('slot'));

        // The replay of the same attempt returns its own module (the slot is taken by it).
        $this->postJson("/api/v1/knx/field/projects/C1618/boards/{$board->json('id')}/modules", [
            'clientId' => 'cm-1',
            'device' => 'SA/S8.16.2.2',
            'slot' => 3,
            'capturedAt' => '2026-10-08T09:05:00+02:00',
        ])->assertOk()->assertJsonPath('slot', 3);

        // A DIFFERENT attempt on a taken slot is an explicit 409 with the module.
        $conflict = $this->postJson("/api/v1/knx/field/projects/C1618/boards/{$board->json('id')}/modules", [
            'clientId' => 'cm-2',
            'device' => 'DG/S1.64.1.1',
            'slot' => 3,
            'capturedAt' => '2026-10-08T09:06:00+02:00',
        ])->assertStatus(409);

        $this->assertSame('slot_taken', $conflict->json('code'));
        $this->assertSame('SA/S8.16.2.2', $conflict->json('module.device'));

        // One typed notice for the module; the replay and the rejected attempt add none.
        $this->assertSame(1, KnxNotification::query()->where('kind', KnxNotification::KIND_MODULE_ADDED)->count());
    }

    public function test_a_registration_can_name_its_board_slot_and_channel(): void
    {
        $this->assignToday($this->technician(), 'C1618');
        $this->asField();

        $board = $this->createBoard()->assertCreated();
        $this->postJson("/api/v1/knx/field/projects/C1618/boards/{$board->json('id')}/modules", [
            'clientId' => 'cm-x',
            'device' => 'SA/S8.16.2.2',
            'slot' => 3,
            'capturedAt' => '2026-10-08T09:05:00+02:00',
        ])->assertCreated();

        $roomId = $this->getJson('/api/v1/knx/field/projects/C1618')->assertOk()->json('rooms.0.id');

        $device = $this->postJson('/api/v1/knx/field/projects/C1618/devices', [
            'clientId' => 'cd-1',
            'projectCode' => 'C1618',
            'address' => '1.9.001',
            'type' => 'SA/S8.16.2.2',
            'roomId' => $roomId,
            'boardId' => $board->json('id'),
            'moduleSlot' => 3,
            'channel' => 'A',
            'capturedAt' => '2026-10-08T09:10:00+02:00',
        ])->assertCreated();

        $this->assertSame($board->json('id'), $device->json('boardId'));
        $this->assertSame(3, $device->json('moduleSlot'));
        $this->assertSame('A', $device->json('channel'));

        $stored = KnxDevice::query()->where('client_id', 'cd-1')->sole();
        $this->assertSame(3, $stored->module_slot);
        $this->assertSame('A', $stored->channel);

        // An unknown boardId is a field error, not a silent null.
        $this->postJson('/api/v1/knx/field/projects/C1618/devices', [
            'clientId' => 'cd-2',
            'projectCode' => 'C1618',
            'address' => '1.9.002',
            'type' => 'SA/S8.16.2.2',
            'roomId' => $roomId,
            'boardId' => 'bestaat-niet',
            'capturedAt' => '2026-10-08T09:11:00+02:00',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['boardId']]);
    }

    public function test_the_field_board_writes_refuse_a_project_not_assigned_today(): void
    {
        // Jan is only assigned to C1618; 239870 belongs to Mira.
        $this->assignToday($this->technician(), 'C1618');
        $this->asField();

        $this->postJson('/api/v1/knx/field/projects/239870/boards', [
            'clientId' => 'cb-x',
            'code' => 'E99',
            'name' => 'x',
            'capturedAt' => '2026-10-08T09:00:00+02:00',
        ])->assertForbidden();
    }
}
