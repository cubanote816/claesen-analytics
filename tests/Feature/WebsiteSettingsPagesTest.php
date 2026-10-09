<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\AddressSettingsPage;
use App\Filament\Clusters\Website\Pages\ContactSettingsPage;
use App\Filament\Clusters\Website\Pages\GeneralSettingsPage;
use App\Filament\Clusters\Website\Pages\OpeningHoursSettingsPage;
use App\Filament\Clusters\Website\Pages\PrivacyConsentSettingsPage;
use App\Filament\Clusters\Website\Pages\SocialLinksSettingsPage;
use App\Filament\Clusters\Website\WebsiteNavigationGroups;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\Site;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\SiteSetting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Las páginas de Ajustes, una por sección (WU-S2 del rediseño del backoffice).
 *
 * Estos tests **se movieron**, no se reescribieron: son las mismas aserciones que verificaban la
 * pantalla única —el domicilio como cuatro campos, los horarios con las cuatro claves del
 * contrato, la regla del correo— repartidas por la página que ahora edita cada cosa. Rehacerlos
 * habría tirado la única prueba de que esos comportamientos siguen en pie tras el traslado.
 *
 * Y hay una aserción nueva, que es la razones de la división: guardar una sección **no** toca las
 * claves de otra. Es la mitad del criterio «publicar una página no publica los cambios de otras»:
 * la otra mitad es la sincronización por sección, del lado del sitio.
 */
final class WebsiteSettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }

    private function stored(string $key): mixed
    {
        return SiteSetting::query()->where('site_id', Site::claesenId())->where('key', $key)->value('value');
    }

    private function enablePublishPipeline(): void
    {
        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);

        // El chequeo de drift que la acción hace antes de despachar. Por defecto responde
        // «habría cambios» para que los tests que ya existían sigan probando exactamente lo
        // mismo de siempre: la publicación de hoy. Los tests del chequeo montan el suyo propio.
        Http::fake([
            '*drift*' => Http::response(['checked' => true, 'drift' => true]),
        ]);
    }

    /**
     * El entorno del chequeo: pipeline encendido y un receiver que responde su /drift. Se
     * monta aquí en vez de en enablePublishPipeline() porque la respuesta que hace falta
     * es justamente lo que cada test del chequeo pinza.
     */
    private function enablePublishPipelineWithDrift(array $driftBody, int $status = 200): void
    {
        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);

        Http::fake([
            '*drift*' => Http::response($driftBody, $status),
        ]);
    }

    /**
     * El sitio que el panel Bertels resuelve (`Site::forPanelOrFail()`): una base recién
     * migrada sólo trae la fila de Claesen, así que un GET de `/bertels/...` aborta 404 si no
     * existe. `Livewire::test` no lo necesita porque sin ruta resuelve el panel admin.
     */
    private function serveBertelsPanelSite(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        Site::factory()->create([
            'organization_id' => $organization->id,
            'key'             => 'electro-bertels',
            'status'          => Site::STATUS_ACTIVE,
        ]);
    }

    /** Las seis páginas, con la sección que cada una edita. El mapa manda; acá no se copia. */
    private function settingsPages(): array
    {
        return [
            GeneralSettingsPage::class => WebsiteSettingSections::GENERAL,
            ContactSettingsPage::class => WebsiteSettingSections::CONTACT,
            AddressSettingsPage::class => WebsiteSettingSections::ADDRESS,
            OpeningHoursSettingsPage::class => WebsiteSettingSections::HOURS,
            SocialLinksSettingsPage::class => WebsiteSettingSections::SOCIAL,
            PrivacyConsentSettingsPage::class => WebsiteSettingSections::PRIVACY,
        ];
    }

    // ─── Carga ────────────────────────────────────────────────────────────────

    public function test_each_section_loads_its_own_keys_into_the_form(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 555', 'type' => SiteSetting::TYPE_TEXT]);
        SiteSetting::factory()->create([
            'key'   => 'hours',
            'value' => ['nl' => 'Ma-Vr: 9-17u'],
            'type'  => SiteSetting::TYPE_TRANSLATABLE,
        ]);

        Livewire::test(ContactSettingsPage::class)->assertFormSet(['phone' => '+32 555']);

        Livewire::test(OpeningHoursSettingsPage::class)->assertFormSet([
            'hours' => ['nl' => 'Ma-Vr: 9-17u', 'en' => null, 'fr' => null, 'de' => null],
        ]);
    }

    public function test_mount_leaves_unset_keys_empty_instead_of_erroring(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ContactSettingsPage::class)
            ->assertOk()
            ->assertFormSet(['phone' => null]);
    }

    // ─── Guardado ─────────────────────────────────────────────────────────────

    public function test_saving_a_section_persists_only_its_own_keys(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ContactSettingsPage::class)
            ->fillForm(['phone' => '+32 111', 'email' => 'info@example.test'])
            ->call('save');

        $this->assertSame('+32 111', $this->stored('phone'));
        $this->assertSame('info@example.test', $this->stored('email'));

        // Las claves de otras secciones no aparecen: no hay fila, y menos una vacía.
        $this->assertNull($this->stored('opening_hours'));
        $this->assertNull($this->stored('social_links'));
    }

    public function test_saving_a_section_does_not_touch_the_values_of_another(): void
    {
        $this->actingAsSuperAdmin();

        $hours = [
            ['day_key' => 'tuesdayToFriday', 'hours' => [['open' => '09:00', 'close' => '18:00']]],
        ];
        SiteSetting::factory()->create(['key' => 'opening_hours', 'value' => $hours, 'type' => SiteSetting::TYPE_JSON]);

        Livewire::test(ContactSettingsPage::class)
            ->fillForm(['email' => 'nieuw@electrobertels.be'])
            ->call('save');

        // Los horarios quedan exactamente igual: guardar Contacto no escribe ni marca horarios.
        $this->assertEquals($hours, $this->stored('opening_hours'));
    }

    public function test_saving_again_updates_the_same_row_instead_of_creating_a_duplicate(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 111', 'type' => SiteSetting::TYPE_TEXT]);

        Livewire::test(ContactSettingsPage::class)
            ->fillForm(['phone' => '+32 999'])
            ->call('save');

        $this->assertSame(1, SiteSetting::query()->where('key', 'phone')->count());
        $this->assertSame('+32 999', $this->stored('phone'));
    }

    public function test_translatable_keys_are_trimmed_of_empty_locales(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(OpeningHoursSettingsPage::class)
            ->fillForm(['hours' => ['nl' => 'Ma-Vr: 9-17u', 'en' => '', 'fr' => '', 'de' => '']])
            ->call('save');

        $this->assertEquals(['nl' => 'Ma-Vr: 9-17u'], $this->stored('hours'));
    }

    // ─── Cabecera y barra de cambios sin guardar (WU-S3) ───────────────────────

    /**
     * Criterio de aceptación del slice: **discard restaura el último borrador guardado**.
     *
     * Y sale gratis, porque la base **es** el borrador guardado: descartar es volver a leerla, no
     * hay nada que guardar para poder descartar. Lo que sí hay que probar es que relee lo guardado
     * y **no** el estado inicial de la sesión ni un formulario vacío.
     */
    public function test_discard_restores_the_last_saved_draft(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create([
            'key'   => 'email',
            'type'  => SiteSetting::TYPE_TEXT,
            'value' => 'guardado@electrobertels.be',
        ]);

        Livewire::test(ContactSettingsPage::class)
            ->assertFormSet(['email' => 'guardado@electrobertels.be'])
            ->fillForm(['email' => 'a-medio-cambiar@electrobertels.be'])
            ->assertFormSet(['email' => 'a-medio-cambiar@electrobertels.be'])
            ->call('discard')
            ->assertFormSet(['email' => 'guardado@electrobertels.be']);

        // Y descartar no escribió nada: el borrador guardado sigue siendo el mismo.
        $this->assertSame('guardado@electrobertels.be', $this->stored('email'));
    }

    /**
     * La barra aparece **cuando hay algo que guardar** y desaparece cuando no.
     *
     * Es lo que el prototipo pide al sustituir el botón «Save» del final del formulario: la acción
     * de guardar está cuando hay algo que guardar, no siempre a la vista.
     */
    public function test_the_unsaved_bar_shows_only_when_saving_would_change_something(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create([
            'key'   => 'email',
            'type'  => SiteSetting::TYPE_TEXT,
            'value' => 'guardado@electrobertels.be',
        ]);

        Livewire::test(ContactSettingsPage::class)
            // Recién abierta: el formulario dice lo mismo que la base.
            ->assertDontSee(__('website.settings_bar.unsaved'))
            ->fillForm(['email' => 'otro@electrobertels.be'])
            ->assertSee(__('website.settings_bar.unsaved'))
            ->call('save')
            // Guardado: otra vez no hay nada pendiente.
            ->assertDontSee(__('website.settings_bar.unsaved'));
    }

    /**
     * Y volver al valor guardado **no** cuenta como cambio.
     *
     * Es la diferencia con el badge de la navegación, que cuenta cambios **hechos**: acá la pregunta
     * es si guardar cambiaría algo, así que escribir lo mismo que ya está no enciende la barra.
     */
    public function test_typing_the_saved_value_back_does_not_count_as_a_change(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create([
            'key'   => 'email',
            'type'  => SiteSetting::TYPE_TEXT,
            'value' => 'guardado@electrobertels.be',
        ]);

        Livewire::test(ContactSettingsPage::class)
            ->fillForm(['email' => 'otro@electrobertels.be'])
            ->assertSee(__('website.settings_bar.unsaved'))
            ->fillForm(['email' => 'guardado@electrobertels.be'])
            ->assertDontSee(__('website.settings_bar.unsaved'));
    }

    /** La cabecera: migas de tres partes y la descripción de la sección debajo del título. */
    public function test_the_header_carries_the_three_part_breadcrumb_and_the_description(): void
    {
        $this->actingAsSuperAdmin();

        $page = Livewire::test(ContactSettingsPage::class)->instance();

        $this->assertSame(
            __('website.settings_sections.contact.description'),
            $page->getSubheading(),
        );

        $this->assertSame([
            \App\Filament\Clusters\Website\WebsiteCluster::getUrl()
                => \App\Filament\Clusters\Website\WebsiteCluster::getNavigationLabel(),
            WebsiteNavigationGroups::label(WebsiteNavigationGroups::SITE_SETTINGS),
            __('website.settings_sections.contact.label'),
        ], $page->getBreadcrumbs());
    }

    // ─── Publicación por sección ──────────────────────────────────────────────

    /**
     * Las seis páginas exponen la acción que publica **su** sección — y conservan la de todo
     * el sitio, que el trait de cabecera ya les daba.
     */
    public function test_each_settings_page_exposes_the_section_publish_action(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();

        foreach (array_keys($this->settingsPages()) as $page) {
            Livewire::test($page)
                ->assertOk()
                ->assertActionExists('publish_section')
                ->assertActionExists('publish_now');
        }
    }

    /**
     * La portada HTTP del panel Bertels: `Livewire::test` monta el componente pero no pasa por
     * el layout del panel ni por la cabecera, que es donde vive la acción de publicación. La
     * única cobertura real de esa cabecera es un GET de la página servida.
     *
     * El criterio de la acción sale del mapa (`settingsPages()`), igual que en el resto del
     * archivo: si una página pierde la acción de su sección, se ve acá sin copiar la lista.
     */
    public function test_each_settings_page_serves_200_over_http_with_its_section_publish_action(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();
        $this->serveBertelsPanelSite();

        // La portada HTTP pasa por el layout del panel: sólo queremos la cabecera, no la
        // tubería de assets del navegador.
        $this->withoutVite();

        $label = __('website.publication.actions.publish_section');

        foreach (array_keys($this->settingsPages()) as $page) {
            $this->get($page::getUrl(panel: 'bertels'))
                ->assertSuccessful()
                ->assertSee($label);
        }
    }

    /**
     * El criterio entero del slice: publicar la página que se está mirando pide el rebuild de
     * **exactamente** las claves de esa sección, y ninguna de otra. La expectativa sale del mapa
     * (`WebsiteSettingSections::keys()`), nunca de una segunda copia escrita a mano: si el mapa
     * cambia, este test cambia con él en vez de romperse.
     */
    public function test_publishing_a_section_requests_a_rebuild_with_exactly_that_sections_keys(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();
        Queue::fake();

        foreach ($this->settingsPages() as $page => $section) {
            Livewire::test($page)->callAction('publish_section');

            /** @var TriggerStaticSiteRebuildJob $job */
            $job = Queue::pushed(TriggerStaticSiteRebuildJob::class)->last();

            // Una clave de otra sección, sacada del propio mapa: si viajara, publicar esta
            // página tocaría los valores publicados de otra.
            $foreignSection = collect(WebsiteSettingSections::all())
                ->first(fn (string $other): bool => $other !== $section);
            $foreignKeys = WebsiteSettingSections::keys($foreignSection);

            // El array **completo** de la sección, en el orden del mapa: la página manda la
            // lista entera y es el sitio el que ignora las claves que no consume (`phone`,
            // `address`, `hours` — declarado y esperado).
            $this->assertSame(
                WebsiteSettingSections::keys($section),
                $job->keys,
                "{$page} no pidió exactamente las claves de [{$section}]",
            );

            $this->assertSame(
                [],
                array_intersect($foreignKeys, $job->keys ?? []),
                "{$page} pidió claves de otra sección",
            );

            $this->assertTrue($job->force);
            $this->assertSame('manual', $job->reason);
        }
    }

    /**
     * La publicación va en cola: lo que el panel puede decir es que **se pidió**, no que se
     * publicó — el resultado del webhook lo registra el job en el estado de publicación.
     */
    public function test_a_section_publish_reports_that_the_publication_was_requested(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(
                __('website.publication.actions.publish_section_success', [
                    'page' => ContactSettingsPage::getNavigationLabel(),
                ]),
            );
    }

    /**
     * La confirmación es lo que publica: montar la acción (abrir el modal) **no** manda nada.
     * Sin este test, un clic accidental en el botón de la cabecera publicaría sin preguntar.
     */
    public function test_the_section_publish_does_not_fire_until_the_modal_is_confirmed(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();
        Queue::fake();

        $page = Livewire::test(ContactSettingsPage::class)
            ->mountAction('publish_section')
            ->assertActionMounted('publish_section');

        Queue::assertNothingPushed();

        $page->callMountedAction();

        // Confirmar el modal es lo que encola la publicación — exactamente una.
        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
    }

    /**
     * El modal nombra la página y muestra su estado: los cambios pendientes de **esa** sección,
     * con el número del badge (una sola fuente). Y exige confirmación antes de publicar.
     */
    public function test_the_section_publish_modal_names_the_page_and_shows_its_pending_changes(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();

        // Un cambio hecho y guardado: exactamente lo que el badge de la página cuenta.
        SiteSetting::factory()->create(['key' => 'email', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'a@b.test']);

        $this->assertSame(1, \App\Filament\Clusters\Website\WebsiteNavigationBadges::forSettingSection(WebsiteSettingSections::CONTACT));

        $page = Livewire::test(ContactSettingsPage::class)->mountAction('publish_section');
        $action = $page->instance()->getMountedAction();

        $this->assertTrue($action->isConfirmationRequired());

        $this->assertSame(
            __('website.publication.actions.publish_section_confirm_title', [
                'page' => ContactSettingsPage::getNavigationLabel(),
            ]),
            $action->getModalHeading(),
        );

        $description = (string) $action->getModalDescription();

        $this->assertStringContainsString(
            __('website.publication.actions.publish_section_confirm_body', [
                'page' => ContactSettingsPage::getNavigationLabel(),
            ]),
            $description,
        );
        $this->assertStringContainsString(
            __('website.publication.actions.publish_section_confirm_pending', ['count' => 1]),
            $description,
        );
    }

    /**
     * El pin del defecto: con la publicación desactivada en el entorno, la acción no intenta
     * ningún webhook y no se anuncia como éxito — avisa de que publicar está desactivado.
     * (`requestRebuild()` sostiene esta misma compuerta para la acción de todo el sitio.)
     */
    public function test_publishing_a_section_when_publishing_is_disabled_never_attempts_a_webhook(): void
    {
        $this->actingAsSuperAdmin();

        // El entorno real: pipeline apagado. La URL y el secreto **sí** están configurados a
        // propósito, para que el fallo —si la compuerta faltara— sea un webhook que viaja y no
        // una configuración ausente.
        config([
            'static_site.enabled'        => false,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);

        Queue::fake();
        Http::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_disabled'))
            ->assertNotNotified(
                __('website.publication.actions.publish_section_success', [
                    'page' => ContactSettingsPage::getNavigationLabel(),
                ]),
            );

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /**
     * El punto entero del chequeo: si el sitio responde que publicar las claves de esta
     * página no cambiaría nada del sitio publicado, **no se despacha nada** — hoy le
     * pediríamos un rebuild que no produce nada. Al operador se le dice por qué.
     */
    public function test_publishing_a_section_dispatches_nothing_when_the_site_reports_no_drift(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipelineWithDrift(['checked' => true, 'drift' => false, 'keys' => null]);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_no_drift'))
            ->assertNotNotified(__('website.publication.actions.publish_section_success', [
                'page' => ContactSettingsPage::getNavigationLabel(),
            ]));

        Queue::assertNothingPushed();
    }

    /**
     * Con drift, la publicación es exactamente la de siempre: despacho con las claves de la
     * sección, force, motivo manual, y la misma notificación honesta de «se pidió».
     */
    public function test_publishing_a_section_behaves_exactly_as_today_when_the_site_reports_drift(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipelineWithDrift(['checked' => true, 'drift' => true]);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_success', [
                'page' => ContactSettingsPage::getNavigationLabel(),
            ]))
            ->assertNotNotified(__('website.publication.actions.publish_section_no_drift'));

        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job) =>
                $job->keys === WebsiteSettingSections::keys(WebsiteSettingSections::CONTACT)
                && $job->force
                && $job->reason === 'manual',
        );
    }

    /**
     * La pregunta viaja con las claves de **esta** sección — la expectativa sale del mapa
     * (`WebsiteSettingSections::keys()`), nunca de una segunda lista escrita a mano.
     */
    public function test_the_drift_check_asks_about_exactly_the_sections_own_keys(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipelineWithDrift(['checked' => true, 'drift' => false]);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)->callAction('publish_section');

        $this->assertCount(1, Http::recorded());

        $url = Http::recorded()[0][0]->url();

        $this->assertSame('http://fake-frontend:9000/drift', preg_replace('/\?.*/', '', $url));

        // El cliente percent-encodea la coma (%2C); el receiver decodifica la misma lista.
        $this->assertSame(
            'keys='.implode(',', WebsiteSettingSections::keys(WebsiteSettingSections::CONTACT)),
            urldecode(parse_url($url, PHP_URL_QUERY)),
        );
    }

    /**
     * «No se pudo comprobar» jamás es «no hay nada que publicar»: un 502 del receiver
     * publica igual, avisando de que el chequeo no corrió.
     */
    public function test_a_failed_drift_check_still_publishes_with_a_warning(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipelineWithDrift(['checked' => false, 'drift' => null, 'error' => 'upstream'], 502);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_unverified'));

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
    }

    public function test_a_timed_out_drift_check_still_publishes_with_a_warning(): void
    {
        $this->actingAsSuperAdmin();
        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);
        Http::fake([
            '*drift*' => fn () => throw new ConnectionException('cURL error 28: Connection timed out'),
        ]);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_unverified'));

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
    }

    public function test_an_unreachable_host_still_publishes_with_a_warning(): void
    {
        $this->actingAsSuperAdmin();
        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);
        Http::fake([
            '*drift*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to port 9000'),
        ]);
        Queue::fake();

        Livewire::test(ContactSettingsPage::class)
            ->callAction('publish_section')
            ->assertNotified(__('website.publication.actions.publish_section_unverified'));

        Queue::assertPushed(TriggerStaticSiteRebuildJob::class);
    }

    /**
     * Los cuatro idiomas definen las dos cadenas nuevas del chequeo — y esta vez **en cada
     * archivo**: `trans()` con fallback devolvería el inglés y un hueco en fr/de se vería
     * como una etiqueta en otro idioma delante del operador.
     */
    public function test_every_locale_defines_the_new_drift_strings(): void
    {
        foreach (['nl', 'en', 'fr', 'de'] as $locale) {
            $actions = trans('website.publication.actions', [], $locale);

            $this->assertIsArray($actions, "[{$locale}] perdió el grupo publication.actions");
            $this->assertArrayHasKey('publish_section_no_drift', $actions, "[{$locale}] falta publish_section_no_drift");
            $this->assertArrayHasKey('publish_section_unverified', $actions, "[{$locale}] falta publish_section_unverified");
        }
    }

    // ─── Contact ──────────────────────────────────────────────────────────────

    /**
     * El campo que de verdad se rompió: el valor guardado fue `info@electrobertels.be5`,
     * escrito en un `TextInput` sin regla de formato.
     *
     * `->email()` a secas NO lo rechaza —un dígito es legal en un TLD según la validación RFC—,
     * que es por lo que el primer intento de arreglo parecía que iba a funcionar y no funcionó.
     * La regla que lo caza exige que el TLD sea sólo letras.
     *
     * Sin pretender cazar todo, a propósito: `info@electrobertels.bee` es una dirección
     * sintácticamente válida y ninguna regla de formato la rechaza. Esto cubre la clase de error
     * que ocurrió, no cualquier error de tipeo.
     */
    public function test_the_email_field_rejects_a_value_that_is_not_an_address(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ContactSettingsPage::class)
            ->fillForm(['email' => 'info@electrobertels.be5'])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertNotSame('info@electrobertels.be5', $this->stored('email'));
    }

    // ─── Address ──────────────────────────────────────────────────────────────

    /**
     * El domicilio se edita como cuatro campos, no como JSON crudo en un campo de una línea.
     *
     * De ahí salió `"Balennn"`: teclear dentro de un blob JSON produce JSON que sigue siendo
     * válido, así que nada lo frenaba. Con partes, un error es local y visible en vez de
     * reescribir el objeto entero en silencio.
     *
     * Se compara con las claves ordenadas porque la columna JSON de MySQL reordena los objetos
     * por longitud: asertar el orden acá sería asertar el de la base, no el de la página.
     */
    public function test_the_address_is_stored_as_the_contract_shape_and_drops_other_keys(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(AddressSettingsPage::class)
            ->fillForm([
                'address_structured' => [
                    'street'        => 'Benoit Jansenstraat 4',
                    'postal_code'   => '2490',
                    'city'          => 'Balen',
                    'country_code'  => 'BE',
                    // No es parte del contrato: no debe llegar a la columna, o viajaría intacta
                    // al snapshot del sitio.
                    'stray'         => 'x',
                ],
            ])
            ->call('save');

        $stored = $this->stored('address_structured');
        ksort($stored);

        $this->assertSame([
            'city'         => 'Balen',
            'country_code' => 'BE',
            'postal_code'  => '2490',
            'street'       => 'Benoit Jansenstraat 4',
        ], $stored);
    }

    // ─── Opening hours ────────────────────────────────────────────────────────

    /**
     * `opening_hours` se edita como una fila por día, no como JSON crudo.
     *
     * El contrato es una lista de cuatro claves de día en la que `hours === null` ES «cerrado», y
     * el validador del sync sólo acepta esas cuatro — una lista de siete fue lo que rechazó el
     * primer seeder.
     */
    public function test_opening_hours_round_trips_the_contract_shape(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(OpeningHoursSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'sunday', 'closed' => true],
                    ['day_key' => 'monday', 'closed' => true],
                    ['day_key' => 'tuesdayToFriday', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                        ['open' => '13:00', 'close' => '18:00'],
                    ]],
                    ['day_key' => 'saturday', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save');

        $this->assertEquals([
            ['day_key' => 'sunday', 'hours' => null],
            ['day_key' => 'monday', 'hours' => null],
            ['day_key' => 'tuesdayToFriday', 'hours' => [
                ['open' => '09:00', 'close' => '12:00'],
                ['open' => '13:00', 'close' => '18:00'],
            ]],
            ['day_key' => 'saturday', 'hours' => [
                ['open' => '09:00', 'close' => '12:00'],
            ]],
        ], $this->stored('opening_hours'));
    }

    /**
     * La lección por la que este test existe. El intento anterior culpó al mapper de una columna
     * que salía nula, y la causa era el propio test: metía un `day_key` inválido
     * (`everyFullMoon`) en el formulario, el `Select` lo rechazaba y la validación abortaba todo
     * el guardado — que es lo correcto. El guardado nunca corría.
     *
     * Así que asevera el resultado observable (no se escribe nada) y dice en voz alta que es el
     * `Select` lo que lo frena, en vez de pretender estar probando el mapper por dentro.
     */
    public function test_opening_hours_never_persists_a_day_outside_the_contract(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(OpeningHoursSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'everyFullMoon', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertNull($this->stored('opening_hours'));
    }

    /**
     * `hours: null` ES «cerrado» en el contrato, así que decide el `Toggle` y no los rangos que
     * hayan quedado en el estado. Sin esto, cerrar un día podría seguir sirviendo su horario.
     */
    public function test_opening_hours_stores_a_closed_day_as_null_hours(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(OpeningHoursSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'sunday', 'closed' => true, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save');

        $this->assertEquals(
            [['day_key' => 'sunday', 'hours' => null]],
            $this->stored('opening_hours'),
        );
    }

    /**
     * El mismo día dos veces no se puede guardar. Sin el `distinct()` del `Select` el guardado
     * pasaría y el snapshot llevaría dos domingos, que no es un estado que el contrato describa:
     * su lista de días es un conjunto.
     */
    public function test_opening_hours_rejects_the_same_day_twice(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(OpeningHoursSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'monday', 'closed' => false, 'hours' => [['open' => '09:00', 'close' => '12:00']]],
                    ['day_key' => 'monday', 'closed' => false, 'hours' => [['open' => '13:00', 'close' => '18:00']]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['opening_hours.1.day_key']);

        $this->assertNull($this->stored('opening_hours'));
    }

    /**
     * Un día cerrado llega igual con un período en blanco, para que abrir el `Toggle` deje una
     * fila lista en vez de un hueco detrás de un botón «añadir».
     *
     * `defaultItems` no puede cubrirlo: cuando el día se abre, el repeater ya tiene estado. Se
     * verifica sobre el estado del formulario montado y no haciendo clic, porque el `Toggle` es
     * el `visible()` de Filament reaccionando a `live()`, y eso es justamente lo que este estado
     * alimenta.
     */
    public function test_opening_hours_offers_a_blank_period_for_a_closed_day(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create([
            'key'   => 'opening_hours',
            'type'  => SiteSetting::TYPE_JSON,
            'value' => [['day_key' => 'sunday', 'hours' => null]],
        ]);

        $rows = array_values(Livewire::test(OpeningHoursSettingsPage::class)->get('data.opening_hours'));

        // `array_values()` en los dos niveles a propósito: Filament indexa el estado de un
        // `Repeater` por uuid de fila, no por índice. Asertar `[0 => …]` falla contra una clave
        // uuid y **parece** un bug de mapeo, que es la conclusión equivocada.
        $this->assertCount(1, $rows);
        $this->assertSame('sunday', $rows[0]['day_key']);
        $this->assertTrue($rows[0]['closed']);
        $this->assertSame(
            [['open' => null, 'close' => null]],
            array_values($rows[0]['hours']),
        );
    }

    // ─── Social links ─────────────────────────────────────────────────────────

    public function test_social_links_are_stored_as_a_list(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SocialLinksSettingsPage::class)
            ->fillForm([
                'social_links' => [['platform' => 'facebook', 'url' => 'https://fb.example']],
            ])
            ->call('save');

        $this->assertEquals(
            [['platform' => 'facebook', 'url' => 'https://fb.example']],
            $this->stored('social_links'),
        );
    }
}
