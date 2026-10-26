<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\WebsiteSettingSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;
use Tests\TestCase;

/**
 * La cobertura de las publicaciones aceptadas (publicación por sección).
 *
 * El badge de una sección cuenta desde «la última publicación que cubrió sus claves», y esa
 * cobertura tiene que quedar **registrada** en el evento `accepted`, no inferida. Acá se fija
 * que el evento aceptado lleve las claves que viajaron en la publicación y la marca de agua
 * que esa publicación cubrió — es lo que después lee `WebsiteNavigationBadges`.
 */
final class StaticSitePublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'static_site.enabled'          => true,
            'static_site.webhook_url'      => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret'   => 'test-secret',
            'static_site.environment'      => 'testing',
            'static_site.debounce_seconds' => 20,
        ]);
    }

    public function test_the_accepted_event_records_the_keys_and_the_watermark_it_covered(): void
    {
        // Un cambio registrado: sin actividad previa la marca de agua sería null y el corte
        // quedaría sin comprobar.
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        Http::fake(['*' => Http::response('', 202)]);

        $state = PublicationState::current(Site::claesenId());
        $state->markPending();
        $state->recordDispatch('covered');

        // La marca de agua que el job captura es el id más alto del log justo antes del webhook;
        // entre acá y el handle no se registra actividad, así que es la misma.
        $watermark = ActivityLogEntry::query()->max('id');

        $keys = WebsiteSettingSections::keys(WebsiteSettingSections::CONTACT);
        (new TriggerStaticSiteRebuildJob(Site::claesenId(), 'covered', 'manual', true, $keys))
            ->handle(app(StaticSitePublicationService::class));

        $accepted = ActivityLogEntry::query()
            ->where('log_name', StaticSitePublicationService::PUBLICATION_LOG)
            ->where('properties->event', 'accepted')
            ->sole();

        $this->assertNotNull($watermark, 'Hay actividad registrada: la marca de agua es un id, no null.');
        $this->assertSame($keys, $accepted->properties['keys'], 'Las claves publicadas quedan en el evento: la cobertura es auditable.');
        $this->assertSame($watermark, $accepted->properties['watermark'], 'La marca de agua que cubrió la publicación queda al lado de sus claves.');
    }

    /** Una publicación de todo el sitio (sin claves) también deja su cobertura registrada. */
    public function test_the_accepted_event_of_a_full_site_publish_records_null_keys(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        Http::fake(['*' => Http::response('', 202)]);

        $state = PublicationState::current(Site::claesenId());
        $state->markPending();
        $state->recordDispatch('fullsite');

        (new TriggerStaticSiteRebuildJob(Site::claesenId(), 'fullsite', 'manual', true))
            ->handle(app(StaticSitePublicationService::class));

        $accepted = ActivityLogEntry::query()
            ->where('log_name', StaticSitePublicationService::PUBLICATION_LOG)
            ->where('properties->event', 'accepted')
            ->sole();

        $this->assertNull($accepted->properties['keys'], 'Sin claves la publicación fue de todo el sitio, y el evento lo dice.');
        $this->assertNotNull($accepted->properties['watermark'], 'Y su marca de agua también queda registrada.');
    }
}
