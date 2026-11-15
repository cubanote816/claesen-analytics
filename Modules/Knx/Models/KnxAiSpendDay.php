<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * The row locked (`FOR UPDATE`) while a reservation of that Brussels day is made.
 */
class KnxAiSpendDay extends Model
{
    use BelongsToKnxTenant;

    protected $table = 'knx_ai_spend_days';

    protected $fillable = ['organization_id', 'day', 'limit_usd'];

    protected function casts(): array
    {
        return ['day' => 'date', 'limit_usd' => 'decimal:6'];
    }
}
