<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxProject;
use RuntimeException;
use Tests\TestCase;

/**
 * The office's plan upload (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * This is the route that makes the plan viewer stop showing an empty state: until
 * now nothing in the domain ever received a file. The three things worth pinning:
 *
 *   - the server derives size, MIME and page count **from the bytes**, because it is
 *     the first writer that has them;
 *   - `supersedes` is explicit and happens in one transaction, so a failure in the
 *     middle can never leave two current revisions or none;
 *   - a repeated `clientId` returns the same document, because a 3 MB retry after a
 *     network cut must not create a duplicate revision.
 */
final class KnxDocumentsUploadTest extends TestCase
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

    /** A real, minimal PDF with `$pages` pages, padded up to `$padBytes` after EOF. */
    private function minimalPdf(int $pages, int $padBytes = 0): string
    {
        // 1 catalog, 2 pages tree, 3 font; page/content pairs follow.
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $kids = [];
        $next = 4;

        for ($page = 1; $page <= $pages; $page++) {
            $pageObject = $next++;
            $contentObject = $next++;
            $stream = sprintf('BT /F1 18 Tf 72 760 Td (Page %d) Tj ET', $page);

            $kids[] = $pageObject.' 0 R';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents '
                .$contentObject.' 0 R /Resources << /Font << /F1 3 0 R >> >> >>';
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";

        for ($number = 1; $number <= $max; $number++) {
            $pdf .= isset($offsets[$number])
                ? sprintf("%010d 00000 n \n", $offsets[$number])
                : "0000000000 65535 f \n";
        }

        $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $padBytes > 0 ? $pdf.str_repeat(' ', $padBytes) : $pdf;
    }

    private function upload(string $code = 'C1618', array $overrides = [], ?string $content = null)
    {
        $payload = array_merge([
            'file' => UploadedFile::fake()->createWithContent('plan.pdf', $content ?? $this->minimalPdf(3)),
            'project' => $code,
            'kind' => 'Plan',
            'revision' => 'Rev. H',
        ], $overrides);

        return $this->postJson('/api/v1/knx/documents', $payload);
    }

    public function test_an_office_session_uploads_a_pdf_and_the_server_derives_its_metadata(): void
    {
        $content = $this->minimalPdf(3, 2 * 1024 * 1024);

        $response = $this->upload('C1618', ['revision' => 'Rev. H'], $content)->assertCreated();

        // The response is the read shape the office already parses, and it comes with
        // its signed URL so the viewer needs no second parser.
        $this->assertSame(
            ['id', 'name', 'projectCode', 'projectName', 'kind', 'mimeType', 'size', 'pages', 'uploadedAt', 'uploadedBy', 'url', 'revision', 'isCurrent', 'approvedBy'],
            array_keys($response->json()),
        );

        $this->assertSame('plan.pdf', $response->json('name'));
        $this->assertSame('application/pdf', $response->json('mimeType'));
        $this->assertSame('Rev. H', $response->json('revision'));
        $this->assertTrue($response->json('isCurrent'));
        $this->assertSame(3, $response->json('pages'));
        // 2 MB of padding, so the human text is the real size of the file.
        $this->assertSame('2 MB', $response->json('size'));
        $this->assertStringContainsString('signature=', (string) $response->json('url'));

        $document = KnxDocument::query()->whereKey($response->json('id'))->sole();

        // The bytes are on the disk under a path derived from the project and the id,
        // which is what the download route already uses.
        $this->assertNotNull($document->path);
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('knx/plans/C1618/', $document->path);
        $this->assertSame(strlen($content), $document->size_bytes);
    }

    public function test_the_uploaded_document_is_listed_with_its_mime_type_pages_and_size(): void
    {
        $this->upload('C1618', ['revision' => 'Rev. Z'])->assertCreated();

        $listed = collect($this->getJson('/api/v1/knx/documents?project=C1618')->assertOk()->json())
            ->firstWhere('revision', 'Rev. Z');

        $this->assertNotNull($listed);
        $this->assertSame('application/pdf', $listed['mimeType']);
        $this->assertSame(3, $listed['pages']);
        $this->assertNotNull($listed['size']);
        // The list still carries no signed URL, exactly as before.
        $this->assertNull($listed['url']);
    }

    public function test_uploading_a_new_revision_supersedes_the_previous_one_in_the_same_transaction(): void
    {
        $previous = KnxDocument::query()
            ->where('name', 'UV_C1618_Gelijkvloers_Wayfinding.pdf')
            ->sole();

        $this->assertTrue($previous->is_current);

        $new = $this->upload('C1618', [
            'revision' => 'Rev. D',
            'supersedes' => $previous->getKey(),
        ])->assertCreated();

        $this->assertTrue($new->json('isCurrent'));
        $this->assertFalse($previous->fresh()->is_current);

        // Exactly one current revision of that series.
        $this->assertSame(
            1,
            KnxDocument::query()->whereKey($new->json('id'))->where('is_current', true)->count(),
        );
    }

    public function test_a_failure_in_the_middle_leaves_neither_revision_wrong(): void
    {
        $previous = KnxDocument::query()
            ->where('name', 'UV_C1618_Gelijkvloers_Wayfinding.pdf')
            ->sole();

        // Force the transaction to die after the row insert, inside the write. The
        // old revision must stay current and no half-written document may survive.
        $listener = static function (): void {
            throw new RuntimeException('storage exploded');
        };

        Event::listen('eloquent.created: '.KnxDocument::class, $listener);
        $this->withoutExceptionHandling();

        try {
            $this->upload('C1618', [
                'revision' => 'Rev. D',
                'supersedes' => $previous->getKey(),
            ]);

            $this->fail('The upload should have failed inside its transaction.');
        } catch (RuntimeException) {
            // expected
        } finally {
            Event::forget('eloquent.created: '.KnxDocument::class);
        }

        $this->assertTrue($previous->fresh()->is_current);
        $this->assertSame(0, KnxDocument::query()->where('revision', 'Rev. D')->count());
    }

    public function test_the_same_client_id_returns_the_same_document_and_does_not_duplicate(): void
    {
        $content = $this->minimalPdf(1);

        $first = $this->upload('C1618', ['clientId' => 'upload-abc'])->assertCreated();
        $second = $this->upload('C1618', ['clientId' => 'upload-abc'])->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, KnxDocument::query()->where('client_id', 'upload-abc')->count());
    }

    public function test_a_client_id_reused_in_another_project_is_rejected(): void
    {
        $this->upload('C1618', ['clientId' => 'upload-xyz'])->assertCreated();

        $this->upload('239870', ['clientId' => 'upload-xyz'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['clientId']]);
    }

    public function test_an_unknown_project_or_supersedes_is_a_404(): void
    {
        $this->upload('NOPE')->assertNotFound();

        $this->upload('C1618', ['supersedes' => 999999])->assertNotFound();

        // A supersedes id that exists but belongs to another project must not be
        // silently linked either: the relation is stored, so it is scoped tightly.
        $foreign = KnxDocument::query()
            ->whereHas('project', fn ($query) => $query->where('code', '!=', 'C1618'))
            ->firstOrFail();

        $this->upload('C1618', ['supersedes' => $foreign->getKey()])->assertNotFound();
    }

    public function test_a_file_over_the_limit_is_a_413_and_not_a_500(): void
    {
        // 60 MB: the request is fine, the body is too large.
        $response = $this->postJson('/api/v1/knx/documents', [
            'file' => UploadedFile::fake()->create('big.zip', 60000),
            'project' => 'C1618',
            'kind' => 'Foto’s',
        ])->assertStatus(413);

        $response->assertJsonPath('code', 'payload_too_large');
        $this->assertSame(0, KnxDocument::query()->where('name', 'big.zip')->count());
    }

    public function test_a_storage_failure_rolls_the_document_back_instead_of_committing_an_empty_path(): void
    {
        $previous = KnxDocument::query()
            ->where('name', 'UV_C1618_Gelijkvloers_Wayfinding.pdf')
            ->sole();
        $before = KnxDocument::query()->count();

        // putFileAs answers false instead of throwing when the disk cannot write. The
        // write must fail loudly and roll back, never commit a row with an empty path.
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('local', $disk);

        $this->withoutExceptionHandling();

        try {
            $this->upload('C1618', ['supersedes' => $previous->getKey()]);
            $this->fail('The storage failure should have rolled the document back.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($before, KnxDocument::query()->count());
        $this->assertTrue($previous->fresh()->is_current);
    }

    public function test_an_empty_client_id_is_no_key_instead_of_a_500_on_retry(): void
    {
        // "" is what a form field sends when it is empty; it must mean "no key", not a
        // key of the empty string. Otherwise the retry bypasses replay and the unique
        // index turns it into a 500.
        $first = $this->upload('C1618', ['clientId' => ''])->assertCreated();
        $second = $this->upload('C1618', ['clientId' => ''])->assertCreated();

        $this->assertNotSame($first->json('id'), $second->json('id'));
        $this->assertNull(KnxDocument::query()->whereKey($first->json('id'))->sole()->client_id);
        $this->assertSame(0, KnxDocument::query()->where('client_id', '')->count());
    }

    public function test_a_client_id_used_by_another_organization_does_not_block_this_one(): void
    {
        $other = Organization::factory()->create(['slug' => 'other-org']);
        $foreignProject = KnxProject::factory()->create(['organization_id' => $other->id]);

        KnxDocument::factory()->create([
            'organization_id' => $other->id,
            'project_id' => $foreignProject->getKey(),
            'client_id' => 'shared-upload-key',
        ]);

        // The key is unique per organization: this organization's upload is a fresh
        // document, not a cross-tenant replay and not a unique-constraint 500.
        $this->upload('C1618', ['clientId' => 'shared-upload-key'])->assertCreated();
    }

    public function test_the_missing_required_fields_are_field_errors(): void
    {
        $this->postJson('/api/v1/knx/documents', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['file', 'project', 'kind']]);
    }

    public function test_the_upload_requires_the_office_app(): void
    {
        $this->app['auth']->forgetGuards();
        $this->upload()->assertUnauthorized();

        // A field account cannot upload plans: this is office work.
        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );
        $this->upload()->assertUnauthorized();
    }
}
