<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Database\Factories\KnxProjectFactory;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * An installation project. Addressed by `code` ("C1618"), unique per tenant —
 * that is the natural key the whole contract uses.
 *
 * `devices_planned` / `devices_done` / `photos` are counters the office app
 * reads straight from the project row (contract §3 `Project`); keeping them
 * denormalised is what makes `GET /projects` a single query.
 */
class KnxProject extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const STATUSES = ['planned', 'busy', 'delivery', 'done'];

    protected $table = 'knx_projects';

    protected $fillable = [
        'organization_id',
        'client_id',
        'code',
        'name',
        'city',
        'site_street',
        'site_postal_code',
        'site_city',
        'site_access_note',
        'lead_employee_id',
        'devices_planned',
        'devices_done',
        'photos',
        'status',
        'deadline',
    ];

    protected static function newFactory(): KnxProjectFactory
    {
        return KnxProjectFactory::new();
    }

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'devices_planned' => 'integer',
            'devices_done' => 'integer',
            'photos' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(KnxClient::class, 'client_id');
    }

    /**
     * The structured site address of the contract (KNX-51), or null for a project
     * created before the address existed. Null means "we never had one", never a
     * half-filled block: the office sets the four columns together.
     *
     * @return array{street: string|null, postalCode: string|null, city: string|null, accessNote: string|null}|null
     */
    public function siteAddress(): ?array
    {
        if ($this->site_street === null && $this->site_postal_code === null && $this->site_city === null && $this->site_access_note === null) {
            return null;
        }

        return [
            'street' => $this->site_street,
            'postalCode' => $this->site_postal_code,
            'city' => $this->site_city,
            'accessNote' => $this->site_access_note,
        ];
    }

    /** The project lead (Kantoor shows `lead` as a short name, e.g. "L. Smet"). */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'lead_employee_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(KnxProjectRoom::class, 'project_id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(KnxBoard::class, 'project_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(KnxDevice::class, 'project_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(KnxConflict::class, 'project_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(KnxNotification::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnxDocument::class, 'project_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(KnxExport::class, 'project_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(KnxZone::class, 'project_id');
    }

    public function functionSpecs(): HasMany
    {
        return $this->hasMany(KnxFunctionSpec::class, 'project_id');
    }

    public function acceptanceTests(): HasMany
    {
        return $this->hasMany(KnxAcceptanceTest::class, 'project_id');
    }

    public function planningAssignments(): HasMany
    {
        return $this->hasMany(KnxPlanningAssignment::class, 'project_id');
    }

    /** Conflicts the office still has to work (`open` + `in_review`). */
    public function openConflicts(): HasMany
    {
        return $this->conflicts()->whereIn('status', KnxConflict::OPEN_STATUSES);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $like = '%'.$term.'%';
            $inner->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('city', 'like', $like);
        });
    }
}
