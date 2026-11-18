<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * The names other vocabularies use for this room (KNX-44). The office's `name` is
     * canonical; these are how ETS, the worklist or the plan spell the same space.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(KnxRoomAlias::class, 'room_id');
    }

    /** The readiness zone of this room (zona = habitación), if it has one. */
    public function zone(): HasOne
    {
        return $this->hasOne(KnxZone::class, 'room_id');
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
