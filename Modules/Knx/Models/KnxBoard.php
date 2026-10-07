<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Database\Factories\KnxBoardFactory;

/**
 * A distribution board ("verdeelbord E10"). `code` is what the UI shows next to
 * a device; `name` is the longer label used by field notifications.
 *
 * `slug` is the board's **external identity** (KNX-3): the worklist slug the boards
 * endpoint exposes as `id` and the string a plan marker's `boardId` carries. It is
 * separate from `code` because the code is the short label ("E10", "caja-1") and the
 * slug is the full cabinet name. Nullable: a board created from a field registration
 * has a code and no slug.
 */
class KnxBoard extends Model
{
    use HasFactory;

    protected $table = 'knx_boards';

    protected $fillable = ['project_id', 'code', 'slug', 'name', 'floor'];

    protected static function newFactory(): KnxBoardFactory
    {
        return KnxBoardFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(KnxDevice::class, 'board_id');
    }

    /**
     * The modules of the board, in slot order. Empty for a board the office has not
     * modelled yet, which is an honest answer and not an error.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(KnxBoardModule::class, 'board_id')->orderBy('slot');
    }
}
