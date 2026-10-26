<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website;

use Modules\Website\Models\SiteSetting;

/**
 * Qué claves de `site_settings` edita cada página de Ajustes.
 *
 * Sale del prototipo aprobado (`Mejoras UI backoffice website`), que declara exactamente este
 * reparto. Es la **fuente de verdad de la división**, y una sola: la usan las seis páginas para
 * iterar sus claves, y la usan los badges del menú y la publicación por sección para saber a qué
 * página pertenece un cambio.
 *
 * Dos claves del whitelist **no** están en el reparto del prototipo y sí acá: `phone` y `hours`.
 * Son las de **Claesen** —su frontend las lee textuales, y sus notas lo dicen— y el prototipo es
 * del backoffice de Bertels, así que no las menciona. Sacarlas de la UI rompería la edición de
 * Claesen sin que nadie lo pidiera, así que van en la sección cuyo tema comparten (Contact y
 * Opening hours) y conservan su nota.
 *
 * La nota de `opening_hours` que el prototipo pide («nota opcional traducible») **no** está acá
 * a propósito: no existe una clave para eso, y agregar una que el sitio ignora sería un campo
 * que no llega a ningún lado. El sync del sitio consume 13 claves de las 17 y **descarta las
 * desconocidas en silencio**: primero tiene que existir del lado del sitio, y eso es de esa línea.
 */
final class WebsiteSettingSections
{
    public const GENERAL = 'general';
    public const CONTACT = 'contact';
    public const ADDRESS = 'address';
    public const HOURS = 'hours';
    public const SOCIAL = 'social';
    public const PRIVACY = 'privacy';

    /** @var array<string, list<string>> */
    private const KEYS = [
        self::GENERAL => ['legal_name', 'founded_year', 'vat_number'],
        self::CONTACT => ['phone_display', 'phone_tel', 'whatsapp_display', 'whatsapp_url', 'email', 'phone'],
        self::ADDRESS => ['address_structured', 'address_country', 'address', 'maps_embed_url', 'maps_directions_url'],
        self::HOURS => ['opening_hours', 'hours'],
        self::SOCIAL => ['social_links'],
        self::PRIVACY => ['maps_consent_mode', 'contact_consent_version'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::KEYS);
    }

    /** @return list<string> */
    public static function keys(string $section): array
    {
        return self::KEYS[$section] ?? [];
    }

    /** A qué sección pertenece una clave, o `null` si el reparto no la cubre. */
    public static function sectionOf(string $key): ?string
    {
        foreach (self::KEYS as $section => $keys) {
            if (in_array($key, $keys, true)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Las claves del whitelist que **ninguna** sección edita.
     *
     * Debe estar vacío, y hay un test que lo exige: una clave huérfana es una clave que nadie
     * puede editar desde el panel, y eso se descubre tarde —cuando alguien la busca y no está—.
     * Es el mismo tipo de guarda que el portero de traducciones del clúster: convierte un olvido
     * silencioso en un fallo.
     *
     * @return list<string>
     */
    public static function orphanedKeys(): array
    {
        $assigned = array_merge(...array_values(self::KEYS));

        return array_values(array_diff(array_keys(SiteSetting::allowedKeys()), $assigned));
    }
}
