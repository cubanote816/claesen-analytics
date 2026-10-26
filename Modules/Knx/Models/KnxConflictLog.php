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
 * No created_at/updated_at: `at` is the domain timestamp and nothing reads
 * model timestamps here.
 */
class KnxConflictLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'knx_conflict_logs';

    protected $fillable = ['conflict_id', 'at', 'action', 'address'];

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
