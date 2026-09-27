<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxFunctionSpecFactory;

/**
 * The agreed description of what a room must DO, before it is programmed
 * (§4.B). The list-shaped fields are JSON: they are free-form bullet lists the
 * UI edits as a set, and normalising them would not buy any query we need.
 */
class KnxFunctionSpec extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const STATUSES = ['draft', 'proposed', 'approved', 'changed'];

    protected $table = 'knx_function_specs';

    protected $fillable = [
        'organization_id',
        'project_id',
        'zone_id',
        'name',
        'objective',
        'triggers',
        'conditions',
        'manual_controls',
        'automations',
        'timings',
        'priorities',
        'failure_behaviour',
        'dependencies',
        'acceptance_criteria',
        'group_addresses',
        'dpts',
        'knx_objects',
        'status',
        'version',
        'author_employee_id',
        'approved_by_employee_id',
        'approved_at',
    ];

    protected static function newFactory(): KnxFunctionSpecFactory
    {
        return KnxFunctionSpecFactory::new();
    }

    protected function casts(): array
    {
        return [
            'triggers' => 'array',
            'conditions' => 'array',
            'manual_controls' => 'array',
            'automations' => 'array',
            'dependencies' => 'array',
            'acceptance_criteria' => 'array',
            'group_addresses' => 'array',
            'dpts' => 'array',
            'knx_objects' => 'array',
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(KnxZone::class, 'zone_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'author_employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'approved_by_employee_id');
    }

    public function acceptanceTests(): HasMany
    {
        return $this->hasMany(KnxAcceptanceTest::class, 'function_id');
    }
}
