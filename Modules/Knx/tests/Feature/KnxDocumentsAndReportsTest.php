<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Http\Resources\DocumentResource;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * K8 — plans & documents, and reports (docs/BACKEND-API.md §4.6 and §4.9).
 *
 * The two things worth pinning here are the **signed** download (a browser cannot
 * send a bearer token on a plain link, so the signature is the credential), and
 * the fact that a report the domain cannot honestly produce is refused instead of
 * faked.
 */
final class KnxDocumentsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    public function test_the_document_list_has_the_contract_shape_and_no_signed_urls(): void
    {
        $documents = $this->getJson('/api/v1/knx/documents')->assertOk()->json();

        $this->assertCount(6, $documents);
        $this->assertSame(
            ['id', 'name', 'projectCode', 'projectName', 'kind', 'size', 'uploadedAt', 'uploadedBy', 'url', 'revision', 'isCurrent', 'approvedBy'],
            array_keys($documents[0]),
        );

        // A list never carries signed URLs: building N of them to draw a table would
        // be wasteful, and the fixture's own list has `url: null` too.
        $this->assertSame([null], array_values(array_unique(array_column($documents, 'url'))));

        // Human size, rendered from the stored bytes.
        $sizes = collect($documents)->pluck('size', 'name');
        $this->assertSame('4,2 MB', $sizes['UV_C1618_Gelijkvloers_Wayfinding.pdf']);
        $this->assertSame('860 kB', $sizes['C1618_ETS-export_0409.knxproj']);
        $this->assertSame('38 MB', $sizes['Linde_app21_foto’s_oplevering.zip']);

        // The superseded revision is visible as such.
        $superseded = collect($documents)->firstWhere('isCurrent', false);
        $this->assertSame('Rev. B', $superseded['revision']);
        $this->assertNull($superseded['approvedBy']);
    }

    public function test_the_document_list_filters_by_project_and_name(): void
    {
        $this->getJson('/api/v1/knx/documents?project=C1618')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/knx/documents?q=Wayfinding')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/documents?q=niets')->assertOk()->assertJsonCount(0);
    }

    public function test_a_document_without_a_file_gets_no_link_instead_of_a_broken_one(): void
    {
        // The fixture describes documents that live in the Filament backoffice, so
        // their files are not on this disk.
        $document = KnxDocument::query()->where('name', 'like', '%Wayfinding%')->sole();

        $payload = $this->getJson("/api/v1/knx/documents/{$document->getKey()}")->assertOk()->json();

        $this->assertSame('4,2 MB', $payload['size']);
        $this->assertNull($payload['url']);
    }

    public function test_a_document_that_exists_gets_a_signed_download_that_works_without_a_token(): void
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        $document = KnxDocument::factory()->create([
            'project_id' => $project->getKey(),
            'name' => 'plan.pdf',
            'path' => 'knx/documents/plan.pdf',
        ]);
        Storage::disk('local')->put('knx/documents/plan.pdf', 'PDF-ish bytes');

        $url = $this->getJson("/api/v1/knx/documents/{$document->getKey()}")->assertOk()->json('url');

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);

        // No bearer token: the signature is the credential. That is the whole point.
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertOk()->assertHeader('content-disposition', 'attachment; filename=plan.pdf');

        // Tampering with it drops the signature.
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        // And a document whose file is missing answers 404, not a broken download.
        $this->get($this->signedUrlFor($document))->assertOk();
        Storage::disk('local')->delete('knx/documents/plan.pdf');
        $this->get($this->signedUrlFor($document))->assertNotFound();
    }

    private function signedUrlFor(KnxDocument $document): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'api.knx.documents.download',
            now()->addMinutes(5),
            ['id' => $document->getKey()],
        );
    }

    public function test_the_export_history_has_the_contract_shape(): void
    {
        $exports = $this->getJson('/api/v1/knx/reports/exports')->assertOk()->json();

        $this->assertCount(3, $exports);
        $this->assertSame(['id', 'type', 'projectCode', 'projectName', 'createdAt', 'downloadUrl'], array_keys($exports[0]));

        // The fixture's exports have no file behind them, so no link.
        $this->assertNull($exports[0]['downloadUrl']);

        // Most recent first.
        $dates = array_column($exports, 'createdAt');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_generating_an_ets_report_queues_it_and_writes_the_correction_worklist(): void
    {
        // The local queue is `sync`, so the job has run by the time the response is
        // read; on a real worker the row simply stays `queued` longer.
        $response = $this->postJson('/api/v1/knx/reports', [
            'projectCode' => 'C1618',
            'type' => 'ets',
        ])->assertStatus(202);

        $this->assertSame(['id', 'type', 'projectCode', 'projectName', 'createdAt', 'downloadUrl'], array_keys($response->json()));

        $export = KnxExport::query()->whereKey($response->json('id'))->sole();

        $this->assertSame(KnxExport::STATUS_READY, $export->status);
        $this->assertNotNull($export->path);
        Storage::disk('local')->assertExists($export->path);

        $csv = Storage::disk('local')->get($export->path);

        // The worklist: one line per conflict of the project, with the address that
        // is in play and the last workflow step. This is the report the module exists
        // for, and it is real data.
        $this->assertStringContainsString('conflict;severity;type;address;proposal;status;last_step', $csv);
        $this->assertStringContainsString('1.1.116', $csv);
        $this->assertStringContainsString('1.1.133', $csv);
        $this->assertStringContainsString('reported', $csv);
        // Only this project's conflicts.
        $this->assertStringNotContainsString('1.2.021', $csv);

        // And the file can be downloaded through the signed link from the history.
        $url = $this->getJson('/api/v1/knx/reports/exports')->assertOk()->json()[0]['downloadUrl'];

        $this->assertNotNull($url);
        $this->get($url)->assertOk();
    }

    public function test_a_dossier_report_lists_the_registered_apparatuses(): void
    {
        $response = $this->postJson('/api/v1/knx/reports', ['projectCode' => 'C1618', 'type' => 'dossier'])
            ->assertStatus(202);

        // By id, not by type: the fixture already has a seeded dossier export.
        $export = KnxExport::query()->whereKey($response->json('id'))->sole();
        $csv = Storage::disk('local')->get($export->path);

        $this->assertStringContainsString('address;type;room;board;serial;source;registered_by', $csv);
        $this->assertStringContainsString('1.1.100', $csv);
        $this->assertStringContainsString('field', $csv);
    }

    public function test_the_hours_report_is_refused_because_there_is_no_source_for_it(): void
    {
        // §4.9 lists it, and it is the one type this domain cannot produce: nothing
        // tracks time. Refusing loudly beats a file of invented numbers.
        $this->postJson('/api/v1/knx/reports', ['projectCode' => 'C1618', 'type' => 'hours'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['type']]);

        $this->assertSame(0, KnxExport::query()->where('type', 'hours')->count());
    }

    public function test_generating_rejects_an_unknown_project_or_type(): void
    {
        $this->postJson('/api/v1/knx/reports', ['projectCode' => 'NOPE', 'type' => 'ets'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['projectCode']]);

        $this->postJson('/api/v1/knx/reports', ['projectCode' => 'C1618', 'type' => 'onzin'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_the_human_size_helper_matches_the_office_apps_format(): void
    {
        // The fixture's own strings, byte for byte.
        $this->assertSame('4,2 MB', DocumentResource::humanSize((int) round(4.2 * 1024 ** 2)));
        $this->assertSame('38 MB', DocumentResource::humanSize(38 * 1024 ** 2));
        $this->assertSame('860 kB', DocumentResource::humanSize(860 * 1024));
        $this->assertSame('1,5 GB', DocumentResource::humanSize((int) round(1.5 * 1024 ** 3)));
        $this->assertNull(DocumentResource::humanSize(null));
    }

    public function test_the_json_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/documents')->assertUnauthorized();
        $this->getJson('/api/v1/knx/reports/exports')->assertUnauthorized();
        $this->postJson('/api/v1/knx/reports', [])->assertUnauthorized();
    }
}
