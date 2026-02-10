<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Modules\Website\App\Enums\ProjectCategory;
use App\Traits\HasAiTranslations;

class Project extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, InteractsWithMedia, HasAiTranslations;

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
        'featured_image_url',
        'gallery',
    ];

    protected $hidden = [
        'media',
    ];

    public $translatable = [
        'title',
        'content',
        'description',
        'location',
        'client',
        'seo_tags',
    ];

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
        $this->addMediaCollection('featured_image')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(200);

        $this->addMediaConversion('gallery')
            ->width(1200)
            ->height(800);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('featured_image') ?: null;
    }

    public function getGalleryAttribute()
    {
        return $this->getMedia('gallery')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'gallery' => $media->getUrl('gallery'),
                'caption' => $media->getCustomProperty('caption'),
                'alt' => $media->getCustomProperty('alt'),
            ];
        });
    }
}
