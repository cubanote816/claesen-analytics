<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GlossaryService;

/**
 * CLA-611 (gap G4): a per-site glossary entry. `do_not_translate` terms
 * (KNX, QBUS, brands) cross every locale verbatim; other terms pin the
 * approved translation per locale so the AI stays consistent
 * (verdeelbord → tableau de distribution / distribution board /
 * Verteilerschrank — the documented real-world example).
 */
class Glossary extends Model
{
    protected $table = 'website_glossaries';

    protected $fillable = [
        'site_id',
        'term',
        'translations',
        'do_not_translate',
    ];

    protected $casts = [
        'translations' => 'array',
        'do_not_translate' => 'boolean',
    ];

    protected static function booted(): void
    {
        // The derived glossary version is cached — editing a term must
        // invalidate it so translation cache keys change (CLA-611, cache by
        // hash(source + locale + glossary_version)).
        static::saved(fn (self $entry) => GlossaryService::forgetCacheFor((int) $entry->site_id));
        static::deleted(fn (self $entry) => GlossaryService::forgetCacheFor((int) $entry->site_id));
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
