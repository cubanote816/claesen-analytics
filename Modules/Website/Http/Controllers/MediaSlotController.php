<?php

declare(strict_types=1);

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
 */
class MediaSlotController extends Controller
{
    public function index(): JsonResponse
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
                ->map(fn (MediaSlot $slot) => $this->serialize($slot))
                ->all(),
        ]);
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

    private function serialize(MediaSlot $slot): array
    {
        $media = $slot->media;
        $meta = $this->conversionMeta($media);

        return [
            'slot' => $slot->slot,
            'url' => $media->getUrl('optimized'),
            'width' => $meta['width'],
            'height' => $meta['height'],
            'checksum' => $meta['checksum'],
            'mime_type' => $media->mime_type,
            'alt' => $this->localeMap($media->getCustomProperty('alt')),
            'caption' => $this->localeMap($media->getCustomProperty('caption')),
        ];
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
