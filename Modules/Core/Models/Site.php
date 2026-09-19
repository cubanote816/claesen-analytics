<?php

namespace Modules\Core\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The public identity of one organization's website inside the shared
 * backoffice (docs/ai/adr-multi-organization.md, decision D3).
 *
 * `site_id` is the single source of truth on every site-owned row —
 * `organization_id` is never duplicated as a second foreign key on the
 * owning table. The organization is always derived through this model's
 * `organization()` relation.
 *
 * Not consulted by any authorization or scoping logic yet: as of phase P1
 * this model is pure structure. Enforcement lands in phase P5.
 */
class Site extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const CLAESEN_KEY = 'claesen-verlichting';

    protected $fillable = [
        'organization_id',
        'key',
        'domain',
        'default_locale',
        'locales',
        'status',
        // F3/CLA-472: per-site override of config/static_site.php's global
        // webhook settings. Null on every existing site (Claesen included) —
        // Modules\Website\Services\StaticSitePublicationService falls back
        // to the global config when these are unset.
        'static_site_webhook_url',
        'static_site_webhook_secret',
        'static_site_webhook_timeout',
        'static_site_health_url',
        'static_site_debounce_seconds',
    ];

    protected $hidden = [
        'static_site_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'locales' => 'array',
        ];
    }

    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The id of the bootstrap Claesen site row (phase P1 seed migration).
     * Every Website creation path defaults here until the public API and the
     * panel become site-aware — see ADR phase P3a / F3-F4.
     */
    public static function claesenId(): int
    {
        return static::query()->where('key', self::CLAESEN_KEY)->value('id')
            ?? throw new \RuntimeException('The Claesen bootstrap site row is missing — run migrations.');
    }
}
