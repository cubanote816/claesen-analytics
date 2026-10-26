<?php

declare(strict_types=1);

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\MediaSlot;

/**
 * CLA-481: GET /v1/website/media/slots — the resolved site's named media
 * slots for the frontend build: conversion URL only (CLA-467: originals are
 * private and are NEVER serialized), declared width/height for CLS, a
 * stable per-file checksum so the build skips re-downloading unchanged
 * binaries, and per-locale alt/caption maps (only locales that actually
 * have a value — a missing locale is absent from the map, never coalesced
 * to another locale's text, CLA-611 G9 applied to maps).
 *
 * Slots whose media has no recorded usage-rights confirmation
 * (website:confirm-media-usage-rights, CLA-467) are omitted entirely —
 * backend-requirements.md §8.2.6: unauthorized photos are never served.
 *
 * `url` is built from the **request's** host, not from `APP_URL`. The
 * deployment's `APP_URL` is frequently not the address a given consumer reaches
 * the API by (internal host vs public domain — the same mismatch that broke
 * images in production once before, see CLA-444 in
 * docs/ai/fieldops-decisions-log.md), and the site's build downloads these URLs
 * from whichever machine runs the build. A URL that is only correct on the server
 * that generated it is worse than useless: it looks fine and 404s.
 */
class MediaSlotController extends Controller
{
    /**
     * Lo que se devuelve cuando no hay dimensiones que informar. Constante y no un
     * array suelto porque se retorna desde dos sitios (conversión ausente y lectura
     * fallida) y los dos deben decir exactamente lo mismo.
     *
     * @var array{width: null, height: null, mime_type: null, checksum: null}
     */
    private const EMPTY_CONVERSION_META = [
        'width' => null,
        'height' => null,
        'mime_type' => null,
        'checksum' => null,
    ];

    public function index(Request $request): JsonResponse
    {
        $siteId = app(OrganizationContext::class)->siteId();

        $slots = MediaSlot::query()
            ->when($siteId !== null, fn ($query) => $query->forSite($siteId))
            ->with('media')
            ->orderBy('slot')
            ->get();

        return response()->json([
            'data' => $slots
                ->filter(fn (MediaSlot $slot) => $this->isServable($slot))
                ->values()
                ->map(fn (MediaSlot $slot) => $this->serialize($request, $slot))
                ->all(),
        ])
            // El cuerpo cambia según el Host (las URLs son absolutas desde él), y
            // `SetPublicApiCacheHeaders` marca la respuesta `public, max-age=60`.
            // Sin esto, un caché compartido podría servirle a un host la URL del otro.
            ->header('Vary', 'Host');
    }

    /**
     * A slot is servable only when its media item still exists and usage
     * rights were explicitly confirmed for it.
     */
    private function isServable(MediaSlot $slot): bool
    {
        $media = $slot->media;

        return $media !== null
            && filled($media->getCustomProperty('usage_rights_confirmed_at'));
    }

    private function serialize(Request $request, MediaSlot $slot): array
    {
        $media = $slot->media;
        $meta = $this->conversionMeta($media);

        return [
            'slot' => $slot->slot,
            'url' => $this->publicUrl($request, $media),
            'width' => $meta['width'],
            'height' => $meta['height'],
            'checksum' => $meta['checksum'],
            'mime_type' => $meta['mime_type'] ?? $media->mime_type,
            // `(object)` y no el array pelado: un mapa vacío serializa como `[]` (una lista), y
            // el contrato dice que `alt` y `caption` son objetos por idioma. Con un array vacío el
            // sync del sitio rechaza la superficie entera — que es como se encontró esto.
            'alt' => (object) $this->localeMap($media->getCustomProperty('alt')),
            'caption' => (object) $this->localeMap($media->getCustomProperty('caption')),
        ];
    }

    /**
     * The public conversion URL, with the origin taken from the request.
     *
     * The path is still the disk's own (`/storage/…`, including the `/storage`
     * prefix configured for the public disk) so the symlink layout stays the single
     * source of truth; only the scheme+host are swapped. Same shape the fieldops
     * fix settled on (a `/storage/{path}` path resolved by the consumer), except
     * the API can resolve the origin itself because it sees the request.
     */
    private function publicUrl(Request $request, $media): string
    {
        $relative = $media->getPathRelativeToRoot('optimized');
        $path = parse_url(Storage::disk($media->conversions_disk)->url($relative), PHP_URL_PATH);

        return $request->getSchemeAndHttpHost().($path ?: '/storage/'.$relative);
    }

    /**
     * Dimensions, mime and checksum of the PUBLIC optimized conversion (never the
     * private original). Cached per media revision so repeated builds are cheap and
     * unchanged files keep the same checksum.
     *
     * `mime_type` is read from the conversion FILE, not from `$media->mime_type`:
     * that property describes the **original**. A site whose original is a PNG and
     * whose conversion is WebP would otherwise be told `image/png` while the bytes
     * it downloads are WebP — and a consumer that trusts the field to pick a
     * decoder is misled. Reading it here costs nothing: the file is already being
     * opened for the dimensions and the checksum.
     *
     * @return array{width: ?int, height: ?int, mime_type: ?string, checksum: ?string}
     */
    private function conversionMeta($media): array
    {
        $disk = Storage::disk($media->conversions_disk);
        $relativePath = $media->getPathRelativeToRoot('optimized');

        // La existencia del archivo se comprueba ANTES del caché, y el vacío no se
        // cachea nunca. Antes sí se cacheaba, y eso significaba que un slot cuyas
        // conversiones todavía no estaban generadas (justo después de sembrar o subir)
        // quedaba respondiendo `width`/`height`/`checksum` en `null` durante UNA HORA
        // después de que el fichero ya existiera. El sync del sitio exige enteros
        // positivos, así que rechazaba la superficie entera por eso.
        if (! $disk->exists($relativePath)) {
            return self::EMPTY_CONVERSION_META;
        }

        // El `try` envuelve al caché entero, no al revés: `Cache::remember` sólo guarda
        // el resultado cuando el closure **retorna**, así que una lectura que falle
        // tampoco deja un vacío cacheado. Un fallo de lectura no es un hecho sobre la
        // imagen y no se debe congelar una hora como si lo fuera.
        try {
            $key = sprintf(
                'website.media-slot-meta.%d.%s',
                $media->id,
                $media->updated_at?->timestamp ?? '0'
            );

            return Cache::remember($key, 3600, function () use ($disk, $relativePath): array {
                $absolutePath = $disk->path($relativePath);
                $dimensions = @getimagesize($absolutePath);

                return [
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                    'mime_type' => $dimensions['mime'] ?? null,
                    'checksum' => 'sha256:'.hash_file('sha256', $absolutePath),
                ];
            });
        } catch (\Throwable) {
            // Una conversión ausente o ilegible no puede tumbar el listado público: las
            // dimensiones quedan en null y el frontend cae a su ImagePlaceholder. Pero
            // el vacío se devuelve SIN cachear, para que el próximo intento reintente.
            return self::EMPTY_CONVERSION_META;
        }
    }

    /**
     * Normalize a per-locale custom property to a clean locale => string
     * map (legacy string values and empty entries are dropped).
     *
     * @return array<string, string>
     */
    private function localeMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->all();
    }
}
