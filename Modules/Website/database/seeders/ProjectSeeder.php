<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Site;
use Modules\Website\Models\Project;

/**
 * Los proyectos del sitio — **DEMO, y se nota**.
 *
 * **Estos seis proyectos NO son reales.** El propio YAML del que salen lo declara, uno por uno:
 * *"fictional fixture from the approved design prototype ... not a real completed project"*, con
 * el aviso *"Fictional fixture — not approved public content"*. Eran material del prototipo de
 * diseño del cliente, y en su repositorio están detrás de un gate (`GATE_REAL_PROJECTS`) que
 * impide que se rendericen en un build de producción.
 *
 * Se siembran **a propósito** y por decisión del usuario, para poder probar el circuito
 * `API → web` de punta a punta antes de que exista contenido real. El riesgo que eso abre, dicho
 * sin adornos: **ese gate vive en el build del sitio y no sabe nada de filas de una base de
 * datos**, así que en cuanto la web lea de aquí, estos casos se mostrarán como trabajo propio
 * salvo que alguien los marque. Por eso van marcados **en los datos** y no sólo en este
 * comentario:
 *
 *   * el `title` empieza por `DEMO · `, que es lo primero y más grande que se ve en una ficha;
 *   * el `client` lleva el aviso completo, que es donde un visitante buscaría el nombre del
 *     cliente — y del que no hay dato real, porque el YAML no lo trae.
 *
 * El `year` se deja **nulo** y no se inventa: el YAML tampoco lo trae. Igual los `facts` del caso
 * (segmento, técnica, duración...): el modelo no tiene dónde guardarlos, así que se pierden; está
 * declarado en `odd/tasks/website-content-seeders.md`.
 *
 * Las imágenes sí se copian (9 ficheros, 344K) a `resources/assets/demo-projects/`: son las del
 * prototipo y su derecho de uso no está confirmado — de ahí que el slot de media de la web pida
 * `usage_rights_confirmed_at` en el camino feliz.
 */
class ProjectSeeder extends Seeder
{
    private const SITE_KEY = 'electro-bertels';

    public function run(): void
    {
        $site = Site::query()->where('key', self::SITE_KEY)->first();

        if ($site === null) {
            $this->command?->warn(sprintf('ProjectSeeder: no "%s" site; nothing seeded.', self::SITE_KEY));

            return;
        }

        $assets = module_path('Website', 'resources/assets/demo-projects');
        $projects = $this->projects();

        foreach ($projects as $i => $project) {
            $model = Project::query()->firstOrCreate(
                ['site_id' => $site->getKey(), 'slug' => $project['slug']],
                $project['attributes'] + ['order_index' => $i + 1],
            );

            // Las imágenes sólo si faltan: volver a correr el seeder no debe duplicarlas.
            if ($model->getMedia('featured_image')->isEmpty()) {
                $model->addMedia($assets.'/'.$project['featured_image'])
                    ->preservingOriginal()
                    ->toMediaCollection('featured_image');
            }

            foreach ($project['gallery'] as $file) {
                if ($model->getMedia('detail_gallery')->contains(fn ($m) => $m->file_name === $file)) {
                    continue;
                }

                $model->addMedia($assets.'/'.$file)
                    ->preservingOriginal()
                    ->withCustomProperties(['caption' => $project['captions'][$file] ?? null])
                    ->toMediaCollection('detail_gallery');
            }
        }

        $this->command?->info(sprintf(
            'ProjectSeeder: %d DEMO projects for site "%s" (marked in title and client; year left null on purpose).',
            count($projects),
            $site->key,
        ));
    }

    /**
     * @return array<int, array{slug: string, featured_image: string, gallery: array<int, string>, captions: array<string, ?string>, attributes: array<string, mixed>}>
     */
    private function projects(): array
    {
        return [
            [
                'slug' => 'buitenverlichting-bedrijfsterrein',
                'featured_image' => 'eb-proj-6.webp',
                'gallery' => [],
                'captions' => [],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · Buitenverlichting bedrijfsterrein',
                    ],
                    'description' => [
                        'nl' => 'Terrein- en toegangsverlichting met tijdsturing en aparte kring voor camera\'s.',
                    ],
                    'category' => 'verlichting',
                    'location' => [
                        'nl' => 'Mol',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => false,
                ],
            ],
            [
                'slug' => 'knx-domotica-nieuwbouwwoning',
                'featured_image' => 'eb-proj-1.webp',
                'gallery' => [
                    'eb-case-g1.webp',
                    'eb-case-g2.webp',
                    'eb-case-g3.webp',
                ],
                'captions' => [
                    'eb-case-g1.webp' => 'Detail: verdeelbord',
                    'eb-case-g2.webp' => 'Detail: bediening',
                    'eb-case-g3.webp' => 'Detail: verlichting',
                ],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · KNX-domotica in nieuwbouwwoning',
                    ],
                    'description' => [
                        'nl' => 'Licht, zonwering, verwarming en toegang op één bus, uitbreidbaar zonder breekwerk.',
                    ],
                    'category' => 'domotica',
                    'location' => [
                        'nl' => 'Balen',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => true,
                    'work_story' => [
                        'nl' => 'Particulier · KNX · Balen',
                    ],
                    'challenge' => [
                        'nl' => 'Eén systeem voor licht, zonwering, verwarming en toegang, met de mogelijkheid om later uit te breiden zonder opnieuw kabels te trekken.',
                    ],
                    'solution' => [
                        'nl' => 'Volledige elektrische installatie met KNX-bus, centrale verdeling in de technische ruimte en scenario\'s per zone. Bediening via drukknoppen en een visualisatie op tablet.',
                    ],
                    'result' => [
                        'nl' => 'Opgeleverd met keuringsverslag. Uitbreidingen — laadpaal, extra zones — blijven een parameterwijziging in plaats van breekwerk.',
                    ],
                ],
            ],
            [
                'slug' => 'qbus-sturing-kantoorgebouw',
                'featured_image' => 'eb-proj-5.webp',
                'gallery' => [],
                'captions' => [],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · Qbus-sturing in kantoorgebouw',
                    ],
                    'description' => [
                        'nl' => 'Zonering per verdieping met aanwezigheidsdetectie en centrale uitschakeling.',
                    ],
                    'category' => 'domotica',
                    'location' => [
                        'nl' => 'Dessel',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => false,
                ],
            ],
            [
                'slug' => 'renovatie-jaren-70-woning',
                'featured_image' => 'eb-proj-3.webp',
                'gallery' => [],
                'captions' => [],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · Volledige renovatie jaren-70 woning',
                    ],
                    'description' => [
                        'nl' => 'Installatie vernieuwd en keuringsconform opgeleverd, inclusief nieuwe kring voor laadpaal.',
                    ],
                    'category' => 'renovatie',
                    'location' => [
                        'nl' => 'Leopoldsburg',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => false,
                ],
            ],
            [
                'slug' => 'verdeelborden-productiehal',
                'featured_image' => 'eb-proj-2.webp',
                'gallery' => [],
                'captions' => [],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · Verdeelborden voor productiehal',
                    ],
                    'description' => [
                        'nl' => 'Nieuwe borden en machinevoedingen, gefaseerd geplaatst tijdens het bouwverlof.',
                    ],
                    'category' => 'industrie',
                    'location' => [
                        'nl' => 'Mol',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => false,
                ],
            ],
            [
                'slug' => 'winkelverlichting-en-data',
                'featured_image' => 'eb-proj-4.webp',
                'gallery' => [],
                'captions' => [],
                'attributes' => [
                    'title' => [
                        'nl' => 'DEMO · Winkelverlichting en data',
                    ],
                    'description' => [
                        'nl' => 'LED-spots op rail, noodverlichting en netwerkkabels naar kassa en kantoor.',
                    ],
                    'category' => 'verlichting',
                    'location' => [
                        'nl' => 'Balen',
                    ],
                    'client' => [
                        'nl' => 'DEMO — fictief voorbeeld uit het ontwerpprototype, geen goedgekeurd project.',
                    ],
                    'year' => null,
                    'published' => true,
                    'featured' => false,
                ],
            ],
        ];
    }
}
