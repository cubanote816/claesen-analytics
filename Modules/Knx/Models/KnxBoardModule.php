<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One physical module on a board — its identity of instance (KNX-3).
 *
 * The worklist only carries the order number, so two identical actuators on the same
 * board would be indistinguishable; `slot` (the 1-based position on the board,
 * derived from the worklist order) is what tells them apart.
 */
class KnxBoardModule extends Model
{
    use HasFactory;

    public const ORIGIN_IMPORT = 'import';

    public const ORIGIN_OFFICE = 'office';

    public const ORIGIN_FIELD = 'field';

    public const REVIEW_CONFIRMED = 'confirmed';

    public const REVIEW_UNREVIEWED = 'unreviewed';

    protected $table = 'knx_board_modules';

    protected $fillable = ['board_id', 'device', 'slot', 'origin', 'review_state', 'updated_by_employee_id'];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KnxBoard::class, 'board_id');
    }

    /** Who last wrote the module (KNX-52). */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'updated_by_employee_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(KnxBoardLink::class, 'module_id')->orderBy('position');
    }
}
