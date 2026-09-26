<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxAcceptanceTestFactory;

/**
 * An acceptance test (§4.C). It hangs off a *function*, not only off a device —
 * that is what lets the office answer "verified against which agreed behaviour".
 */
class KnxAcceptanceTest extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const STATUSES = ['pending', 'passed', 'failed', 'blocked', 'not_applicable'];

    protected $table = 'knx_acceptance_tests';

    protected $fillable = [
        'organization_id',
        'project_id',
        'function_id',
        'action',
        'expected',
        'observed',
        'status',
        'note',
        'physical_check',
        'executor_employee_id',
        'executed_at',
        'evidence_urls',
        'issue_id',
    ];

    protected static function newFactory(): KnxAcceptanceTestFactory
    {
        return KnxAcceptanceTestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'physical_check' => 'boolean',
            'executed_at' => 'datetime',
            'evidence_urls' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(KnxFunctionSpec::class, 'function_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'executor_employee_id');
    }
}
