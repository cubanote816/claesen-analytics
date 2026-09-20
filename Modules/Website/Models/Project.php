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

    /**
     * F3/CLA-467: originals live on the private 'local' disk
     * (storage/app/private, same disk Modules\FieldOps/Safety already use
     * for private attachments) — never publicly reachable, so there is no
     * URL pattern an outsider could use to enumerate or download an
     * original. Every conversion below is explicitly stored on 'public'
     * instead: those derived, resized/re-encoded files are the only thing
     * the public API/website ever links to (see getApiGalleryAttribute()/
     * getApiFeaturedImageUrlAttribute() below, and
     * Modules\Website\App\Http\Resources\ProjectResource, which no longer
     * expose $media->getUrl() — the original's URL — at all).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')
            ->useDisk('local')
            ->storeConversionsOnDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);

        $this->addMediaCollection('gallery')
            ->useDisk('local')
            ->storeConversionsOnDisk('public')
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);

        $this->addMediaCollection('detail_gallery')
            ->useDisk('local')
            ->storeConversionsOnDisk('public')
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);
    }

    /**
     * Three responsive breakpoints (thumb/gallery/optimized, unchanged from
     * before this ticket) x two formats. AVIF is generated in addition to —
     * never instead of — WebP: Imagick/GD AVIF encoding is confirmed
     * available on this host, but a consumer's <picture> element is
     * expected to fall back to the WebP source for any browser/decoder
     * that doesn't support AVIF, so both must exist.
     */
    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->format('webp')
            ->width(300)
            ->height(200)
            ->quality(85);

        $this->addMediaConversion('thumb_avif')
            ->format('avif')
            ->width(300)
            ->height(200)
            ->quality(85);

        $this->addMediaConversion('optimized')
            ->format('webp')
            ->width(1200)
            ->height(1200)
            ->quality(80);

        $this->addMediaConversion('optimized_avif')
            ->format('avif')
            ->width(1200)
            ->height(1200)
            ->quality(80);

        $this->addMediaConversion('gallery')
            ->format('webp')
            ->width(1200)
            ->height(800)
            ->quality(80);

        $this->addMediaConversion('gallery_avif')
            ->format('avif')
            ->width(1200)
            ->height(800)
            ->quality(80);
    }

    /**
     * Never $media->getUrl() (the original, private-disk file — see
     * registerMediaCollections()) — only ever a conversion URL, which lives
     * on the public disk. 'optimized' is the largest generated size, used
     * as the fallback of last resort if for some reason the conversion
     * queue hasn't produced 'optimized' yet.
     */
    public function getApiFeaturedImageUrlAttribute()
    {
        return $this->getFirstMediaUrl('featured_image', 'optimized') ?: null;
    }

    public function getApiGalleryAttribute()
    {
        return $this->getMedia('gallery')->map(function ($media) {
            return [
                'id'          => $media->id,
                'name'        => $media->name,
                'file_name'   => $media->file_name,
                'optimized'   => $media->getUrl('optimized'),
                'thumb'       => $media->getUrl('thumb'),
                'gallery'     => $media->getUrl('gallery'),
                'optimized_avif' => $media->getUrl('optimized_avif'),
                'thumb_avif'  => $media->getUrl('thumb_avif'),
                'gallery_avif' => $media->getUrl('gallery_avif'),
                'caption'     => $media->getCustomProperty('caption'),
                'alt'         => $media->getCustomProperty('alt'),
                'focal_point' => $media->getCustomProperty('focal_point', ['x' => 0.5, 'y' => 0.5]),
            ];
        });
    }
}
