<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\WebsiteSettingSections;
use Modules\Website\Models\SiteSetting;
use Tests\TestCase;

/**
 * El reparto de claves de Ajustes (WU-S2 del rediseño del backoffice).
 *
 * Vive separado de los tests de las páginas porque son invariantes del **mapa**, no de una
 * pantalla: si el mapa se rompe, las seis páginas siguen funcionando y una clave desaparece de
 * la UI sin que nada falle. Eso es exactamente lo que estas aserciones convierten en un fallo.
 */
final class WebsiteSettingSectionsMappingTest extends TestCase
{
    /**
     * Ninguna clave del whitelist puede quedar fuera del reparto: una clave huérfana es una
     * clave que **nadie puede editar** desde el panel, y se descubre tarde —cuando alguien la
     * busca y no está—. Es la guarda que hace visible el olvido de agregar la clave nueva a una
     * sección.
     */
    public function test_no_whitelisted_key_is_left_without_a_section(): void
    {
        $this->assertSame(
            [],
            WebsiteSettingSections::orphanedKeys(),
            'Toda clave del whitelist tiene que pertenecer a una sección, o no hay pantalla que la edite.',
        );
    }

    /**
     * Y ninguna puede estar en dos: dos páginas escribiendo la misma clave harían que la última
     * en guardar gane, que es una pelea silenciosa entre pantallas.
     */
    public function test_no_key_belongs_to_two_sections(): void
    {
        $assigned = [];

        foreach (WebsiteSettingSections::all() as $section) {
            foreach (WebsiteSettingSections::keys($section) as $key) {
                $this->assertArrayNotHasKey(
                    $key,
                    $assigned,
                    "La clave `{$key}` está en más de una sección.",
                );

                $assigned[$key] = $section;
                $this->assertSame($section, WebsiteSettingSections::sectionOf($key));
            }
        }

        $this->assertSame(count(SiteSetting::allowedKeys()), count($assigned));
    }

    /**
     * Las seis páginas se llaman y se describen en los cuatro idiomas del panel.
     *
     * El portero de traducciones del clúster **no** cubre esto: las claves de sección se piden
     * concatenadas (`'website.settings_sections.'.static::section().'.label'`), y su propio
     * docblock declara que descarta las concatenaciones. Sin este test, una sección sin etiqueta
     * mostraría la clave cruda en el menú.
     */
    public function test_every_section_has_a_label_and_a_description_in_every_locale(): void
    {
        foreach (['nl', 'en', 'fr', 'de'] as $locale) {
            foreach (WebsiteSettingSections::all() as $section) {
                foreach (['label', 'description'] as $part) {
                    $key = "website.settings_sections.{$section}.{$part}";
                    $translated = trans($key, [], $locale);

                    $this->assertNotSame(
                        $key,
                        $translated,
                        "Falta `{$key}` en `{$locale}`: el panel mostraría la clave cruda.",
                    );
                }
            }
        }
    }
}
