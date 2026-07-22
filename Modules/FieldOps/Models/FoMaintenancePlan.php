<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\FoMaintenancePlanFactory;

class FoMaintenancePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_maintenance_plans';

    protected $fillable = [
        'created_by_user_id', 'fo_maintenance_type_id', 'maintainable_id', 'maintainable_type',
        'luminaire_position_id', 'client_id', 'assigned_employee_id', 'recurrence_unit',
        'recurrence_interval', 'next_due_at', 'instructions', 'is_active',
    ];

    protected $casts = [
        'next_due_at' => 'datetime',
        'recurrence_interval' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): FoMaintenancePlanFactory
    {
        return FoMaintenancePlanFactory::new();
    }

    public function maintainable()
    {
        return $this->morphTo();
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function workOrders()
    {
        return $this->hasMany(FoMaintenanceWorkOrder::class, 'maintenance_plan_id');
    }
}
