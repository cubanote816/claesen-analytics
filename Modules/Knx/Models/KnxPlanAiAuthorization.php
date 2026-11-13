<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * A person's authorization to send ONE document to ONE AI provider (KNX-48).
 */
class KnxPlanAiAuthorization extends Model
{
    use BelongsToKnxTenant;

    protected $table = 'knx_plan_ai_authorizations';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'provider',
        'granted_by_employee_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnxDocument::class, 'document_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'granted_by_employee_id');
    }
}
