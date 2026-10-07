<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\AddressSettingsPage;
use App\Filament\Clusters\Website\Pages\ContactSettingsPage;
use App\Filament\Clusters\Website\Pages\OpeningHoursSettingsPage;
use App\Filament\Clusters\Website\Pages\SocialLinksSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
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
