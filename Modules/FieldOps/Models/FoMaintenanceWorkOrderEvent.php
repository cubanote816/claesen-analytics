<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderEventType;

class FoMaintenanceWorkOrderEvent extends Model
{
    protected $table = 'fo_maintenance_work_order_events';

    protected $fillable = [
        'work_order_id', 'event_type', 'actor_user_id', 'from_status', 'to_status',
        'from_assigned_employee_id', 'to_assigned_employee_id', 'data', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => MaintenanceWorkOrderEventType::class,
            'data' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Work-order events are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Work-order events are append-only.'));
    }

    public function workOrder()
    {
        return $this->belongsTo(FoMaintenanceWorkOrder::class, 'work_order_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
