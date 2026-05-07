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
            'related' => ProjectResource::collection($this->whenLoaded('related')),
            'published_at' => $this->published_at,
        ];
    }
}
