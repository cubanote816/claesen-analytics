<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\FoMaintenanceRecordFactory;

class FoMaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_maintenance_records';

    protected $fillable = [
        'created_by_user_id',
        'fo_maintenance_type_id',
        'maintainable_id',
        'maintainable_type',
        'employee_id',
        'client_id',
        'maintenance_at',
        'details',
        'notes',
        'problem_description',
        'root_cause',
        'solution_applied',
        'is_emergency',
        'problem_reported_at',
        'problem_solved_at',
        'downtime_hours',
        'reported_by_client',
        'priority',
        'contact_person',
        'contact_phone',
        'location_details',
    ];

    protected $casts = [
        'maintenance_at'       => 'datetime',
        'details'              => 'array',
        'is_emergency'         => 'boolean',
        'reported_by_client'   => 'boolean',
        'problem_reported_at'  => 'datetime',
        'problem_solved_at'    => 'datetime',
        'downtime_hours'       => 'decimal:2',
    ];

    protected static function newFactory(): FoMaintenanceRecordFactory
    {
        return FoMaintenanceRecordFactory::new();
    }

    public function maintainable()
    {
        return $this->morphTo();
    }

    public function maintenanceType()
    {
        return $this->belongsTo(FoMaintenanceType::class, 'fo_maintenance_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function client()
    {
        return $this->belongsTo(FoClient::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeCorrective($query)
    {
        return $query->whereHas('maintenanceType', fn ($q) => $q->where('code', FoMaintenanceType::CODE_CORRECTIVE));
    }

    public function scopePreventive($query)
    {
        return $query->whereHas('maintenanceType', fn ($q) => $q->where('code', FoMaintenanceType::CODE_PREVENTIVE));
    }

    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }

    public function scopeClientReported($query)
    {
        return $query->where('reported_by_client', true);
    }

    public function scopePendingClientReported($query)
    {
        return $query->clientReported()->whereNull('problem_solved_at');
    }

    public function scopeResolvedClientReported($query)
    {
        return $query->clientReported()->whereNotNull('problem_solved_at');
    }

    public function getResolutionTimeHoursAttribute(): ?float
    {
        if ($this->problem_reported_at && $this->problem_solved_at) {
            return $this->problem_reported_at->diffInHours($this->problem_solved_at, true);
        }

        return null;
    }

    public function getProblemStatusAttribute(): string
    {
        if (!$this->problem_reported_at) {
            return 'none';
        }

        return $this->problem_solved_at ? 'resolved' : 'in_progress';
    }
}
