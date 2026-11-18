<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One name a room is known by in another vocabulary (KNX-44).
 *
 * The office's own room name is canonical; ETS, the worklist and the plan carry
 * their own spellings of "the same" space, and they are not reconciled. An alias
 * keeps that knowledge without pretending the names are equal: the import resolves a
 * worklist room through these, and a room sheet can show where a name came from.
 */
class KnxRoomAlias extends Model
{
    public const SOURCE_ETS = 'ets';

    public const SOURCE_WORKLIST = 'worklist';

    public const SOURCE_PLAN = 'plan';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [
        self::SOURCE_ETS,
        self::SOURCE_WORKLIST,
        self::SOURCE_PLAN,
        self::SOURCE_MANUAL,
    ];

    protected $table = 'knx_room_aliases';

    protected $fillable = ['room_id', 'source', 'name'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }
}
