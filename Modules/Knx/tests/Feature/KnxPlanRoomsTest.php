<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxPlanRoom;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The room model of a plan revision (KNX-45).
 *
 * Pins the three facts the contract insists on: the set belongs to a DOCUMENT (a
 * revision) and a page, never to the project; `PUT` replaces the whole set (an empty one
 * is valid — the KNX-18 lesson); and a carry-over copies the SAME page, returning what it
 * copies to `proposed` because a new revision may have changed the drawing.
 */
final class KnxPlanRoomsTest extends TestCase
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

    private function document(string $code, string $name): KnxDocument
    {
        $project = KnxProject::query()->where('code', $code)->sole();

        return KnxDocument::query()->where('project_id', $project->getKey())->where('name', $name)->sole();
    }

    private function room(string $code, string $name): KnxProjectRoom
    {
        $project = KnxProject::query()->where('code', $code)->sole();

        return KnxProjectRoom::query()->where('project_id', $project->getKey())->where('name', $name)->sole();
    }

    /**
     * @param  array<int, array{roomId: string, source: string, state: string}>  $rooms
     * @return array<string, mixed>
     */
    private function payload(array $rooms, array $overrides = []): array
    {
        return array_merge([
            'page' => 1,
            'pageWidthPt' => 4309.0,
            'pageHeightPt' => 2591.0,
            'rooms' => $rooms,
        ], $overrides);
    }

    private function roomRow(string $roomId, string $state = 'confirmed'): array
    {
        return [
            'roomId' => $roomId,
            'polygon' => [[0.31, 0.44], [0.42, 0.44], [0.42, 0.55]],
            'labelAt' => [0.365, 0.495],
            'areaM2' => 137.0,
            'source' => 'manual',
            'state' => $state,
        ];
    }

    public function test_the_model_of_a_page_starts_empty_with_proposal_none(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');

        $payload = $this->getJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms")->assertOk()->json();

        $this->assertSame(
            ['documentId', 'revision', 'page', 'pageWidthPt', 'pageHeightPt', 'proposalStatus', 'rooms'],
            array_keys($payload),
        );
        $this->assertSame((string) $document->getKey(), $payload['documentId']);
        $this->assertSame('Rev. C', $payload['revision']);
        $this->assertSame('none', $payload['proposalStatus']);
        $this->assertSame([], $payload['rooms']);
    }

    public function test_the_put_replaces_the_whole_model_and_reads_it_back(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";
        $inkomhal = $this->room('C1618', 'Inkomhal');
        $vergaderzaal = $this->room('C1618', 'Vergaderzaal');

        $put = $this->putJson($url, $this->payload([
            $this->roomRow((string) $inkomhal->getKey()),
            $this->roomRow((string) $vergaderzaal->getKey()),
        ]))->assertOk();

        $this->assertCount(2, $put->json('rooms'));
        // JSON drops the trailing `.0` (no JSON_PRESERVE_ZERO_FRACTION), so compare by value.
        $this->assertEqualsWithDelta(4309.0, $put->json('pageWidthPt'), 0.0001);

        // The geometry survives the round trip exactly as sent: the server is a
        // caretaker of what was drawn, not an interpreter of the PDF.
        $get = $this->getJson($url)->assertOk()->json();
        $this->assertSame($put->json('rooms'), $get['rooms']);
        $this->assertSame([0.31, 0.44], $get['rooms'][0]['polygon'][0]);
        $this->assertEqualsWithDelta(137.0, $get['rooms'][0]['areaM2'], 0.0001);

        // A second PUT replaces: one room only, the other is gone.
        $second = $this->putJson($url, $this->payload([$this->roomRow((string) $inkomhal->getKey())]))->assertOk();
        $this->assertCount(1, $second->json('rooms'));
        $this->assertSame((string) $inkomhal->getKey(), $second->json('rooms.0.roomId'));
    }

    public function test_an_empty_set_clears_the_page_but_a_missing_key_is_rejected(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";
        $inkomhal = $this->room('C1618', 'Inkomhal');

        $this->putJson($url, $this->payload([$this->roomRow((string) $inkomhal->getKey())]))->assertOk();

        // `present|array`: the key must come, but empty is a valid body (clear the page).
        $empty = $this->putJson($url, ['page' => 1, 'rooms' => []])->assertOk();
        $this->assertSame([], $empty->json('rooms'));
        $this->assertSame(0, KnxPlanRoom::query()->where('document_id', $document->getKey())->count());

        // The missing key is a different body and stays a 422.
        $this->putJson($url, ['page' => 1])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['rooms']]);
    }

    public function test_carry_over_from_copies_the_same_page_and_returns_rooms_to_proposed(): void
    {
        $previous = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $next = $this->document('C1618', 'C1618_ETS-export_0409.knxproj');
        $inkomhal = $this->room('C1618', 'Inkomhal');
        $vergaderzaal = $this->room('C1618', 'Vergaderzaal');

        // The previous revision has a CONFIRMED room on page 1 and a different one on page 2.
        $this->putJson("/api/v1/knx/projects/C1618/plans/{$previous->getKey()}/rooms", $this->payload([
            $this->roomRow((string) $inkomhal->getKey()),
        ]))->assertOk();
        $this->putJson("/api/v1/knx/projects/C1618/plans/{$previous->getKey()}/rooms", $this->payload([
            $this->roomRow((string) $vergaderzaal->getKey()),
        ], ['page' => 2]))->assertOk();

        // Carrying page 1 brings ONLY the room of page 1, and it comes back as proposed.
        $carried = $this->putJson("/api/v1/knx/projects/C1618/plans/{$next->getKey()}/rooms", [
            'page' => 1,
            'carryOverFrom' => $previous->getKey(),
            'rooms' => [],
        ])->assertOk();

        $this->assertCount(1, $carried->json('rooms'));
        $this->assertSame((string) $inkomhal->getKey(), $carried->json('rooms.0.roomId'));
        $this->assertSame('proposed', $carried->json('rooms.0.state'));
        // The source revision is untouched.
        $this->assertSame(
            1,
            KnxPlanRoom::query()->where('document_id', $previous->getKey())->where('page', 1)->count(),
        );
    }

    public function test_a_room_must_belong_to_the_project_and_the_document_to_the_route(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";

        // A room of another project of the same tenant is not a valid target.
        $otherRoom = $this->room('239870', 'Inkomhal');
        $this->putJson($url, $this->payload([$this->roomRow((string) $otherRoom->getKey())]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['rooms.0.roomId']]);

        // A room id sent twice is rejected before the unique key turns it into a 500.
        $inkomhal = $this->room('C1618', 'Inkomhal');
        $this->putJson($url, $this->payload([
            $this->roomRow((string) $inkomhal->getKey()),
            $this->roomRow((string) $inkomhal->getKey()),
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['rooms.0.roomId']]);

        // A document of another project is a 404 on both routes: the document is
        // resolved inside the project of the code.
        $this->getJson('/api/v1/knx/projects/C1618/plans/999999/rooms')->assertNotFound();
        $this->putJson('/api/v1/knx/projects/C1618/plans/999999/rooms', $this->payload([]))->assertNotFound();
        $this->getJson('/api/v1/knx/projects/NOPE/plans/1/rooms')->assertNotFound();
    }

    public function test_a_document_of_another_organization_is_a_404(): void
    {
        $other = Organization::factory()->create(['slug' => 'other-plans']);
        $foreignProject = KnxProject::factory()->create(['organization_id' => $other->id, 'code' => 'FOREIGN-PLAN']);
        $foreignDocument = KnxDocument::factory()->create(['project_id' => $foreignProject->getKey()]);

        $this->getJson("/api/v1/knx/projects/FOREIGN-PLAN/plans/{$foreignDocument->getKey()}/rooms")
            ->assertNotFound();
    }

    public function test_the_model_endpoints_refuse_a_field_account(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->getJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms")->assertUnauthorized();
    }

    /** @return array<string, mixed> */
    private function contourRow(string $roomId, array $overrides = []): array
    {
        return array_merge($this->roomRow($roomId), [
            'source' => 'pdf',
            'state' => 'proposed',
            // An L with an interior hole.
            'polygon' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.5], [0.5, 0.5], [0.5, 0.9], [0.1, 0.9]],
            'geometry' => 'contour',
            'holes' => [[[0.2, 0.2], [0.3, 0.2], [0.3, 0.3], [0.2, 0.3]]],
            'geometryNote' => 'area-mismatch',
            'processingVersion' => 'kantoor-plan/3',
        ], $overrides);
    }

    public function test_the_provenance_and_topology_fields_round_trip_exactly(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";
        $inkomhal = (string) $this->room('C1618', 'Inkomhal')->getKey();
        $vergaderzaal = (string) $this->room('C1618', 'Vergaderzaal')->getKey();
        $row = $this->contourRow($inkomhal);
        $approx = $this->contourRow($vergaderzaal, [
            'polygon' => [[0.5, 0.5], [0.6, 0.5], [0.6, 0.6]],
            'geometry' => 'area',
            'holes' => [],
            'geometryNote' => 'open',
            'processingVersion' => null,
        ]);

        $put = $this->putJson($url, $this->payload([$row, $approx]))->assertOk();
        $get = $this->getJson($url)->assertOk();

        foreach ([$put, $get] as $response) {
            $this->assertSame('contour', $response->json('rooms.0.geometry'));
            $this->assertSame($row['holes'], $response->json('rooms.0.holes'));
            $this->assertSame('area-mismatch', $response->json('rooms.0.geometryNote'));
            $this->assertSame('kantoor-plan/3', $response->json('rooms.0.processingVersion'));
            $this->assertSame('area', $response->json('rooms.1.geometry'));
            $this->assertSame([], $response->json('rooms.1.holes'));
            $this->assertSame('open', $response->json('rooms.1.geometryNote'));
            $this->assertNull($response->json('rooms.1.processingVersion'));
        }

        // The set keys do not change: only the per-room keys grew.
        $this->assertSame(
            ['documentId', 'revision', 'page', 'pageWidthPt', 'pageHeightPt', 'proposalStatus', 'rooms'],
            array_keys($get->json()),
        );
        $this->assertSame(
            ['roomId', 'polygon', 'labelAt', 'areaM2', 'source', 'state', 'geometry', 'holes', 'geometryNote', 'processingVersion'],
            array_keys($get->json('rooms.0')),
        );
    }

    public function test_omitting_the_new_fields_stores_and_returns_the_legacy_defaults(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";
        $inkomhal = $this->room('C1618', 'Inkomhal');
        $vergaderzaal = $this->room('C1618', 'Vergaderzaal');

        $pdf = $this->roomRow((string) $vergaderzaal->getKey());
        $pdf['source'] = 'pdf';

        $put = $this->putJson($url, $this->payload([$this->roomRow((string) $inkomhal->getKey()), $pdf]))->assertOk();

        $this->assertSame('manual', $put->json('rooms.0.geometry'));
        $this->assertSame('area', $put->json('rooms.1.geometry'));
        $this->assertSame([], $put->json('rooms.0.holes'));
        $this->assertNull($put->json('rooms.0.geometryNote'));
        $this->assertNull($put->json('rooms.0.processingVersion'));
        $this->assertSame('manual', KnxPlanRoom::query()->where('room_id', $inkomhal->getKey())->sole()->geometry);

        // A legacy row (columns NULL) reads with the same defaults.
        KnxPlanRoom::query()->update(['geometry' => null, 'holes' => null]);
        $get = $this->getJson($url)->assertOk();
        $this->assertSame('manual', $get->json('rooms.0.geometry'));
        $this->assertSame('area', $get->json('rooms.1.geometry'));
        $this->assertSame([], $get->json('rooms.1.holes'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidRoomProvider(): array
    {
        $touching = [[0.1, 0.3], [0.3, 0.3], [0.3, 0.5], [0.1, 0.5]];

        return [
            'geometry outside the enum' => [['geometry' => 'blob'], 'rooms.0.geometry'],
            'note outside the enum' => [['geometryNote' => 'nope'], 'rooms.0.geometryNote'],
            'area note on a contour' => [['geometryNote' => 'open'], 'rooms.0.geometryNote'],
            'contour note on an area' => [['geometry' => 'area', 'geometryNote' => 'area-mismatch'], 'rooms.0.geometryNote'],
            'a note on a manual room' => [['geometry' => 'manual', 'geometryNote' => 'open'], 'rooms.0.geometryNote'],
            'hole ring too short' => [['holes' => [[[0.2, 0.2], [0.3, 0.2]]]], 'rooms.0.holes.0'],
            'hole fraction out of range' => [['holes' => [[[0.2, 0.2], [0.3, 0.2], [1.3, 0.3]]]], 'rooms.0.holes.0.2.0'],
            'polygon fraction out of range' => [['polygon' => [[0.1, 0.1], [0.9, 0.1], [0.9, 1.5]]], 'rooms.0.polygon.2.1'],
            'processing version too long' => [['processingVersion' => str_repeat('v', 33)], 'rooms.0.processingVersion'],
            'bow-tie polygon' => [['polygon' => [[0.1, 0.1], [0.9, 0.9], [0.9, 0.1], [0.1, 0.9]]], 'rooms.0.polygon'],
            'hole touching the outer ring' => [['holes' => [$touching]], 'rooms.0.holes'],
        ];
    }

    #[DataProvider('invalidRoomProvider')]
    public function test_inconsistent_or_invalid_geometry_is_a_422_that_stores_nothing(array $overrides, string $errorKey): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms";
        $inkomhal = (string) $this->room('C1618', 'Inkomhal')->getKey();

        // `holes` default of the helper is replaced wholesale, and the note is cleared so
        // the case under test is the only problem.
        $row = $this->contourRow($inkomhal, array_merge(['geometryNote' => null], $overrides));

        $this->putJson($url, $this->payload([$row]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => [$errorKey]]);

        $this->assertSame(0, KnxPlanRoom::query()->where('document_id', $document->getKey())->count());
    }

    public function test_more_than_fifty_holes_are_rejected(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $inkomhal = (string) $this->room('C1618', 'Inkomhal')->getKey();
        $holes = array_fill(0, 51, [[0.2, 0.2], [0.3, 0.2], [0.3, 0.3]]);

        $this->putJson(
            "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms",
            $this->payload([$this->contourRow($inkomhal, ['geometryNote' => null, 'holes' => $holes])]),
        )->assertStatus(422)->assertJsonStructure(['errors' => ['rooms.0.holes']]);
    }

    public function test_carry_over_keeps_the_geometry_fields_but_returns_the_state_to_proposed(): void
    {
        $previous = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $next = $this->document('C1618', 'C1618_ETS-export_0409.knxproj');
        $inkomhal = (string) $this->room('C1618', 'Inkomhal')->getKey();
        $row = $this->contourRow($inkomhal, ['state' => 'confirmed']);

        $this->putJson("/api/v1/knx/projects/C1618/plans/{$previous->getKey()}/rooms", $this->payload([$row]))->assertOk();

        $carried = $this->putJson("/api/v1/knx/projects/C1618/plans/{$next->getKey()}/rooms", [
            'page' => 1,
            'carryOverFrom' => $previous->getKey(),
            'rooms' => [],
        ])->assertOk();

        $this->assertSame('proposed', $carried->json('rooms.0.state'));
        $this->assertSame('contour', $carried->json('rooms.0.geometry'));
        $this->assertSame($row['holes'], $carried->json('rooms.0.holes'));
        $this->assertSame('area-mismatch', $carried->json('rooms.0.geometryNote'));
        $this->assertSame('kantoor-plan/3', $carried->json('rooms.0.processingVersion'));
        $this->assertSame($row['polygon'], $carried->json('rooms.0.polygon'));
    }

    public function test_the_field_model_exposes_geometry_and_holes_of_confirmed_rooms(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $inkomhal = (string) $this->room('C1618', 'Inkomhal')->getKey();
        $vergaderzaal = (string) $this->room('C1618', 'Vergaderzaal')->getKey();
        $confirmed = $this->contourRow($inkomhal, ['state' => 'confirmed', 'geometryNote' => null]);

        $this->putJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/rooms", $this->payload([
            $confirmed,
            $this->contourRow($vergaderzaal),
        ]))->assertOk();

        // A legacy row without the columns reads with the defaults on the field side too.
        $technician = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();
        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $technician->getKey(), 'date' => now()->toDateString()],
            ['project_id' => KnxProject::query()->where('code', 'C1618')->sole()->getKey()],
        );
        $this->actingAs(User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(), 'sanctum');

        $model = $this->getJson("/api/v1/knx/field/projects/C1618/plans/{$document->getKey()}/model")->assertOk()->json();

        $this->assertCount(1, $model['rooms']);
        $this->assertSame('contour', $model['rooms'][0]['geometry']);
        $this->assertSame($confirmed['holes'], $model['rooms'][0]['holes']);

        KnxPlanRoom::query()->update(['geometry' => null, 'holes' => null]);
        $legacy = $this->getJson("/api/v1/knx/field/projects/C1618/plans/{$document->getKey()}/model")->assertOk()->json();
        $this->assertSame('area', $legacy['rooms'][0]['geometry']);
        $this->assertSame([], $legacy['rooms'][0]['holes']);
    }
}
