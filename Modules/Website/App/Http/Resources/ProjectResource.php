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
            'title' => $this->title,
            'description' => $this->description,

            // Work Details — translatable (nl/en/fr via Gemini auto-translate).
            // 'de' has no auto-translation; HasTranslations falls back to 'nl'
            // (config app.fallback_locale) when no manual 'de' translation is present.
            // Null means the field was never filled on this project.
            'work_story'  => $this->work_story,
            'challenge'   => $this->challenge,
            'solution'    => $this->solution,
            'result'      => $this->result,

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
                    'id' => $media->id,
                    'name' => $media->name,
                    'original' => $media->getUrl(),
                    'optimized' => $media->getUrl('optimized') ?: $media->getUrl(),
                    'gallery' => $media->getUrl('gallery'),
                    'thumb' => $media->getUrl('thumb'),
                    'caption' => $media->getCustomProperty('caption'),
                    'alt' => $media->getCustomProperty('alt'),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ];
            }),
            // Always an array — empty [] when no images have been uploaded yet.
            'detail_gallery' => $this->getMedia('detail_gallery')->map(function ($media) {
                return [
                    'id'       => $media->id,
                    'original' => $media->getUrl(),
                    'optimized' => $media->getUrl('optimized') ?: $media->getUrl(),
                    'gallery'  => $media->getUrl('gallery'),
                    'thumb'    => $media->getUrl('thumb'),
                    'caption'  => $media->getCustomProperty('caption'),
                    'alt'      => $media->getCustomProperty('alt'),
                    'mime_type' => $media->mime_type,
                    'size'     => $media->size,
                ];
            })->values(),
            'related' => ProjectResource::collection($this->whenLoaded('related')),
            'published_at' => $this->published_at,
        ];
    }
}
