<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * An admin's override of the configured daily USD limit (one row per organization).
 */
class KnxAiSpendSetting extends Model
{
    use BelongsToKnxTenant;

    protected $table = 'knx_ai_spend_settings';

    protected $fillable = ['organization_id', 'daily_limit_usd', 'updated_by_employee_id'];

    protected function casts(): array
    {
        return ['daily_limit_usd' => 'decimal:6'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'updated_by_employee_id');
    }
}
