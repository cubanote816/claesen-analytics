<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanMarker;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * The boards placed on a plan revision (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * The two facts this pins are the ones the contract insists on: the set belongs to a
 * DOCUMENT (a revision), not to the project, and `PUT` replaces the whole set. The
 * third is the one that protects the data: marking a new revision never moves the
 * previous revision's markers.
 */
final class KnxPlanMarkersTest extends TestCase
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

    private function payload(string $documentId, array $overrides = []): array
    {
        return array_merge([
            'documentId' => $documentId,
            'revision' => 'Rev. H',
            'page' => 1,
            'pageWidthPt' => 4309.0,
            'pageHeightPt' => 2591.0,
            'markers' => [
                ['boardId' => 'caja-1-alsb-leefgroep-gelijkvloers-glv', 'nx' => 0.31, 'ny' => 0.44],
                ['boardId' => 'caja-2-bord-dagbesteding-gelijkvloers-glv', 'nx' => 0.62, 'ny' => 0.18],
            ],
        ], $overrides);
    }

    public function test_the_office_replaces_the_whole_marker_set_and_reads_it_back(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');

        $put = $this->putJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers", [
            'documentId' => (string) $document->getKey(),
            'revision' => 'Rev. C',
            'page' => 1,
            'pageWidthPt' => 4309.0,
            'pageHeightPt' => 2591.0,
            'markers' => [
                ['boardId' => 'caja-1-alsb-leefgroep-gelijkvloers-glv', 'nx' => 0.31, 'ny' => 0.44],
                ['boardId' => 'caja-2-bord-dagbesteding-gelijkvloers-glv', 'nx' => 0.62, 'ny' => 0.18],
            ],
        ])->assertOk();

        $this->assertSame(
            ['documentId', 'revision', 'page', 'pageWidthPt', 'pageHeightPt', 'markers'],
            array_keys($put->json()),
        );
        $this->assertSame((string) $document->getKey(), $put->json('documentId'));
        // The revision is the document's own label, echoed — not the body's.
        $this->assertSame($document->revision, $put->json('revision'));
        $this->assertSame(1, $put->json('page'));
        // JSON does not preserve the zero fraction (4309.0 is sent as 4309), so the
        // comparison casts: the front reads a `number` either way.
        $this->assertSame(4309.0, (float) $put->json('pageWidthPt'));
        $this->assertSame(2591.0, (float) $put->json('pageHeightPt'));
        $this->assertCount(2, $put->json('markers'));

        // The geometry the client measured is stored exactly as sent.
        $marker = KnxPlanMarker::query()->where('document_id', $document->getKey())->orderBy('id')->first();
        $this->assertSame((float) 0.31, $marker->nx);
        $this->assertSame((float) 4309.0, $marker->page_width_pt);

        // GET returns the same thing.
        $get = $this->getJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers")->assertOk();

        $this->assertSame($put->json(), $get->json());
    }

    public function test_the_put_replaces_the_whole_set_and_is_idempotent(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers";

        $this->putJson($url, $this->payload((string) $document->getKey()))->assertOk();

        // A second PUT with a different set does not append: it replaces.
        $second = $this->putJson($url, $this->payload((string) $document->getKey(), [
            'markers' => [
                ['boardId' => 'caja-3-bord-leefgroep-1e-verdieping-v01', 'nx' => 0.1, 'ny' => 0.2],
            ],
        ]))->assertOk();

        $this->assertCount(1, $second->json('markers'));

        // And repeating the exact same body leaves the same rows.
        $this->putJson($url, $this->payload((string) $document->getKey(), [
            'markers' => [
                ['boardId' => 'caja-3-bord-leefgroep-1e-verdieping-v01', 'nx' => 0.1, 'ny' => 0.2],
            ],
        ]))->assertOk();

        $this->assertSame(1, KnxPlanMarker::query()->where('document_id', $document->getKey())->count());
    }

    public function test_a_new_revision_does_not_move_the_old_revisions_markers(): void
    {
        $old = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $other = $this->document('C1618', 'C1618_ETS-export_0409.knxproj');

        $this->putJson("/api/v1/knx/projects/C1618/plans/{$old->getKey()}/markers", $this->payload((string) $old->getKey()))
            ->assertOk();

        // The new revision gets its own set; the old document keeps exactly what it had.
        $this->putJson("/api/v1/knx/projects/C1618/plans/{$other->getKey()}/markers", $this->payload((string) $other->getKey(), [
            'markers' => [
                ['boardId' => 'caja-5-bord-kantoren-2e-verdieping-v02', 'nx' => 0.9, 'ny' => 0.1],
            ],
        ]))->assertOk();

        $stillOld = $this->getJson("/api/v1/knx/projects/C1618/plans/{$old->getKey()}/markers")->assertOk();

        $this->assertCount(2, $stillOld->json('markers'));
        $this->assertSame(
            ['caja-1-alsb-leefgroep-gelijkvloers-glv', 'caja-2-bord-dagbesteding-gelijkvloers-glv'],
            array_column($stillOld->json('markers'), 'boardId'),
        );
    }

    public function test_carry_over_from_uses_the_previous_revision_as_a_base(): void
    {
        $previous = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $next = $this->document('C1618', 'C1618_ETS-export_0409.knxproj');

        $this->putJson("/api/v1/knx/projects/C1618/plans/{$previous->getKey()}/markers", $this->payload((string) $previous->getKey()))
            ->assertOk();

        // Start from the previous revision, correct one board and add another.
        $carried = $this->putJson("/api/v1/knx/projects/C1618/plans/{$next->getKey()}/markers", [
            'documentId' => (string) $next->getKey(),
            'page' => 1,
            'pageWidthPt' => 4309.0,
            'pageHeightPt' => 2591.0,
            'carryOverFrom' => $previous->getKey(),
            'markers' => [
                ['boardId' => 'caja-2-bord-dagbesteding-gelijkvloers-glv', 'nx' => 0.7, 'ny' => 0.2],
                ['boardId' => 'caja-4-bord-dagbesteding-1e-verdieping-v01', 'nx' => 0.5, 'ny' => 0.5],
            ],
        ])->assertOk();

        $byBoard = collect($carried->json('markers'))->keyBy('boardId');

        $this->assertCount(3, $byBoard);
        // Carried, unchanged.
        $this->assertSame(0.31, (float) $byBoard['caja-1-alsb-leefgroep-gelijkvloers-glv']['nx']);
        // Carried but corrected by the body.
        $this->assertSame(0.7, (float) $byBoard['caja-2-bord-dagbesteding-gelijkvloers-glv']['nx']);
        // Added in this revision.
        $this->assertSame(0.5, (float) $byBoard['caja-4-bord-dagbesteding-1e-verdieping-v01']['ny']);
    }

    public function test_a_duplicate_board_or_an_out_of_range_fraction_is_a_field_error(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers";

        // The table has one marker per board per document; two would mean one delete
        // removes both, so the API rejects what the database forbids.
        $this->putJson($url, $this->payload((string) $document->getKey(), [
            'markers' => [
                ['boardId' => 'same-board', 'nx' => 0.1, 'ny' => 0.1],
                ['boardId' => 'same-board', 'nx' => 0.2, 'ny' => 0.2],
            ],
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['markers.0.boardId']]);

        $this->putJson($url, $this->payload((string) $document->getKey(), [
            'markers' => [['boardId' => 'some-board', 'nx' => 1.5, 'ny' => 0.2]],
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['markers.0.nx']]);

        // The document id in the body has to be this document.
        $this->putJson($url, $this->payload('999999'))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['documentId']]);
    }

    public function test_a_missing_document_or_a_cross_project_one_is_a_404(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');

        $this->getJson('/api/v1/knx/projects/C1618/plans/999999/markers')->assertNotFound();
        $this->getJson('/api/v1/knx/projects/NOPE/plans/1/markers')->assertNotFound();

        // The same document id under another project is unknown there: the set belongs
        // to the document, and the document belongs to C1618.
        $this->getJson("/api/v1/knx/projects/239870/plans/{$document->getKey()}/markers")->assertNotFound();
    }

    public function test_another_project_does_not_see_the_markers(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $other = $this->document('239870', 'Hectaar_2e_verdieping_verlichting.pdf');

        $this->putJson("/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers", $this->payload((string) $document->getKey()))
            ->assertOk();

        $otherSet = $this->getJson("/api/v1/knx/projects/239870/plans/{$other->getKey()}/markers")->assertOk();

        $this->assertSame([], $otherSet->json('markers'));
        // An empty set still answers the contract's shape, with page 1 by default.
        $this->assertSame(1, $otherSet->json('page'));
        $this->assertNull($otherSet->json('pageWidthPt'));
    }

    public function test_the_marker_endpoints_require_the_office_app(): void
    {
        $document = $this->document('C1618', 'UV_C1618_Gelijkvloers_Wayfinding.pdf');
        $url = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/markers";

        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
        $this->putJson($url, [])->assertUnauthorized();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );
        $this->getJson($url)->assertUnauthorized();
    }
}
