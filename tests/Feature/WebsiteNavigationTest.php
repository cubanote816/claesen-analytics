<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\ContactSettingsPage;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteNavigationBadges;
use App\Filament\Clusters\Website\WebsiteNavigationGroups;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Intelligence\Models\TranslationState;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\PagePublication;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;
use Tests\TestCase;

/**
 * La navegación de Website (WU-S1 del rediseño del backoffice).
 *
 * Dos cosas distintas se prueban acá: que **todo** ítem del clúster caiga en uno de los cuatro
 * grupos —un ítem sin grupo aparece suelto arriba del submenú, fuera de la estructura que el brief
 * pide, y nada falla— y de dónde sale **cada número** del badge.
 *
 * El corte de los badges es la marca de agua del WU4: cambios registrados **después** de la última
 * publicación. Cuenta cambios **hechos**, no diferencias que quedan (editar y revertir sigue
 * contando 1 hasta publicar), y eso es lo que el brief pide al hablar de «cambios»: la versión por
 * diferencia es la de la publicación por sección (S4), y cambia en un solo lugar — el servicio.
 */
final class WebsiteNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Publica como lo hace el job: petición pendiente, webhook aceptado, estado aceptado y
     * rastro del resultado. `$keys` null = publicación de todo el sitio; con claves, sólo esas
     * viajan — la misma ruta que la acción «Publica esta página» de cada sección de Ajustes.
     *
     * @param list<string>|null $keys
     */
    private function publish(?array $keys = null): void
    {
        Http::fake(['*' => Http::response('', 202)]);

        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
            'static_site.environment'    => 'testing',
        ]);

        $state = PublicationState::current(Site::claesenId());
        $state->markPending();
        $state->recordDispatch(uniqid('publish-', true));

        (new TriggerStaticSiteRebuildJob(Site::claesenId(), $state->dispatch_key, 'manual', true, $keys))
            ->handle(app(StaticSitePublicationService::class));
    }

    /**
     * El submenú cuelga del ítem Website **dentro del sidebar**, con sus cuatro grupos y sus
     * ítems, en el orden del prototipo.
     *
     * Es lo que Filament no hace solo: corta el registro de las páginas de un clúster a propósito
     * (`Page::registerNavigationItems()` sale temprano), así que sus ítems no llegan al sidebar y
     * el submenú terminaba renderizándose al costado del contenido. Acá se comprueba la forma
     * anidada que sí es alcanzable: ítem del clúster -> grupos -> ítems.
     */
    public function test_the_cluster_item_carries_the_groups_and_their_items(): void
    {
        $items = \App\Filament\Clusters\Website\WebsiteCluster::getNavigationItems();

        $this->assertCount(1, $items, 'El clúster publica un ítem: el suyo.');

        $clusterItem = $items[0];
        $this->assertSame(__('navigation.groups.content_website'), $clusterItem->getGroup());

        $children = $clusterItem->getChildItems();

        $this->assertSame(
            array_map(fn (string $g): string => WebsiteNavigationGroups::label($g), WebsiteNavigationGroups::all()),
            array_map(fn ($item): string => (string) $item->getLabel(), $children),
            'Los cuatro grupos, en el orden del prototipo (Publicación primero, Ajustes tercero).',
        );

        $settingsGroup = collect($children)
            ->first(fn ($item): bool => $item->getLabel() === WebsiteNavigationGroups::label(WebsiteNavigationGroups::SITE_SETTINGS));

        $this->assertCount(
            count(WebsiteSettingSections::all()),
            $settingsGroup->getChildItems(),
            'Cada una de las seis secciones de Ajustes cuelga de su grupo, no suelta.',
        );
    }

    /**
     * Y el badge viaja con el ítem: el número que se ve en el sidebar es el mismo que se cuenta.
     */
    public function test_the_nested_items_keep_their_badges(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        $items = \App\Filament\Clusters\Website\WebsiteCluster::getNavigationItems();

        $contact = collect($items[0]->getChildItems())
            ->flatMap(fn ($group) => $group->getChildItems())
            ->first(fn ($item): bool => $item->getLabel() === __('website.settings_sections.contact.label'));

        $this->assertNotNull($contact, 'El ítem de Contact tiene que estar colgado del submenú.');
        $this->assertSame('1', $contact->getBadge());
    }

    /**
     * El override de la vista del sidebar es **de carga**, y por eso tiene una alarma.
     *
     * Filament no pasa `child-items` a la recursión, así que sin esa línea el sidebar no baja el
     * segundo nivel: el menú de tres niveles («Website › grupo › página») vuelve a mostrar sólo los
     * cuatro grupos —sin sus páginas— y las páginas quedan **inalcanzables desde el menú**. No es un
     * test de comportamiento (eso lo cubre el navegador): es para que borrar el archivo, o
     * actualizar Filament, no se lleve el menú en silencio.
     */
    public function test_the_sidebar_view_override_still_passes_the_children_down(): void
    {
        $path = resource_path('views/vendor/filament-panels/components/sidebar/item.blade.php');

        $this->assertFileExists(
            $path,
            'El override del sidebar desapareció: el menú perdería el segundo nivel de anidado.',
        );

        $this->assertStringContainsString(
            ':child-items="$childItem->getChildItems()"',
            (string) file_get_contents($path),
            'El override del sidebar ya no pasa los hijos a la recursión: las páginas quedarían inalcanzables.',
        );
    }

    // ─── La estructura ────────────────────────────────────────────────────────

    public function test_every_cluster_component_belongs_to_one_of_the_four_groups(): void
    {
        $labels = array_map(
            fn (string $group): string => WebsiteNavigationGroups::label($group),
            WebsiteNavigationGroups::all(),
        );

        $components = WebsiteCluster::getClusteredComponents();

        $this->assertNotEmpty($components, 'El clúster debería tener componentes registrados.');

        foreach ($components as $component) {
            $this->assertContains(
                $component::getNavigationGroup(),
                $labels,
                "{$component} no cae en ninguno de los cuatro grupos: aparecería suelto en el submenú.",
            );
        }
    }

    public function test_every_group_is_labelled_in_every_locale(): void
    {
        foreach (['nl', 'en', 'fr', 'de'] as $locale) {
            foreach (WebsiteNavigationGroups::all() as $group) {
                $key = "website.nav_groups.{$group}";

                $this->assertNotSame(
                    $key,
                    trans($key, [], $locale),
                    "Falta `{$key}` en `{$locale}`: el submenú mostraría la clave cruda.",
                );
            }
        }
    }

    /**
     * Las seis páginas de ajustes comparten grupo y se ordenan como el mapa del prototipo — y la
     * primera no queda última por un cero que se confunde con falso.
     */
    public function test_the_settings_pages_share_the_group_and_keep_the_order_of_the_map(): void
    {
        $this->assertSame(
            WebsiteNavigationGroups::label(WebsiteNavigationGroups::SITE_SETTINGS),
            ContactSettingsPage::getNavigationGroup(),
        );

        $this->assertSame(
            array_search(WebsiteSettingSections::CONTACT, WebsiteSettingSections::all(), true) + 1,
            ContactSettingsPage::getNavigationSort(),
        );
    }

    // ─── Los números ──────────────────────────────────────────────────────────

    public function test_a_settings_badge_counts_changes_since_the_last_publication(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        $this->assertSame(1, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));
        // Otra sección no se contamina: el número es de la página.
        $this->assertSame(0, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::ADDRESS));

        // Publicar corre la marca de agua al id más alto…
        PublicationState::current(Site::claesenId())->markAccepted(ActivityLogEntry::query()->max('id'));
        $this->assertSame(0, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));

        // …y un cambio nuevo vuelve a contar.
        SiteSetting::factory()->create(['key' => 'phone_display', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'x']);
        $this->assertSame(1, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));
    }

    /** Y la página lo publica en la navegación: sin conteo, `null` en vez de `'0'`. */
    public function test_the_page_shows_its_count_and_hides_a_zero(): void
    {
        $this->assertNull(ContactSettingsPage::getNavigationBadge());

        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        $this->assertSame('1', ContactSettingsPage::getNavigationBadge());
    }

    /**
     * El caso que justifica el corte por sección: publicar Contact saca las claves de Contact
     * y **sólo** las de Contact — su badge baja a 0 y el de las demás secciones se queda
     * exactamente como estaba. Hoy la publicación por sección mueve la marca de agua de todo
     * el sitio, y ese movimiento borra también el badge de las secciones que no viajaron.
     */
    public function test_publishing_one_section_clears_its_badge_and_leaves_the_others_untouched(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);
        SiteSetting::factory()->create(['key' => 'address', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'Calle 1']);

        $this->assertSame(1, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));
        $this->assertSame(1, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::ADDRESS));

        $this->publish(WebsiteSettingSections::keys(WebsiteSettingSections::CONTACT));

        $this->assertSame(
            0,
            WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT),
            'La sección publicada ya no tiene cambios pendientes.',
        );
        $this->assertSame(
            1,
            WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::ADDRESS),
            'La publicación de Contact no tocó Address: su badge no puede borrarse.',
        );
    }

    /** Una publicación sin claves es de todo el sitio: borra el badge de todas las secciones. */
    public function test_a_full_site_publication_clears_every_section_s_badge(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);
        SiteSetting::factory()->create(['key' => 'address', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'Calle 1']);
        SiteSetting::factory()->create(['key' => 'social_links', 'type' => SiteSetting::TYPE_TEXT, 'value' => '[]']);

        $this->publish();

        foreach ([WebsiteSettingSections::CONTACT, WebsiteSettingSections::ADDRESS, WebsiteSettingSections::SOCIAL] as $section) {
            $this->assertSame(
                0,
                WebsiteNavigationBadges::forSettingSection($section),
                "La publicación de todo el sitio cubrió {$section}: su badge tiene que bajar a 0.",
            );
        }
    }

    /**
     * Sin historial por sección — filas anteriores a la publicación por sección, que no dejan
     * claves ni marca de agua propia en el evento — el corte sigue siendo el de todo el sitio:
     * exactamente el comportamiento de antes.
     */
    public function test_without_per_section_history_the_badge_keeps_the_site_wide_cut_off(): void
    {
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        // Un accepted «viejo»: sin claves, sin marca de agua propia en el evento.
        PublicationState::current(Site::claesenId())->markAccepted(ActivityLogEntry::query()->max('id'));
        app(StaticSitePublicationService::class)->recordPublicationEvent('accepted', Site::claesenId(), [
            'dispatch_key' => 'legacy',
            'reason'       => 'manual',
        ]);

        $this->assertSame(0, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));

        // Y un cambio nuevo vuelve a contar desde el corte de todo el sitio.
        SiteSetting::factory()->create(['key' => 'phone_display', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'x']);
        $this->assertSame(1, WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));
    }

    public function test_a_content_badge_counts_that_content_s_changes(): void
    {
        Project::factory()->create(['site_id' => Site::claesenId()]);

        $this->assertSame(1, WebsiteNavigationBadges::forSubjectType(Project::class));
        // Y lo que no es ese modelo no cuenta.
        $this->assertSame(0, WebsiteNavigationBadges::forSubjectType(SiteSetting::class));
    }

    public function test_the_inbox_badge_counts_new_requests_only(): void
    {
        ConsultationRequest::factory()->create(['site_id' => Site::claesenId(), 'status' => ConsultationRequest::STATUS_NEW]);
        ConsultationRequest::factory()->create(['site_id' => Site::claesenId(), 'status' => ConsultationRequest::STATUS_CLOSED]);

        $this->assertSame(1, WebsiteNavigationBadges::forNewConsultations());
    }

    public function test_the_pages_badge_counts_approved_pages_waiting_to_go_live(): void
    {
        PagePublication::query()->create([
            'site_id' => Site::claesenId(),
            'page'    => 'home',
            'locale'  => 'nl',
            'status'  => PagePublication::STATUS_REVIEWED,
        ]);
        PagePublication::query()->create([
            'site_id' => Site::claesenId(),
            'page'    => 'contact',
            'locale'  => 'nl',
            'status'  => PagePublication::STATUS_MACHINE,
        ]);

        $this->assertSame(1, WebsiteNavigationBadges::forPagesWaitingToGoLive());
    }

    /**
     * El badge de traducciones cuenta lo que **falta hacer**: revisadas y publicadas no cuentan,
     * porque ya no son trabajo pendiente.
     */
    public function test_the_translations_badge_excludes_reviewed_and_published(): void
    {
        $project = Project::factory()->create(['site_id' => Site::claesenId()]);

        // El proyecto llega con estados de traducción que `HasAiTranslations` crea al guardarlo, y
        // uno de ellos es `title`/`fr` — la misma tupla que este test inserta. Se limpian para que
        // el conteo sea el de las filas de acá y no dependa de cuántos idiomas tenga el modelo.
        TranslationState::query()->delete();

        // Una fila por (sitio, sujeto, atributo, idioma): la tabla tiene unique por esa tupla.
        $rows = [
            ['attribute' => 'title', 'locale' => 'fr', 'status' => TranslationState::STATUS_NEEDS_REVIEW],
            ['attribute' => 'title', 'locale' => 'en', 'status' => TranslationState::STATUS_MACHINE],
            ['attribute' => 'description', 'locale' => 'fr', 'status' => TranslationState::STATUS_REVIEWED],
            ['attribute' => 'client', 'locale' => 'fr', 'status' => TranslationState::STATUS_PUBLISHED],
        ];

        foreach ($rows as $row) {
            TranslationState::query()->create(array_merge([
                'site_id'           => Site::claesenId(),
                'translatable_type' => Project::class,
                'translatable_id'   => $project->id,
            ], $row));
        }

        // Esperan una persona las dos primeras; revisada y publicada ya no son trabajo pendiente.
        $this->assertSame(2, WebsiteNavigationBadges::forTranslationsWaiting());
    }
}
