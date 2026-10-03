<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Nombres para personas, en lugar de los valores internos.
 *
 * El panel lo lee quien aprueba, no quien programa. `machine`, `needs_review` o los códigos
 * ISO significan algo en la base de datos y nada en la pantalla, y los dos sitios donde pasó
 * (el estado de las páginas y el de las traducciones) son el mismo defecto. Esto vive aquí
 * para que ambos pregunten lo mismo en vez de que cada pantalla tenga su copia.
 *
 * `__()` devuelve **la propia clave** cuando falta la traducción, así que soltar
 * `website.translation_review.statuses.failed` delante de quien aprueba sería peor que el
 * nombre técnico: un valor sin etiqueta se muestra tal cual, que se entiende aunque se lea
 * raro, y no engaña a nadie.
 */
trait NamesValuesForHumans
{
    /**
     * @param  string  $key  prefijo de traducción donde vive el valor, p. ej.
     *                       `website.translation_review.statuses`
     */
    protected static function human(string $key, string $value): string
    {
        $full = "{$key}.{$value}";
        $label = __($full);

        return $label === $full ? $value : $label;
    }
}
