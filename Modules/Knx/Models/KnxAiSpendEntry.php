<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * One reservation of the daily USD ledger and, once known, its real cost.
 *
 * Counts against the day: `settled` by `actual_usd`; `reserved` and `uncertain` by
 * `reserved_usd`; `released` (the request was never sent) by nothing.
 */
class KnxAiSpendEntry extends Model
{
    use BelongsToKnxTenant;

    public const RESERVED = 'reserved';

    public const SETTLED = 'settled';

    public const UNCERTAIN = 'uncertain';

    public const RELEASED = 'released';

    protected $table = 'knx_ai_spend_entries';

    protected $fillable = [
        'organization_id', 'day', 'provider', 'operation', 'model', 'status',
        'reserved_usd', 'actual_usd', 'usage', 'project_id', 'document_id', 'page',
        'processing_id', 'request_id', 'reason', 'created_by_employee_id', 'settled_at',
        'reconciled_by_employee_id', 'reconciled_note',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'usage' => 'array',
            'page' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'created_by_employee_id');
    }
}
