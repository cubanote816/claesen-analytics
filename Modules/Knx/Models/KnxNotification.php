<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxNotificationFactory;

/**
 * A device reported from the field app that the office has not acknowledged yet.
 * Kantoor polls this every 5 s; `acked` is `acknowledged_at !== null`.
 *
 * `board_id` nullable is meaningful: a field registration can report a board the
 * office has never seen, which is exactly what the office must triage.
 */
class KnxNotification extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    protected $table = 'knx_notifications';

    protected $fillable = [
        'organization_id',
        'project_id',
        'device_id',
        'board_id',
        'type',
        'address',
        'room',
        'serial',
        'reported_by_employee_id',
        'reported_at',
        'acknowledged_at',
    ];

    protected static function newFactory(): KnxNotificationFactory
    {
        return KnxNotificationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KnxDevice::class, 'device_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KnxBoard::class, 'board_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'reported_by_employee_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function isAcked(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
