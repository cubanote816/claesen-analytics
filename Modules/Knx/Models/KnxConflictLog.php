<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxConflictLogFactory;

/**
 * Append-only history entry of a conflict. `address` records the proposed
 * address *at that moment*, because the history is what becomes the ETS
 * correction worklist line.
 *
 * `by` and `note` (KNX-56) carry who took the step and their free note. `by` is text,
 * not an FK: the line is a frozen record of the moment, not a pointer that changes
 * when a person is renamed.
 *
 * No created_at/updated_at: `at` is the domain timestamp and nothing reads
 * model timestamps here.
 */
class KnxConflictLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'knx_conflict_logs';

    protected $fillable = ['conflict_id', 'at', 'action', 'by', 'address', 'note'];

    protected static function newFactory(): KnxConflictLogFactory
    {
        return KnxConflictLogFactory::new();
    }

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }

    public function conflict(): BelongsTo
    {
        return $this->belongsTo(KnxConflict::class, 'conflict_id');
    }
}
