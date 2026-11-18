<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Tests\TestCase;

/**
 * KNX-52 — the office models a cabinet by hand.
 *
 * The worklist carries most of the structure, but the office sometimes has to add a
 * board the worklist never had or a module that appeared on site. The two facts this
 * pins: every write leaves its provenance (`origin`, `reviewState`, `updatedBy`), and a
 * re-import never overwrites what a person wrote.
 */
final class KnxBoardWritesTest extends TestCase
{
    use RefreshDatabase;

    private ?string $directory = null;

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

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            File::deleteDirectory($this->directory);
        }

        parent::tearDown();
    }

    private function room(): KnxProjectRoom
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        return KnxProjectRoom::query()->where('project_id', $project->getKey())->where('name', 'Inkomhal')->sole();
    }

    private function worklistDirectory(): string
    {
        $directory = sys_get_temp_dir().'/knx-board-writes-'.uniqid();
        File::ensureDirectoryExists($directory);

        File::put($directory.'/linking-worklist.csv', implode("\r\n", [
            'Box,Device,Channel,Room,Object,GA,GA_Name,DPT',
            'Caja 9 - Test (GLV),SA/S4.16.2.2,A,Inkomhal,Schakelen (Cmd),9/0/1,L-Test-Cmd,DPST-1-1',
        ]));

        File::put($directory.'/group-addresses.csv', implode("\r\n", [
            'Address,GA_Name,DPT,Main,Middle,Role,Zone,Wing,Room,Board,Source',
            '9/0/1,L-Test-Cmd,DPST-1-1,9,0,switch_cmd,GLV,,Inkomhal,Caja 9,convention',
        ]));

        return $this->directory = $directory;
    }

    public function test_the_office_creates_a_board_and_it_is_born_confirmed(): void
    {
        $response = $this->postJson('/api/v1/knx/projects/C1618/boards', [
            'code' => 'E99',
            'name' => 'Bord technieken (TEST)',
            'floor' => 'Gelijkvloers',
        ])->assertCreated();

        $this->assertSame('bord-technieken-test', $response->json('id'));
        $this->assertSame('E99', $response->json('code'));
        $this->assertSame('Gelijkvloers', $response->json('floor'));
        $this->assertSame('office', $response->json('origin'));
        $this->assertSame('confirmed', $response->json('reviewState'));
        $this->assertSame('L. Smet', $response->json('updatedBy'));
        $this->assertSame([], $response->json('modules'));

        // The id is the same string a marker carries: the slug, stable across renames.
        $renamed = $this->patchJson('/api/v1/knx/projects/C1618/boards/bord-technieken-test', [
            'name' => 'Bord technieken (TEST) hernoemd',
        ])->assertOk();

        $this->assertSame('bord-technieken-test', $renamed->json('id'));
        $this->assertSame('Bord technieken (TEST) hernoemd', $renamed->json('name'));
    }

    public function test_a_taken_slot_is_a_conflict_carrying_the_module_that_occupies_it(): void
    {
        $this->postJson('/api/v1/knx/projects/C1618/boards', ['code' => 'E99', 'name' => 'Bord test'])
            ->assertCreated();

        $module = $this->postJson('/api/v1/knx/projects/C1618/boards/bord-test/modules', [
            'device' => 'SA/S8.16.2.2',
            'slot' => 2,
        ])->assertCreated();

        $this->assertSame('office', $module->json('origin'));
        $this->assertSame('confirmed', $module->json('reviewState'));
        $this->assertSame([], $module->json('channels'));

        // A second module on the same slot is an explicit 409 with the current module,
        // never a silent overwrite.
        $conflict = $this->postJson('/api/v1/knx/projects/C1618/boards/bord-test/modules', [
            'device' => 'DG/S1.64.1.1',
            'slot' => 2,
        ])->assertStatus(409);

        $this->assertSame('slot_taken', $conflict->json('code'));
        $this->assertSame('SA/S8.16.2.2', $conflict->json('module.device'));
        $this->assertSame(2, $conflict->json('module.slot'));
        $this->assertSame(1, KnxBoardModule::query()->where('board_id', KnxBoard::query()->where('slug', 'bord-test')->sole()->getKey())->count());
    }

    public function test_the_office_edits_and_removes_a_module(): void
    {
        $this->postJson('/api/v1/knx/projects/C1618/boards', ['code' => 'E99', 'name' => 'Bord test'])->assertCreated();
        $this->postJson('/api/v1/knx/projects/C1618/boards/bord-test/modules', ['device' => 'SA/S8.16.2.2', 'slot' => 1])->assertCreated();

        $edited = $this->patchJson('/api/v1/knx/projects/C1618/boards/bord-test/modules/1', [
            'device' => 'DG/S1.64.1.1',
        ])->assertOk();

        $this->assertSame('DG/S1.64.1.1', $edited->json('device'));

        $this->deleteJson('/api/v1/knx/projects/C1618/boards/bord-test/modules/1')->assertNoContent();

        $this->getJson('/api/v1/knx/projects/C1618/boards')->assertOk();
        $this->assertSame(0, KnxBoardModule::query()->count());
    }

    public function test_the_office_replaces_the_links_of_a_channel(): void
    {
        $room = $this->room();

        $this->postJson('/api/v1/knx/projects/C1618/boards', ['code' => 'E99', 'name' => 'Bord test'])->assertCreated();
        $this->postJson('/api/v1/knx/projects/C1618/boards/bord-test/modules', ['device' => 'SA/S8.16.2.2', 'slot' => 1])->assertCreated();

        $url = '/api/v1/knx/projects/C1618/boards/bord-test/modules/1/channels/A/links';

        $linked = $this->putJson($url, ['links' => [
            ['roomId' => (string) $room->getKey(), 'object' => 'Schakelen (Cmd)', 'role' => 'switch_cmd', 'ga' => '9/0/1', 'gaName' => 'L-Test-Cmd', 'dpt' => 'DPST-1-1'],
            ['roomId' => (string) $room->getKey(), 'object' => 'Status (Fb)', 'role' => 'switch_fb', 'ga' => '9/1/1', 'gaName' => 'L-Test-Fb', 'dpt' => 'DPST-1-11'],
        ]])->assertOk();

        $this->assertCount(1, $linked->json('channels'));
        $this->assertSame('A', $linked->json('channels.0.channel'));
        $this->assertCount(2, $linked->json('channels.0.links'));
        $this->assertSame((string) $room->getKey(), $linked->json('channels.0.links.0.roomId'));

        // Replacing with an empty set clears the channel.
        $cleared = $this->putJson($url, ['links' => []])->assertOk();
        $this->assertSame([], $cleared->json('channels'));

        // A room of another project is not a valid target.
        $foreignRoom = KnxProjectRoom::query()
            ->where('project_id', KnxProject::query()->where('code', '239870')->sole()->getKey())
            ->firstOrFail();
        $this->putJson($url, ['links' => [
            ['roomId' => (string) $foreignRoom->getKey(), 'object' => 'x', 'role' => 'switch_cmd', 'ga' => '9/0/9', 'gaName' => 'x', 'dpt' => 'DPST-1-1'],
        ]])->assertStatus(422)->assertJsonStructure(['errors' => ['links.0.roomId']]);
    }

    public function test_a_reimport_does_not_overwrite_a_board_a_person_edited(): void
    {
        $directory = $this->worklistDirectory();

        $this->artisan('knx:import-worklist', ['directory' => $directory, 'code' => 'C1618'])->assertSuccessful();

        $board = KnxBoard::query()->where('slug', 'caja-9-test-glv')->sole();
        $this->assertSame('import', $board->origin);

        // The office edits the module by hand.
        $this->patchJson("/api/v1/knx/projects/C1618/boards/{$board->slug}/modules/1", [
            'device' => 'EDITED-BY-OFFICE',
        ])->assertOk();

        // A re-import must leave it alone, and say so.
        $this->artisan('knx:import-worklist', ['directory' => $directory, 'code' => 'C1618'])
            ->expectsOutputToContain('Skipped 1 board')
            ->assertSuccessful();

        $module = KnxBoardModule::query()->where('board_id', $board->getKey())->sole();
        $this->assertSame('EDITED-BY-OFFICE', $module->device);
        $this->assertSame('office', $module->origin);
    }

    public function test_the_board_write_routes_refuse_the_field_app_and_unknown_boards(): void
    {
        $this->patchJson('/api/v1/knx/projects/C1618/boards/nope', ['name' => 'x'])->assertNotFound();
        $this->patchJson('/api/v1/knx/projects/NOPE/boards/nope', ['name' => 'x'])->assertNotFound();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->postJson('/api/v1/knx/projects/C1618/boards', ['code' => 'E99', 'name' => 'x'])->assertUnauthorized();
    }
}
