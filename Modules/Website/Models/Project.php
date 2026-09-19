<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Website\Database\Factories\ProjectFactory;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Modules\Intelligence\Traits\HasAiTranslations;

class Project extends Model implements HasMedia
{
    /**
     * CLA-470: single source of truth for what a project's media collections
     * accept — App\Filament\Clusters\Website\Resources\ProjectResource reads
     * this instead of keeping its own copy, so the Filament upload picker
     * and Spatie MediaLibrary's actual server-side enforcement can never
     * diverge again the way they had (the form offered video/mp4 uploads
     * that registerMediaCollections() below always rejected).
     */
    public const MEDIA_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia, HasAiTranslations, BelongsToSite;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected $table = 'website_projects';

    protected $fillable = [
        'site_id',
        'slug',
        'title',
        'content', // Legacy field, kept for safety
        'description',
        'work_story',
        'challenge',
        'solution',
        'result',
        'category',
        'location',
        'year',
        'client',
        'published',
        'featured',
        'order_index',
        'seo_tags', // Legacy
    ];

    protected $appends = [
        'api_featured_image_url',
        'api_gallery',
    ];

    protected $hidden = [
        // 'media', // Commented out to fix Filament form hydration
    ];

    public $translatable = [
        'title',
        'content',
        'description',
        'work_story',
        'challenge',
        'solution',
        'result',
        'location',
        'client',
        'seo_tags',
    ];

    public function getAiTranslatableAttributes(): array
    {
        // Gemini auto-translates all four locales: nl, en, fr, de.
        // HasAiTranslations::$targetLocales = ['nl','en','fr','de'].
        return [
            'title',
            'description',
            'location',
            'client',
            'work_story',
            'challenge',
            'solution',
            'result',
        ];
    }

    protected $casts = [
        // F3/CLA-468: category is a plain slug string now, validated against
        // Modules\Website\Models\ProjectCategory (a site-owned catalog) at
        // the Filament form layer rather than cast to the global PHP enum
        // this replaced (Modules\Website\App\Enums\ProjectCategory, deleted).
        'year' => 'integer',
        'published' => 'boolean',
        'featured' => 'boolean',
        'order_index' => 'integer',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);

        $this->addMediaCollection('detail_gallery')
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->format('webp')
            ->width(300)
            ->height(200)
            ->quality(85);

        $this->addMediaConversion('optimized')
            ->format('webp')
            ->width(1200)
            ->height(1200)
            ->quality(80);

        $this->addMediaConversion('gallery')
            ->format('webp')
            ->width(1200)
            ->height(800)
            ->quality(80);
    }

    public function getApiFeaturedImageUrlAttribute()
    {
        $webp = $this->getFirstMediaUrl('featured_image', 'optimized');
        return $webp ?: ($this->getFirstMediaUrl('featured_image') ?: null);
    }

    public function getApiGalleryAttribute()
    {
        return $this->getMedia('gallery')->map(function ($media) {
            return [
                'id'        => $media->id,
                'name'      => $media->name,
                'file_name' => $media->file_name,
                'url'       => $media->getUrl(),
                'optimized' => $media->getUrl('optimized'),
                'thumb'     => $media->getUrl('thumb'),
                'gallery'   => $media->getUrl('gallery'),
                'caption'   => $media->getCustomProperty('caption'),
                'alt'       => $media->getCustomProperty('alt'),
            ];
        });
    }
}
