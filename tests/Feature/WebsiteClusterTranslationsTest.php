<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Ninguna etiqueta del clúster Website puede salir en crudo.
 *
 * `__()` devuelve **la propia clave** cuando falta la traducción, así que un recurso que
 * pide una clave inexistente no falla: imprime `website.media_slots.plural_label` en la
 * barra de pestañas, delante de quien usa el panel. Pasó exactamente eso (CLA-481): el
 * recurso de media slots usaba seis claves que no existían en ningún fichero de idioma, y
 * el defecto no se veía en ningún test porque la pantalla «funcionaba».
 *
 * Este test lee lo que el clúster pide —claves sueltas y grupos de valores— y comprueba que
 * ninguna salga **en crudo**, en ninguno de los idiomas que el panel puede usar.
 *
 * Lo que garantiza, medido y no supuesto: que exista **alguna** traducción. No que cada
 * idioma tenga la suya — comprobado quitando una clave solo de `nl`: el test sigue pasando,
 * porque el traductor cae al idioma de respaldo y devuelve el texto inglés en lugar de la
 * clave. Para el cliente eso es una etiqueta en otro idioma (feo, pero se entiende), no una
 * clave técnica. Apretar hasta «cada idioma por separado» es otra decisión, y este test
 * declara dónde está su límite en vez de aparentar más de lo que hace.
 */
final class WebsiteClusterTranslationsTest extends TestCase
{
    /** The locales the panels can render in. */
    private const LOCALES = ['nl', 'en'];

    /**
     * @return array<int, string>
     */
    private function keysUsedByTheWebsiteCluster(): array
    {
        $keys = [];
        $groups = [];

        foreach (File::allFiles(app_path('Filament/Clusters/Website')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Dos formas de pedir una clave, y las dos cuentan: `__()` directo y el helper
            // compartido (`self::human('website.…')`), que es como las piden las pantallas
            // desde que dejaron de enseñar los valores internos. Mirar solo `__()` dejaría
            // fuera justo las que más importan.
            preg_match_all(
                "/(?:__\(|human\()\s*'(website\.[a-z0-9_.]+)'/i",
                $file->getContents(),
                $matches,
            );

            foreach ($matches[1] as $key) {
                // Una clave que termina en punto es una concatenación
                // (`__('website.publication.status.' . $status->value)`), no una clave:
                // sus valores reales se cubren por separado, como cualquier otro literal.
                if (str_ends_with($key, '.')) {
                    continue;
                }

                // `human('website.…statuses', $value)` pasa el **grupo** del que sale el
                // valor, no una clave: se comprueba aparte, porque `__()` con un grupo
                // devuelve un array y compararlo con el prefijo pasaría siempre.
                if (str_contains($file->getContents(), "human('{$key}'")) {
                    $groups[$key] = true;

                    continue;
                }

                $keys[$key] = true;
            }
        }

        return ['keys' => array_keys($keys), 'groups' => array_keys($groups)];
    }

    public function test_every_key_the_cluster_asks_for_has_a_translation(): void
    {
        ['keys' => $keys, 'groups' => $groups] = $this->keysUsedByTheWebsiteCluster();

        $this->assertNotEmpty($keys, 'no encontré claves: la ruta del clúster cambió y este test ya no mira donde cree');

        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key) {
                $this->assertNotSame(
                    $key,
                    __($key),
                    "falta la traducción de [{$key}]: el panel la mostraría en crudo (comprobado con el idioma [{$locale}])",
                );
            }
        }
    }

    public function test_every_value_group_the_cluster_asks_for_exists(): void
    {
        ['groups' => $groups] = $this->keysUsedByTheWebsiteCluster();

        $this->assertNotEmpty($groups, 'no encontré grupos: las pantallas dejaron de usar el helper');

        foreach ($groups as $group) {
            // Un grupo entero ausente es exactamente el defecto que ocurrió con media slots:
            // seis valores que no existían en ningún idioma y que el panel imprimía en crudo.
            // Que exista el grupo se puede comprobar desde aquí; que cada valor esté, no —eso
            // lo fijan los tests de cada pantalla, que sí saben qué valores usa (`statuses.machine`).
            $this->assertIsArray(
                __($group),
                "falta el grupo [{$group}]: sus valores saldrían en crudo, como pasó con media slots",
            );
        }
    }
}
