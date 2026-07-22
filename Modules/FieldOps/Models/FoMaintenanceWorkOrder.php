<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\FoMaintenanceWorkOrderFactory;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;

class FoMaintenanceWorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_maintenance_work_orders';

    protected $fillable = [
        'created_by_user_id', 'maintenance_plan_id', 'fo_maintenance_type_id', 'maintainable_id',
        'maintainable_type', 'luminaire_position_id', 'client_id', 'assigned_employee_id',
        'assigned_by_user_id', 'assigned_at',
        'status', 'priority', 'source', 'scheduled_for', 'due_at', 'problem_description',
        'instructions', 'started_at', 'started_by_user_id', 'submitted_at', 'returned_at',
        'returned_by_user_id', 'return_reason', 'completed_at',
        'completed_by_user_id', 'completion_details', 'root_cause', 'solution_applied',
        'completion_notes', 'validated_at', 'validated_by_user_id', 'override_reason',
        'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'maintenance_record_id',
    ];

    protected $casts = [
        'status' => MaintenanceWorkOrderStatus::class,
        'scheduled_for' => 'datetime',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
        'completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completion_details' => 'array',
    ];

    protected static function newFactory(): FoMaintenanceWorkOrderFactory
    {
        return FoMaintenanceWorkOrderFactory::new();
    }

    public function maintainable()
    {
        return $this->morphTo();
    }

    public function plan()
    {
        return $this->belongsTo(FoMaintenancePlan::class, 'maintenance_plan_id');
    }

    public function maintenanceType()
    {
        return $this->belongsTo(FoMaintenanceType::class, 'fo_maintenance_type_id');
    }

    public function luminairePosition()
    {
        return $this->belongsTo(LuminairePosition::class, 'luminaire_position_id');
    }

    public function client()
    {
        return $this->belongsTo(FoClient::class, 'client_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(FoMaintenanceRecord::class, 'maintenance_record_id');
    }

    public function serviceRequest()
    {
        return $this->hasOne(FoMaintenanceRequest::class, 'work_order_id');
    }

    public function serviceRequests()
    {
        return $this->belongsToMany(
            FoMaintenanceRequest::class,
            'fo_maintenance_request_work_order',
            'work_order_id',
            'maintenance_request_id',
        )->withPivot('created_at');
    }

    public function events()
    {
        return $this->hasMany(FoMaintenanceWorkOrderEvent::class, 'work_order_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
