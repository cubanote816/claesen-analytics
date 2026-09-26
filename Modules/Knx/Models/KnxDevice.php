<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxDeviceFactory;

/**
 * A registered device: physical address, room, board, serial.
 *
 * `isNew` / `hasConflict` from the contract are NOT columns — they are the
 * answers to "does it come from the field unacknowledged" and "does its address
 * collide with an open conflict", so they are derived where they are used.
 */
class KnxDevice extends Model
{
    use HasFactory;

    public const SOURCE_ETS = 'ets';

    public const SOURCE_FIELD = 'field';

    protected $table = 'knx_devices';

    protected $fillable = [
        'project_id',
        'room_id',
        'board_id',
        'type',
        'address',
        'serial',
        'source',
        'registered_by_employee_id',
        'registered_at',
        'acknowledged_at',
    ];

    protected static function newFactory(): KnxDeviceFactory
    {
        return KnxDeviceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KnxBoard::class, 'board_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'registered_by_employee_id');
    }

    public function scopeFromField(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_FIELD);
    }

    /** A field registration the office has not confirmed yet (`isNew`). */
    public function isNew(): bool
    {
        return $this->source === self::SOURCE_FIELD && $this->acknowledged_at === null;
    }
}
