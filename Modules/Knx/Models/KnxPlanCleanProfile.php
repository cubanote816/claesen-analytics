<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * The clean-plan profile of one page of one plan revision (KNX-48).
 *
 * `roles` maps an opaque group id (`layer:<name>`, `style:<signature>`) to a role. The
 * server stores it as sent and never reads the PDF.
 */
class KnxPlanCleanProfile extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const ROLES = ['wall', 'window', 'door', 'stair', 'lift', 'void', 'hidden', 'unknown'];

    protected $table = 'knx_plan_clean_profiles';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'page',
        'roles',
        'boundaries',
        'revision',
        'processing_version',
        'updated_by_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'roles' => 'array',
            'boundaries' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnxDocument::class, 'document_id');
    }
}
