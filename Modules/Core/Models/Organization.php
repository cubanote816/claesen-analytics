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
}
