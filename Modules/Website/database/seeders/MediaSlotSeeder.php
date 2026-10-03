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

    /** El valor de derechos: dice que NO están confirmados, en vez de fingir una fecha. */
    private const RIGHTS_VALUE = 'DEMO — niet bevestigd (prototypebeeld, geen goedgekeurde fotografie)';

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

        $media = $site->addMedia($path)
            ->preservingOriginal()
            ->withCustomProperties(['usage_rights_confirmed_at' => self::RIGHTS_VALUE])
            ->toMediaCollection('imagery');

        // `withCustomProperties` ya lo aplica; este update existe por si una versión de la librería
        // lo aplica sólo al vuelo. Es idempotente y deja el valor explícito en la fila.
        DB::table('media')
            ->where('id', $media->getKey())
            ->update(['custom_properties' => json_encode(['usage_rights_confirmed_at' => self::RIGHTS_VALUE])]);

        return $media->refresh();
    }
}
