<?php

declare(strict_types=1);

namespace Modules\Website\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Site;
use Modules\Website\Models\SiteSetting;

/**
 * Los datos de empresa del sitio de Electro Bertels.
 *
 * **Son reales, no inventados.** Salen del snapshot que el propio sitio del cliente mantiene
 * como fuente única de sus hechos no traducibles (`electrobertels-official`,
 * `src/business/facts.snapshot.json`), verificado contra el handoff de diseño aprobado y
 * congelado por sus propios tests. Aquí se copian los valores tal cual; lo único que cambia es
 * la forma, porque el backend guarda cada dato bajo una clave de su whitelist.
 *
 * Sólo se siembran claves que el whitelist admite (`config('website.site_settings.allowed_keys')`):
 * una clave de más se guardaría sin que nadie la sirviera, que es peor que no tenerla.
 *
 * **Dos claves que faltan, a propósito:**
 *
 *   * `vat_number` — el snapshot lo declara `null`: el número de empresa todavía no lo ha dado
 *     el cliente (su repositorio lo llama `GATE_VAT_NUMBER`). El contrato del endpoint dice que
 *     las claves ausentes se **omiten**, nunca se emiten como null, así que lo correcto aquí es
 *     no escribirla. Rellenarla con algo sería inventar un dato fiscal.
 *   * `contact_consent_version` — no está entre los hechos y es la versión del texto de
 *     consentimiento: una decisión legal del cliente. Consecuencia declarada: al corregir la
 *     clave del sitio (`electro-bertels`, que estaba escrita sin el guion), ese campo pasó a ser
 *     **requerido** en el intake de Bertels y el formulario del frontend todavía no lo envía.
 *
 * `firstOrCreate` y no un upsert: si el cliente cambia su teléfono en el backoffice, volver a
 * correr esto no puede deshacerlo.
 */
class SiteSettingSeeder extends Seeder
{
    /** La clave del sitio, como la usan el panel, el seeder de publicaciones y los tests. */
    private const SITE_KEY = 'electro-bertels';

    public function run(): void
    {
        $site = Site::query()->where('key', self::SITE_KEY)->first();

        if ($site === null) {
            $this->command?->warn(sprintf('SiteSettingSeeder: no "%s" site; nothing seeded.', self::SITE_KEY));

            return;
        }

        foreach ($this->settings() as $key => [$type, $value]) {
            SiteSetting::query()->firstOrCreate(
                ['site_id' => $site->getKey(), 'key' => $key],
                ['type' => $type, 'value' => $value],
            );
        }

        $this->command?->info(sprintf(
            'SiteSettingSeeder: %d settings for site "%s" (vat_number and contact_consent_version deliberately absent).',
            count($this->settings()),
            $site->key,
        ));
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    private function settings(): array
    {
        return [
            'legal_name' => [SiteSetting::TYPE_TEXT, 'Electro Bertels'],
            // El snapshot lo guarda como número y el whitelist lo declara `json`, así que se
            // guarda con su forma original en lugar de convertirlo en texto.
            'founded_year' => [SiteSetting::TYPE_JSON, 1977],

            'phone_display' => [SiteSetting::TYPE_TEXT, '014 81 30 80'],
            'phone_tel' => [SiteSetting::TYPE_TEXT, 'tel:+3214813080'],
            'whatsapp_display' => [SiteSetting::TYPE_TEXT, '+32 14 81 30 80'],
            'whatsapp_url' => [SiteSetting::TYPE_TEXT, 'https://wa.me/3214813080'],
            'email' => [SiteSetting::TYPE_TEXT, 'info@electrobertels.be'],

            // `address` es lo que se imprime; `address_structured` es el mismo dato por partes.
            // Los dos, porque el whitelist los declara por separado.
            'address' => [SiteSetting::TYPE_TEXT, 'Benoit Jansenstraat 4, 2490 Balen'],
            'address_structured' => [SiteSetting::TYPE_JSON, [
                'street' => 'Benoit Jansenstraat 4',
                'postal_code' => '2490',
                'city' => 'Balen',
                'country_code' => 'BE',
            ]],

            // Traducible: el nombre del país se escribe distinto en cada idioma del sitio, y los
            // cuatro salen del snapshot.
            'address_country' => [SiteSetting::TYPE_TRANSLATABLE, [
                'nl' => 'België',
                'fr' => 'Belgique',
                'en' => 'Belgium',
                'de' => 'Belgien',
            ]],

            'maps_embed_url' => [SiteSetting::TYPE_TEXT, 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d593.856105007773!2d5.165231190647068!3d51.16449980918741!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2sbe!4v1732211529092&output=embed'],
            'maps_directions_url' => [SiteSetting::TYPE_TEXT, 'https://www.google.com/maps/dir/?api=1&destination=Benoit+Jansenstraat+4,+2490+Balen,+Belgi%C3%AB'],

            // Los VALORES son los del snapshot, pero la clave va en `day_key`, que es la forma
            // que el contrato del API exige (validado por el propio sync del sitio): el snapshot
            // los llama `dayKey` porque son sus tipos internos, y aquí no. Se conserva su clave
            // de cuatro días (`tuesdayToFriday`) y el corte de mediodía, que son los datos.
            'opening_hours' => [SiteSetting::TYPE_JSON, [
                [
                    'day_key' => 'sunday',
                    'hours' => null,
                ],
                [
                    'day_key' => 'monday',
                    'hours' => null,
                ],
                [
                    'day_key' => 'tuesdayToFriday',
                    'hours' => [
                        [
                            'open' => '09:00',
                            'close' => '12:00',
                        ],
                        [
                            'open' => '13:00',
                            'close' => '18:00',
                        ],
                    ],
                ],
                [
                    'day_key' => 'saturday',
                    'hours' => [
                        [
                            'open' => '09:00',
                            'close' => '12:00',
                        ],
                    ],
                ],
            ]],
        ];
    }
}
