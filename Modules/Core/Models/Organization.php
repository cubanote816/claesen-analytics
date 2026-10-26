<?php

namespace Modules\Core\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company operating inside the shared backoffice — Claesen today,
 * Electro Bertels from phase P7 onward. See
 * docs/ai/adr-multi-organization.md for the full multi-organization
 * design.
 *
 * Not consulted by any authorization or scoping logic yet: as of phase P1
 * this model is pure structure. Enforcement lands in phase P5, gated by
 * config('organizations.enforce').
 */
class Organization extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const CLAESEN_SLUG = 'claesen';

    protected $fillable = [
        'slug',
        'name',
        'status',
        'retention_policy',
    ];

    protected function casts(): array
    {
        return [
            'retention_policy' => 'array',
        ];
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * F4/CLA-476 — first real consumer of retention_policy (scaffolding
     * since CLA-458/P1). Modules\Website\Services\RetentionService reads
     * 'spam_days'/'closed_anonymize_days' through this; every existing
     * organization has retention_policy => null, so this always falls
     * back to the global config('website.retention.*') default — zero
     * change in behaviour until an organization is given its own override.
     */
    public function retentionSetting(string $key, mixed $default = null): mixed
    {
        return $this->retention_policy[$key] ?? $default;
    }

    /**
     * The id of the bootstrap Claesen organization row (phase P1 seed
     * migration). Every user-creation path defaults here until phase P6
     * adds a real organization picker — see ADR phase P2.
     */
    public static function claesenId(): int
    {
        return static::query()->where('slug', self::CLAESEN_SLUG)->value('id')
            ?? throw new \RuntimeException('The Claesen bootstrap organization row is missing — run migrations.');
    }
}
