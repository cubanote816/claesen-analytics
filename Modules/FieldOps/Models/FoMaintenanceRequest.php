<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FoMaintenanceRequest extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $table = 'fo_maintenance_requests';

    protected $fillable = [
        'client_id', 'reported_by_user_id', 'source', 'status', 'category', 'impact',
        'description', 'public_response', 'maintainable_type',
        'maintainable_id', 'luminaire_position_id', 'work_order_id',
        'installation_snapshot', 'intake_data', 'acknowledged_at', 'resolved_at',
        'confirmed_at', 'reopened_at', 'closed_at', 'closed_by_user_id',
        'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason',
    ];

    protected $casts = [
        'status' => MaintenanceRequestStatus::class,
        'installation_snapshot' => 'array',
        'intake_data' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function client()
    {
        return $this->belongsTo(FoClient::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function maintainable()
    {
        return $this->morphTo();
    }

    public function luminairePosition()
    {
        return $this->belongsTo(LuminairePosition::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(FoMaintenanceWorkOrder::class);
    }

    public function workOrders()
    {
        return $this->belongsToMany(
            FoMaintenanceWorkOrder::class,
            'fo_maintenance_request_work_order',
            'maintenance_request_id',
            'work_order_id',
        )->withPivot('created_at')->orderByPivot('created_at');
    }

    public function messages()
    {
        return $this->hasMany(FoMaintenanceRequestMessage::class, 'maintenance_request_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
