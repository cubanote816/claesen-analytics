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
            'mime_type' => $media->mime_type,
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
     * Dimensions + checksum of the PUBLIC optimized conversion (never the
     * private original). Cached per media revision so repeated builds are
     * cheap and unchanged files keep the same checksum.
     *
     * @return array{width: ?int, height: ?int, checksum: ?string}
     */
    private function conversionMeta($media): array
    {
        $key = sprintf(
            'website.media-slot-meta.%d.%s',
            $media->id,
            $media->updated_at?->timestamp ?? '0'
        );

        return Cache::remember($key, 3600, function () use ($media): array {
            $empty = ['width' => null, 'height' => null, 'checksum' => null];

            try {
                $disk = Storage::disk($media->conversions_disk);
                $relativePath = $media->getPathRelativeToRoot('optimized');

                if (! $disk->exists($relativePath)) {
                    return $empty;
                }

                $absolutePath = $disk->path($relativePath);
                $dimensions = @getimagesize($absolutePath);

                return [
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                    'checksum' => 'sha256:'.hash_file('sha256', $absolutePath),
                ];
            } catch (\Throwable) {
                // A missing/unreadable conversion must never 500 the public
                // listing — dimensions/checksum stay null and the frontend
                // falls back to its ImagePlaceholder behaviour.
                return $empty;
            }
        });
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
