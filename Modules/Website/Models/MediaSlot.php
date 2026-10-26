<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Concerns\BelongsToSite;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * CLA-481: a named media slot per site. The slot is the stable join key the
 * frontend build uses (`home.hero`, `over-ons.team`, …); the media item it
 * points at can change without any frontend change.
 *
 * ADR D3: only `site_id` on this table (UNIQUE(site_id, slot)). ADR D6 is
 * untouched — this model is not shared with jobs.
 *
 * alt/caption per locale stay where they already live (CLA-467/470): on the
 * media item's custom properties, AI-generated/translated by
 * Modules\Website\Jobs\GenerateGalleryMediaMetadataJob. This model only
 * binds a slot name to a media item.
 */
class MediaSlot extends Model
{
    use BelongsToSite;
    use HasFactory;
    use LogsActivity;

    /**
     * WU4: qué imagen sirve un slot es contenido del sitio — cambiarla y no registrarlo dejaría
     * el rastro de «qué cambió» incompleto justo en la superficie más visible (la foto).
     *
     * El default de spatie no registra nada, así que esto no es opcional.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'website_media_slots';

    protected $fillable = [
        'site_id',
        'slot',
        'media_id',
    ];

    /** The slots the Electro Bertels frontend expects (§7.4 of the brief). */
    public const SUGGESTED_SLOTS = [
        'home.hero',
        'bedrijven.hero',
        'projectcase.hero',
        'winkel.photo',
        'over-ons.team',
        'over-ons.cert-1',
        'over-ons.cert-2',
        'over-ons.cert-3',
        'over-ons.cert-4',
        'home.featured-diagram',
        'home.shop-photo',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Scope to the given resolved site id. Belt-and-braces for public
     * reads — BelongsToSite's global scope is inert while
     * config('organizations.enforce') is off (ADR D4).
     */
    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }
}
