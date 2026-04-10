<?php

namespace Modules\Performance\Models;

use Modules\Cafca\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInsight extends Model
{
    use HasFactory;
    
    protected $connection = 'mysql';
    protected $table = 'performance_employee_insights';

    protected $fillable = [
        'employee_id',
        'archetype_label',
        'archetype_icon',
        'efficiency_trend',
        'burnout_risk_score',
        'manager_insight',
        'ai_analysis',
        'full_performance_snapshot',
        'last_data_hash',
        'last_audited_at',
    ];

    protected $casts = [
        'full_performance_snapshot' => 'json',
        'last_audited_at' => 'datetime',
        'burnout_risk_score' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
