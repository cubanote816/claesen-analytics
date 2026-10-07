<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxBoardLink;
use Modules\Knx\Models\KnxBoardModule;
use Modules\Knx\Models\KnxDocument;
use Tests\TestCase;

/**
 * The cabinets of a project (KNX-3, docs/BACKEND-API.md §4.3).
 *
 * Fed by a real worklist import, and pinned on the two things measuring the project's
 * own 160-row worklist revealed: modules need an identity of instance (`slot`), and a
 * channel serves one room while carrying several communication objects of it. The
 * board `id` is the same identity a plan marker carries, which is the coupling that
 * made upload and marking one contract.
 */
final class KnxBoardsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $directory = null;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        // The demo fixture already models boards (and the devices pointing at them) for
        // these projects. This test is about the import's own structure, so it starts
        // from a clean board slate; `knx_devices.board_id` is nullOnDelete, so nothing
        // else breaks.
        KnxBoard::query()->delete();

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

    /**
     * A small but real worklist. The last row of Caja 1 restarts channel A after D:
     * that is the KNX signal of a second physical module of the same order number.
     */
    private function worklistDirectory(): string
    {
        $directory = sys_get_temp_dir().'/knx-worklist-'.uniqid();

        File::ensureDirectoryExists($directory);

        // The governed workspace writes a BOM; the import must survive it.
        File::put($directory.'/linking-worklist.csv', "\xEF\xBB\xBF".implode("\r\n", [
            'Box,Device,Channel,Room,Object,GA,GA_Name,DPT',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,A,Inkomhal,Schakelen (Cmd),2/0/1,L-GLV-Inkomhal-Cmd,DPST-1-1',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,A,Inkomhal,Status (Fb),2/1/1,L-GLV-Inkomhal-Fb,DPST-1-11',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,B,Eetruimte,Schakelen (Cmd),2/0/2,L-GLV-Eetruimte-Cmd,DPST-1-1',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,C,Eetruimte,Schakelen (Cmd),2/0/3,L-GLV-Eetruimte-Cmd2,DPST-1-1',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,D,Eetruimte,Schakelen (Cmd),2/0/4,L-GLV-Eetruimte-Cmd3,DPST-1-1',
            'Caja 1 - Leefgroep (GLV),SA/S4.16.2.2,A,Zolder,Schakelen (Cmd),2/0/5,L-GLV-Zolder-Cmd,DPST-1-1',
            'Caja 2 - Kantoren (V01),JRA/S4.230.5.1,A,Bureau,Positie,3/0/1,L-V01-Bureau-Pos,DPST-1-1',
        ]));

        File::put($directory.'/group-addresses.csv', "\xEF\xBB\xBF".implode("\r\n", [
            'Address,GA_Name,DPT,Main,Middle,Role,Zone,Wing,Room,Board,Source',
            '2/0/1,L-GLV-Inkomhal-Cmd,DPST-1-1,2,0,switch_cmd,GLV,,Inkomhal,Caja 1,convention',
            '2/1/1,L-GLV-Inkomhal-Fb,DPST-1-11,2,1,switch_fb,GLV,,Inkomhal,Caja 1,convention',
            '2/0/2,L-GLV-Eetruimte-Cmd,DPST-1-1,2,0,switch_cmd,GLV,,Eetruimte,Caja 1,convention',
            '2/0/3,L-GLV-Eetruimte-Cmd2,DPST-1-1,2,0,switch_cmd,GLV,,Eetruimte,Caja 1,convention',
            '2/0/4,L-GLV-Eetruimte-Cmd3,DPST-1-1,2,0,switch_cmd,GLV,,Eetruimte,Caja 1,convention',
            '2/0/5,L-GLV-Zolder-Cmd,DPST-1-1,2,0,switch_cmd,GLV,,Zolder,Caja 1,convention',
            '3/0/1,L-V01-Bureau-Pos,DPST-1-1,3,0,shade_position,V01,,Bureau,Caja 2,convention',
        ]));

        return $this->directory = $directory;
    }

    private function import(string $code = 'C1618'): void
    {
        $this->artisan('knx:import-worklist', [
            'directory' => $this->worklistDirectory(),
            'code' => $code,
        ])->assertSuccessful();
    }

    public function test_the_office_imports_a_worklist_and_gets_the_boards_shape(): void
    {
        $this->import();

        $payload = $this->getJson('/api/v1/knx/projects/C1618/boards')->assertOk()->json();

        $this->assertSame(['projectCode', 'boards'], array_keys($payload));
        $this->assertSame('C1618', $payload['projectCode']);
        $this->assertCount(2, $payload['boards']);

        $first = $payload['boards'][0];

        // The board's identity is its slug — the same string the front's own import
        // produced — and the floor is derived from the box name's suffix.
        $this->assertSame('caja-1-leefgroep-glv', $first['id']);
        $this->assertSame('Caja 1 - Leefgroep (GLV)', $first['name']);
        $this->assertSame('gelijkvloers', $first['floor']);
        $this->assertSame(['device', 'slot', 'channels'], array_keys($first['modules'][0]));

        $second = $payload['boards'][1];

        $this->assertSame('caja-2-kantoren-v01', $second['id']);
        $this->assertSame('1e verdieping', $second['floor']);
    }

    public function test_a_module_has_its_own_slot_and_a_channel_carries_its_objects(): void
    {
        $this->import();

        $first = $this->getJson('/api/v1/knx/projects/C1618/boards')->assertOk()->json('boards.0');

        // Two instances of the same order number: the second one is told apart by slot.
        $this->assertCount(2, $first['modules']);
        $this->assertSame([1, 2], array_column($first['modules'], 'slot'));
        $this->assertSame('SA/S4.16.2.2', $first['modules'][0]['device']);
        $this->assertSame('SA/S4.16.2.2', $first['modules'][1]['device']);

        // Channels in the worklist's own order, and channel A carries two objects of
        // the same room (Schakelen + Status) — the measured model.
        $channels = collect($first['modules'][0]['channels'])->keyBy('channel');

        $this->assertSame(['A', 'B', 'C', 'D'], array_column($first['modules'][0]['channels'], 'channel'));

        $channelA = $channels['A'];
        $this->assertSame(
            ['room', 'object', 'role', 'ga', 'gaName', 'dpt'],
            array_keys($channelA['links'][0]),
        );
        $this->assertSame('Inkomhal', $channelA['links'][0]['room']);
        $this->assertSame('switch_cmd', $channelA['links'][0]['role']);
        $this->assertSame('L-GLV-Inkomhal-Cmd', $channelA['links'][0]['gaName']);
        $this->assertSame('DPST-1-11', $channelA['links'][1]['dpt']);

        // Every link of one channel carries the same room.
        $this->assertSame(['Inkomhal', 'Inkomhal'], array_column($channelA['links'], 'room'));

        // The second module restarts at A, on another room.
        $this->assertSame('A', $first['modules'][1]['channels'][0]['channel']);
        $this->assertSame('Zolder', $first['modules'][1]['channels'][0]['links'][0]['room']);
    }

    public function test_the_board_id_is_the_identity_a_marker_uses(): void
    {
        $this->import();

        $boardId = $this->getJson('/api/v1/knx/projects/C1618/boards')->assertOk()->json('boards.0.id');
        $document = KnxDocument::query()
            ->whereHas('project', fn ($query) => $query->where('code', 'C1618'))
            ->firstOrFail();

        // A marker placed on the board the endpoint just returned is accepted and read
        // back with the same identity — the coupling between the two contracts.
        $this->putJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers", [
            'page' => 1,
            'markers' => [['boardId' => $boardId, 'nx' => 0.31, 'ny' => 0.44]],
        ])->assertOk();

        $this->assertSame(
            $boardId,
            $this->getJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers")->json('markers.0.boardId'),
        );
    }

    public function test_importing_twice_leaves_the_same_structure(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(2, KnxBoard::query()->count());
        $this->assertSame(3, KnxBoardModule::query()->count());
        // Seven worklist rows, one link each.
        $this->assertSame(7, KnxBoardLink::query()->count());

        // The board row survives, so a device pointing at it keeps pointing at it.
        $this->assertCount(2, $this->getJson('/api/v1/knx/projects/C1618/boards')->assertOk()->json('boards'));
    }

    public function test_a_project_without_an_import_answers_an_empty_board_list(): void
    {
        $payload = $this->getJson('/api/v1/knx/projects/239870/boards')->assertOk()->json();

        $this->assertSame('239870', $payload['projectCode']);
        $this->assertSame([], $payload['boards']);
    }

    public function test_another_project_does_not_see_an_imported_project(): void
    {
        $this->import();

        $this->getJson('/api/v1/knx/projects/239870/boards')->assertOk()->assertJsonPath('boards', []);
    }

    public function test_the_command_rejects_an_unknown_project_or_a_missing_worklist(): void
    {
        $this->artisan('knx:import-worklist', [
            'directory' => $this->worklistDirectory(),
            'code' => 'NOPE',
        ])->assertFailed();

        $this->artisan('knx:import-worklist', [
            'directory' => sys_get_temp_dir().'/does-not-exist-'.uniqid(),
            'code' => 'C1618',
        ])->assertFailed();
    }

    public function test_the_boards_endpoint_requires_the_office_app(): void
    {
        $this->import();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/knx/projects/C1618/boards')->assertUnauthorized();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );
        $this->getJson('/api/v1/knx/projects/C1618/boards')->assertUnauthorized();
    }

    public function test_the_unknown_project_code_is_a_404(): void
    {
        $this->getJson('/api/v1/knx/projects/NOPE/boards')->assertNotFound();
    }
}
