<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;


/**
 * Las conversiones que sirve la web pública, en un solo sitio.
 *
 * Los recursos del API (`ProjectResource`, `MediaSlotController`) no serializan nunca el fichero
 * original: piden conversiones por nombre (`optimized`, `thumb`, `gallery` y sus variantes AVIF).
 * El original vive en un disco privado a propósito, para que nadie pueda alcanzarlo ni
 * enumerarlo desde fuera.
 *
 * Vive en `Core` y no en `Website` porque lo usan **los dos**: los proyectos (Website) y el
 * **sitio** (Core), que es el dueño semánticamente correcto de la imaginería del sitio. Un modelo
 * de Core no debe depender de Website; al revés sí, y esa es la dirección que se respeta.
 *
 * Estaban escritas dentro de `Project`, y al darle media al **sitio** —para que pueda ser dueño
 * de su propia imaginería, que es el dueño semánticamente correcto— hacían falta las mismas seis.
 * Copiarlas habría dejado dos listas que se desincronizan en cuanto alguien añada una conversión
 * en un solo lado, y el síntoma sería una URL vacía en un campo que sí existe: exactamente la
 * clase de defecto silencioso que no se ve hasta que una página sale sin imagen.
 *
 * El método se llama `registerSiteImageConversions` y no `registerMediaConversions` a propósito:
 * ese último es el hook que define `InteractsWithMedia`, y dos traits con el mismo método en la
 * misma clase colisionan (el error es un fatal, no un aviso: *"has not been applied ... because of
 * collision"*). Cada modelo lo llama desde su propio hook, que es una línea y se lee.
 *
 * Los tamaños y la calidad son los que ya estaban; esto no cambia ninguna conversión existente.
 */
trait SiteImageConversions
{
    /**
     * Los formatos que acepta la web, con el mismo valor que ya tenía `Project`.
     *
     * Vive aquí para que el sitio y el proyecto no puedan tener listas distintas, y es una
     * constante de trait a propósito: pasa a ser constante de cada clase que la usa, así que
     * `Project::MEDIA_MIME_TYPES` sigue resolviendo igual y su test de contrato no cambia.
     */
    public const MEDIA_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function registerSiteImageConversions(): void
    {
        $this->addMediaConversion('thumb')
            ->format('webp')
            ->width(300)
            ->height(200)
            ->quality(85);

        $this->addMediaConversion('thumb_avif')
            ->format('avif')
            ->width(300)
            ->height(200)
            ->quality(85);

        $this->addMediaConversion('optimized')
            ->format('webp')
            ->width(1200)
            ->height(1200)
            ->quality(80);

        $this->addMediaConversion('optimized_avif')
            ->format('avif')
            ->width(1200)
            ->height(1200)
            ->quality(80);

        $this->addMediaConversion('gallery')
            ->format('webp')
            ->width(1200)
            ->height(800)
            ->quality(80);

        $this->addMediaConversion('gallery_avif')
            ->format('avif')
            ->width(1200)
            ->height(800)
            ->quality(80);
    }
}
