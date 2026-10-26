<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Site;
use Modules\Website\Models\MediaSlot;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Los slots de media del sitio — **sólo los que tienen imagen, y marcados como DEMO**.
 *
 * El mapeo slot → imagen no se inventa aquí: es la tabla S2.1 de
 * `odd/tasks/dynamic-content-sync.md` del repositorio del sitio, leída por ellos del árbol de
 * render, no deducida de los nombres. Se siembran los **siete** que tienen imagen:
 *
 *   `over-ons.team` → `eb-about-team`   ·  `over-ons.cert-1..4` → `eb-cert-1..4`
 *   `home.featured-diagram` → `domotica.png`  ·  `home.shop-photo` → `winkel.png`
 *
 * Los **tres hero** (`home.hero`, `bedrijven.hero`, `projectcase.hero`) **no se siembran** porque
 * no existe imagen suya: su propio repositorio los documenta como vacíos por diseño
 * (`GATE_REAL_MEDIA`: *"Cleared photography; 3 hero slots are empty"*). Y `winkel.photo` tampoco,
 * porque su tabla dice de ese slot que *"the API offers a slot the site never renders"*.
 *
 * **Los derechos de uso van marcados como lo que son.** El API sólo sirve un slot cuyo media tenga
 * `usage_rights_confirmed_at` relleno, y las imágenes disponibles son del **prototipo de diseño**,
 * no fotografía del cliente con derechos claros. Poner ahí una fecha sería afirmar una
 * confirmación que nadie ha hecho. Por decisión del usuario (2026-10-03) el valor es un **texto
 * que dice que no están confirmados**, nunca una fecha: el slot se sirve —para poder probar el
 * circuito entero— pero el dato no miente sobre lo que es.
 *
 * **`alt` y `caption` por idioma, y de dónde salen.** El validador del sync del sitio exige que
 * los dos sean objetos por idioma; con ellos en `null` rechazaba la superficie entera (y dejaba su
 * snapshot intacto, que es lo correcto). El `alt` se copia del **contenido real del cliente** —
 * `over-ons.history.photoAlt`, `home.media.knxSchemaAlt`, `home.media.winkelPhotoAlt` y los
 * `certifications.slotLabels` — en los cuatro idiomas, incluido el token `{{addressFull}}` de la
 * foto de la tienda, que resuelve el propio sitio al renderizar. El `caption` va **vacío**: el
 * contenido no tiene pies de foto para estos slots, y un objeto vacío dice «no hay» sin inventar
 * texto.
 *
 * Los media cuelgan del **sitio**, que es su dueño semánticamente correcto (una foto de equipo no
 * es una foto de obra). Antes sólo `Project` tenía media, así que la única forma de sembrarlos era
 * colgarlos de un proyecto y publicarlos en su galería.
 *
 * `firstOrCreate` y las imágenes sólo si faltan: correr esto dos veces no duplica nada ni deshace
 * una edición del backoffice.
 */
class MediaSlotSeeder extends Seeder
{
    private const SITE_KEY = 'electro-bertels';

    /**
     * El `alt` por slot y por idioma, copiado del contenido del sitio.
     *
     * @var array<string, array<string, string>>
     */
    private const ALT = [
        'over-ons.team' => [
            'nl' => 'Team van Electro Bertels voor de winkel',
            'en' => 'Electro Bertels team in front of the shop',
            'fr' => 'Équipe d\'Electro Bertels devant le magasin',
            'de' => 'Team von Electro Bertels vor dem Ladengeschäft',
        ],
        'home.featured-diagram' => [
            'nl' => 'Schema: KNX-installatie in een nieuwbouwwoning',
            'en' => 'Diagram: KNX installation in a new-build home',
            'fr' => 'Schéma : installation KNX dans une habitation neuve',
            'de' => 'Schema: KNX-Installation in einem Neubau',
        ],
        'home.shop-photo' => [
            'nl' => 'Winkel Electro Bertels, {{addressFull}}',
            'en' => 'Electro Bertels shop, {{addressFull}}',
            'fr' => 'Magasin Electro Bertels, {{addressFull}}',
            'de' => 'Electro Bertels Ladengeschäft, {{addressFull}}',
        ],
        'over-ons.cert-1' => [
            'nl' => 'Logo keuringsorganisme',
            'en' => 'Inspection body logo',
            'fr' => 'Logo organisme de contrôle',
            'de' => 'Logo Prüforganisation',
        ],
        'over-ons.cert-2' => [
            'nl' => 'Logo Techlink',
            'en' => 'Techlink logo',
            'fr' => 'Logo Techlink',
            'de' => 'Logo Techlink',
        ],
        'over-ons.cert-3' => [
            'nl' => 'Logo KNX Partner',
            'en' => 'KNX Partner logo',
            'fr' => 'Logo KNX Partner',
            'de' => 'Logo KNX Partner',
        ],
        'over-ons.cert-4' => [
            'nl' => 'Logo verzekeraar',
            'en' => 'Insurer logo',
            'fr' => 'Logo assureur',
            'de' => 'Logo Versicherer',
        ],
    ];

    /**
     * El valor de derechos: dice que NO están confirmados, en vez de fingir una fecha.
     *
     * Con guion ASCII y no con raya: el literal del fichero está en UTF-8, pero al escribirlo a
     * MySQL la raya llegaba rota (`DEMO ? niet bevestigd`), y un aviso roto en el dato que
     * precisamente existe para no mentir no sirve de nada.
     */
    private const RIGHTS_VALUE = 'DEMO - niet bevestigd (prototypebeeld, geen goedgekeurde fotografie)';

    /**
     * slot => fichero, dentro de resources/assets/demo-site-imagery/
     *
     * @var array<string, string>
     */
    private const SLOTS = [
        'over-ons.team' => 'eb-about-team.webp',
        'over-ons.cert-1' => 'eb-cert-1.webp',
        'over-ons.cert-2' => 'eb-cert-2.webp',
        'over-ons.cert-3' => 'eb-cert-3.webp',
        'over-ons.cert-4' => 'eb-cert-4.webp',
        'home.featured-diagram' => 'domotica.png',
        'home.shop-photo' => 'winkel.png',
    ];

    public function run(): void
    {
        $site = Site::query()->where('key', self::SITE_KEY)->first();

        if ($site === null) {
            $this->command?->warn(sprintf('MediaSlotSeeder: no "%s" site; nothing seeded.', self::SITE_KEY));

            return;
        }

        $assets = module_path('Website', 'resources/assets/demo-site-imagery');

        foreach (self::SLOTS as $slot => $file) {
            $media = $this->mediaFor($site, $assets.'/'.$file);

            MediaSlot::query()->firstOrCreate(
                ['site_id' => $site->getKey(), 'slot' => $slot],
                ['media_id' => $media->getKey()],
            );
        }

        $this->command?->info(sprintf(
            'MediaSlotSeeder: %d slots for site "%s" — the 3 hero slots stay empty on purpose; usage rights say DEMO, not confirmed.',
            count(self::SLOTS),
            $site->key,
        ));
    }

    /**
     * El media del sitio para un fichero, creándolo la primera vez.
     *
     * La media de Spatie es polimórfica, así que el sitio puede ser su dueño sin tocar ninguna
     * tabla. El original va al disco privado y sólo las conversiones quedan públicas, igual que en
     * los proyectos: es lo único que el API serializa.
     */
    private function mediaFor(Site $site, string $path): Media
    {
        $existing = $site->getMedia('imagery')->firstWhere('file_name', basename($path));

        if ($existing !== null) {
            return $existing;
        }

        $slot = array_search(basename($path), self::SLOTS, true);

        return $site->addMedia($path)
            ->preservingOriginal()
            ->withCustomProperties([
                'usage_rights_confirmed_at' => self::RIGHTS_VALUE,
                'alt' => $slot === false ? [] : self::ALT[$slot],
                // Vacío y no null: el contrato del sitio exige un objeto por idioma, y «sin
                // caption» no es lo mismo que un caption inventado.
                'caption' => [],
            ])
            ->toMediaCollection('imagery');
    }
}
