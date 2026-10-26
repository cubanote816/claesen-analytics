<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Site;
use Modules\Website\Models\LegalDocument;

/**
 * Los tres documentos legales del sitio — **con el texto que existe hoy, que no es el texto legal**.
 *
 * El cliente **todavía no ha entregado el copy legal**. Su repositorio lo dice con todas las
 * letras: los tres ficheros son `status: draft`, con `GATE_LEGAL_COPY` y el comentario *"real
 * privacy policy copy has not been supplied"*. Lo único real que hay es el **título** y el
 * **aviso de "en preparación"**, y eso es exactamente lo que se siembra como cuerpo: el aviso,
 * no una política inventada. Escribir aquí un texto legal ficticio sería lo peor que podría
 * hacer este seeder.
 *
 * **`version` y `effective_date` van marcados como lo que son**, por decisión del usuario
 * (2026-10-03): la versión dice `DEMO` y la fecha de entrada en vigor es **hoy**. Ninguna de las
 * dos significa nada legalmente — el documento que entraría en vigor no existe — y el esquema
 * las exige no nulas, así que la alternativa era no sembrar. Quedan declaradas aquí y en el
 * fichero de tarea para que nadie las lea como un dato del cliente.
 *
 * Cuando llegue el texto real, esto son veinte líneas: se cambia el cuerpo, se pone la versión
 * que el cliente apruebe y su fecha.
 *
 * `firstOrCreate` y no un upsert: si alguien edita la versión o la fecha en el backoffice, volver
 * a correr esto no puede deshacerlo.
 */
class LegalDocumentSeeder extends Seeder
{
    private const SITE_KEY = 'electro-bertels';

    public function run(): void
    {
        $site = Site::query()->where('key', self::SITE_KEY)->first();

        if ($site === null) {
            $this->command?->warn(sprintf('LegalDocumentSeeder: no "%s" site; nothing seeded.', self::SITE_KEY));

            return;
        }

        $documents = $this->documents();

        foreach ($documents as $document) {
            LegalDocument::query()->firstOrCreate(
                ['site_id' => $site->getKey(), 'doc_id' => $document['doc_id']],
                [
                    'title' => ['nl' => $document['title']],
                    'body' => ['nl' => $document['body']],
                    'version' => 'DEMO',
                    'effective_date' => now()->toDateString(),
                ],
            );
        }

        $this->command?->info(sprintf(
            'LegalDocumentSeeder: %d documents for site "%s" — body is the "in preparation" notice, not legal copy; version/date are DEMO.',
            count($documents),
            $site->key,
        ));
    }

    /**
     * @return array<int, array{doc_id: string, title: string, body: string}>
     */
    private function documents(): array
    {
        return [
            [
                'doc_id' => 'cookies',
                'title' => 'Cookiebeleid',
                'body' => 'Deze pagina is in voorbereiding. Het cookiebeleid van Electro Bertels wordt hier gepubliceerd zodra het is goedgekeurd.',
            ],
            [
                'doc_id' => 'privacy',
                'title' => 'Privacyverklaring',
                'body' => 'Deze pagina is in voorbereiding. De privacyverklaring van Electro Bertels wordt hier gepubliceerd zodra ze is goedgekeurd.',
            ],
            [
                'doc_id' => 'terms',
                'title' => 'Algemene voorwaarden',
                'body' => 'Deze pagina is in voorbereiding. De algemene voorwaarden van Electro Bertels worden hier gepubliceerd zodra ze zijn goedgekeurd.',
            ],
        ];
    }
}
