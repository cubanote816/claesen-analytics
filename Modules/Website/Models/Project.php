<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Website\Database\Factories\ProjectFactory;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Modules\Website\App\Enums\ProjectCategory;
use Modules\Intelligence\Traits\HasAiTranslations;

class Project extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia, HasAiTranslations;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected $table = 'website_projects';

    protected $fillable = [
        'slug',
        'title',
        'content', // Legacy field, kept for safety
        'description',
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
        'location',
        'client',
        'seo_tags',
    ];

    public function getAiTranslatableAttributes(): array
    {
        return [
            'title',
            'description',
            'location',
            'client',
        ];
    }

    protected $casts = [
        'category' => ProjectCategory::class,
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
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $this->addMediaCollection('featured_image')
            ->singleFile()
            ->acceptsMimeTypes($allowedMimeTypes);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes($allowedMimeTypes);
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
