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
 */
class KnxBoard extends Model
{
    use HasFactory;

    protected $table = 'knx_boards';

    protected $fillable = ['project_id', 'code', 'name'];

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
}
