<?php

namespace Modules\Website\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'location' => $this->location,
            'year' => $this->year,
            'featured' => $this->featured,
            'order_index' => $this->order_index,
            'featured_image' => [
                'original' => $this->getFirstMediaUrl('featured_image'),
                'optimized' => $this->getFirstMediaUrl('featured_image', 'optimized') ?: $this->getFirstMediaUrl('featured_image'),
                'thumb' => $this->getFirstMediaUrl('featured_image', 'thumb'),
            ],
            'gallery' => $this->getMedia('gallery')->map(function ($media) {
                return [
                    'id'        => $media->id,
                    'name'      => $media->name,
                    'original'  => $media->getUrl(),
                    'optimized' => $media->getUrl('optimized') ?: $media->getUrl(),
                    'gallery'   => $media->getUrl('gallery'),
                    'thumb'     => $media->getUrl('thumb'),
                    'caption'   => $this->resolveLocaleValue($media->getCustomProperty('caption')),
                    'alt'       => $this->resolveLocaleValue($media->getCustomProperty('alt')),
                    'mime_type' => $media->mime_type,
                    'size'      => $media->size,
                ];
            }),
            'related'      => ProjectResource::collection($this->whenLoaded('related')),
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
