<?php

namespace Modules\Website\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    // TODO(WEB-015): This resource is used for both index and detail endpoints.
    // work_story/challenge/solution/result and detail_gallery could be excluded
    // from the index response to reduce payload — track as a future optimisation
    // once the frontend confirms it doesn't need them on listing pages.

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->resolveLocaleValue($this->getTranslations('title')),
            'description' => $this->resolveLocaleValue($this->getTranslations('description')),

            // Work Details — translatable (nl/en/fr/de via Gemini auto-translate).
            // Null means the field was never filled on this project.
            'work_story'  => $this->resolveLocaleValue($this->getTranslations('work_story')),
            'challenge'   => $this->resolveLocaleValue($this->getTranslations('challenge')),
            'solution'    => $this->resolveLocaleValue($this->getTranslations('solution')),
            'result'      => $this->resolveLocaleValue($this->getTranslations('result')),

            'category' => $this->category,
            'location' => $this->resolveLocaleValue($this->getTranslations('location')),
            'year' => $this->year,
            'featured' => $this->featured,
            'order_index' => $this->order_index,
            // F3/CLA-467: the original file lives on a private disk (see
            // Modules\Website\Models\Project::registerMediaCollections()) —
            // never $media->getUrl()/getFirstMediaUrl(collection) without a
            // conversion name. Only conversion URLs (public disk) are ever
            // serialized, so there is no field here an outsider could use
            // to reach or enumerate an original.
            'featured_image' => [
                'optimized' => $this->getFirstMediaUrl('featured_image', 'optimized'),
                'thumb' => $this->getFirstMediaUrl('featured_image', 'thumb'),
                'optimized_avif' => $this->getFirstMediaUrl('featured_image', 'optimized_avif'),
                'thumb_avif' => $this->getFirstMediaUrl('featured_image', 'thumb_avif'),
            ],
            'gallery' => $this->getMedia('gallery')->map(function ($media) {
                return [
                    'id'        => $media->id,
                    'name'      => $media->name,
                    'optimized' => $media->getUrl('optimized'),
                    'gallery'   => $media->getUrl('gallery'),
                    'thumb'     => $media->getUrl('thumb'),
                    'optimized_avif' => $media->getUrl('optimized_avif'),
                    'gallery_avif' => $media->getUrl('gallery_avif'),
                    'thumb_avif' => $media->getUrl('thumb_avif'),
                    'caption'   => $this->resolveLocaleValue($media->getCustomProperty('caption')),
                    'alt'       => $this->resolveLocaleValue($media->getCustomProperty('alt')),
                    'focal_point' => $media->getCustomProperty('focal_point', ['x' => 0.5, 'y' => 0.5]),
                    'mime_type' => $media->mime_type,
                    'size'      => $media->size,
                ];
            }),
            // Always an array — empty [] when no images have been uploaded yet.
            'detail_gallery' => $this->getMedia('detail_gallery')->map(function ($media) {
                return [
                    'id'        => $media->id,
                    'optimized' => $media->getUrl('optimized'),
                    'gallery'   => $media->getUrl('gallery'),
                    'thumb'     => $media->getUrl('thumb'),
                    'optimized_avif' => $media->getUrl('optimized_avif'),
                    'gallery_avif' => $media->getUrl('gallery_avif'),
                    'thumb_avif' => $media->getUrl('thumb_avif'),
                    'caption'   => $this->resolveLocaleValue($media->getCustomProperty('caption')),
                    'alt'       => $this->resolveLocaleValue($media->getCustomProperty('alt')),
                    'focal_point' => $media->getCustomProperty('focal_point', ['x' => 0.5, 'y' => 0.5]),
                    'mime_type' => $media->mime_type,
                    'size'      => $media->size,
                ];
            })->values(),
            'related' => ProjectResource::collection($this->whenLoaded('related')),
            'published_at' => $this->published_at,
        ];
    }

    /**
     * Resolve a translatable custom property value for the current locale.
     * Fallback chain: requested locale → nl → en → first available → null.
     */
    private function resolveLocaleValue(mixed $value): ?string
    {
        if (!is_array($value)) {
            return $value ?: null;
        }

        $locale = app()->getLocale();

        foreach ([$locale, 'nl', 'en'] as $candidate) {
            if (!empty($value[$candidate])) {
                return $value[$candidate];
            }
        }

        foreach ($value as $text) {
            if (!empty($text)) {
                return $text;
            }
        }

        return null;
    }
}
