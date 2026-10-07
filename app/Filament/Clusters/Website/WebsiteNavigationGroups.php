<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website;

/**
 * Los cuatro grupos del submenú de Website (WU-S1 del rediseño del backoffice).
 *
 * Salen del prototipo aprobado, que los declara por página. Un clúster de Filament **agrupa** su
 * subnavegación por el `$navigationGroup` de cada componente, así que esto no es decoración: el
 * valor tiene que ser **el mismo** entre los ítems de un grupo, y por eso la etiqueta se resuelve
 * en un solo sitio (dos traducciones del mismo texto darían dos grupos distintos).
 *
 * El orden importa y es el del prototipo: Publicación primero —es lo que se hace con el
 * contenido una vez escrito—, después el contenido, después los ajustes, y la bandeja al final.
 */
final class WebsiteNavigationGroups
{
    public const PUBLISHING = 'publishing';
    public const CONTENT = 'content';
    public const SITE_SETTINGS = 'site_settings';
    public const INBOX = 'inbox';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PUBLISHING, self::CONTENT, self::SITE_SETTINGS, self::INBOX];
    }

    public static function label(string $group): string
    {
        return __("website.nav_groups.{$group}");
    }
}
