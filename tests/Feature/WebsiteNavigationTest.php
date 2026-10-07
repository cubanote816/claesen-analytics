<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\ContactSettingsPage;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteNavigationBadges;
use App\Filament\Clusters\Website\WebsiteNavigationGroups;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Intelligence\Models\TranslationState;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\PagePublication;
use Modules\Website\Models\Project;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
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
