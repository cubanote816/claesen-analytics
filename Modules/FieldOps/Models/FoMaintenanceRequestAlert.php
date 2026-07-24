<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FieldOps\Enums\MaintenanceRequestAlertType;

class FoMaintenanceRequestAlert extends Model
{
    protected $table = 'fo_maintenance_request_alerts';

    protected $fillable = [
        'maintenance_request_id',
        'alert_type',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'alert_type' => MaintenanceRequestAlertType::class,
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FoMaintenanceRequest::class, 'maintenance_request_id');
    }
}
