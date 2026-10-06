<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link that ties a module's channel to a room, a communication object and a group
 * address (KNX-3).
 *
 * This row **is** the fact of the domain — `(channel, room, object, group address)` —
 * and not a `channel -> room` field: measured over the project's 52 real channels, not
 * one serves more than one room, while a channel carries several objects of that same
 * room. The inverse is many-to-one (up to 11 channels serve one room), which this shape
 * already supports without changing.
 */
class KnxBoardLink extends Model
{
    use HasFactory;

    protected $table = 'knx_board_links';

    protected $fillable = [
        'module_id',
        'channel',
        'room',
        'object',
        'role',
        'ga',
        'ga_name',
        'dpt',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(KnxBoardModule::class, 'module_id');
    }
}
