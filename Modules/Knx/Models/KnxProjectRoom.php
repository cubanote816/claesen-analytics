<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Database\Factories\KnxProjectRoomFactory;

/**
 * A room / space of a project. Zones (site readiness) hang off these.
 */
class KnxProjectRoom extends Model
{
    use HasFactory;

    protected $table = 'knx_project_rooms';

    protected $fillable = ['project_id', 'name', 'floor'];

    protected static function newFactory(): KnxProjectRoomFactory
    {
        return KnxProjectRoomFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(KnxDevice::class, 'room_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(KnxZone::class, 'room_id');
    }
}
