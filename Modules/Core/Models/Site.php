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

    protected $fillable = [
        'organization_id',
        'key',
        'domain',
        'default_locale',
        'locales',
        'status',
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
}
