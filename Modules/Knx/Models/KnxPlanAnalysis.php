<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * A validated AI proposal for one plan page (KNX-48, plano IA). Never a confirmed model.
 */
class KnxPlanAnalysis extends Model
{
    use BelongsToKnxTenant;

    protected $table = 'knx_plan_analyses';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'page',
        'document_revision',
        'provider',
        'model',
        'schema_version',
        'proposal',
        'usage',
        'created_by_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'proposal' => 'array',
            'usage' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnxDocument::class, 'document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'created_by_employee_id');
    }
}
