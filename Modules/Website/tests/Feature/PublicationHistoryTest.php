<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\MediaSlot;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Services\StaticSitePublicationService;
use Tests\TestCase;

/**
 * WU4 de odd/tasks/publish-to-live-website.md: quién publicó, cuándo, y qué cambió.
 *
 * La **base de comparación** para «qué cambió» es el `activity_log` que ya existía, decidido
 * midiendo y no inventando: tiene `site_id`, `causer_id`, `attribute_changes` y fecha, no se
 * escribe a mano (el modelo es **append-only**), y ya tenía filas de este sitio. Su precio,
 * declarado: hereda el `clean_after_days = 365` del log, o sea que el historial de
 * publicaciones se poda al año como todo lo demás.
 *
 * Se registra la **solicitud** y el **resultado** por separado, y por un motivo concreto: la
 * solicitud corre con sesión (alguien apretó el botón) y el resultado corre en un worker donde
 * no hay usuario, así que **el actor sólo existe en la primera**. La `dispatch_key` va en las
 * propiedades de las dos para poder enlazarlas.
 */
final class PublicationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const LOG = 'static_site_publication';

    private StaticSitePublicationService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'static_site.enabled'          => true,
            'static_site.webhook_url'      => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret'   => 'test-secret',
            'static_site.debounce_seconds' => 20,
        ]);

        $this->svc = app(StaticSitePublicationService::class);
    }

    /** @return \Illuminate\Support\Collection<int, ActivityLogEntry> */
    private function publicationRows(): \Illuminate\Support\Collection
    {
        return ActivityLogEntry::query()->where('log_name', self::LOG)->orderBy('id')->get();
    }

    public function test_publishing_records_who_asked_for_it(): void
    {
        // Sin esto el job corre **inline** (`QUEUE_CONNECTION=sync` en phpunit.xml) y sale a la
        // red: acá sólo se prueba el rastro de la solicitud, no la publicación.
        Queue::fake();

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $this->svc->requestRebuild(Site::claesenId(), 'manual', force: true);

        $row = $this->publicationRows()->sole();

        $this->assertSame('requested', $row->properties['event']);
        $this->assertSame('manual', $row->properties['reason']);
        $this->assertTrue($row->properties['force']);
        // El actor: lo único que sólo existe en este lado del circuito.
        $this->assertSame($user->id, $row->causer_id);
        // Y el sitio, para que la pantalla pueda filtrar por el sitio del panel. `tap` es
        // necesario porque el autocompletado lo lee del **sujeto**, y un `Site` no tiene
        // columna `site_id`.
        $this->assertSame(Site::claesenId(), $row->site_id);
    }

    public function test_an_accepted_publication_records_the_outcome(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('', 202)]);

        $this->svc->requestRebuild(Site::claesenId(), 'manual', force: true);
        $key = PublicationState::current(Site::claesenId())->dispatch_key;

        (new TriggerStaticSiteRebuildJob(Site::claesenId(), $key, 'manual', true))->handle($this->svc);

        $outcome = $this->publicationRows()->last();

        $this->assertSame('accepted', $outcome->properties['event']);
        // La clave es lo que enlaza la solicitud con su resultado: son dos filas, y el worker
        // no tiene usuario que las una.
        $this->assertSame($key, $outcome->properties['dispatch_key']);
    }

    public function test_a_failed_publication_records_the_error(): void
    {
        Queue::fake();

        $this->svc->requestRebuild(Site::claesenId(), 'manual', force: true);
        $key = PublicationState::current(Site::claesenId())->dispatch_key;

        (new TriggerStaticSiteRebuildJob(Site::claesenId(), $key, 'manual', true))
            ->failed(new \RuntimeException('webhook caído'));

        $outcome = $this->publicationRows()->last();

        $this->assertSame('failed', $outcome->properties['event']);
        $this->assertSame($key, $outcome->properties['dispatch_key']);
        $this->assertStringContainsString('webhook caído', (string) $outcome->properties['error']);
    }

    /**
     * La cobertura de «qué cambió» no puede quedarse en los ajustes: proyectos y mediaslots son
     * contenido del sitio, y una lista de cambios que los omite **parece** completa.
     */
    public function test_a_project_change_is_recorded_with_its_site(): void
    {
        Project::factory()->create(['site_id' => Site::claesenId()]);

        $change = ActivityLogEntry::query()
            ->where('subject_type', Project::class)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($change, 'Un cambio de proyecto debe quedar en el log.');
        $this->assertSame(Site::claesenId(), $change->site_id);
    }

    public function test_a_media_slot_change_is_recorded_with_its_site(): void
    {
        Storage::fake('public');
        Queue::fake();

        $project = Project::factory()->create(['site_id' => Site::claesenId()]);

        // El camino real de subida, no una fila armada a mano: la tabla `media` de spatie tiene
        // columnas JSON sin default, y rellenarlas a mano prueba mi lista de columnas, no el
        // rastro del cambio.
        $media = $project->addMedia(UploadedFile::fake()->image('slot.webp', 1200, 800))
            ->toMediaCollection('gallery');

        MediaSlot::query()->create([
            'site_id'  => Site::claesenId(),
            'slot'     => 'home.hero',
            'media_id' => $media->id,
        ]);

        $change = ActivityLogEntry::query()
            ->where('subject_type', MediaSlot::class)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($change, 'Un cambio de mediaslot debe quedar en el log.');
        $this->assertSame(Site::claesenId(), $change->site_id);
    }
}
