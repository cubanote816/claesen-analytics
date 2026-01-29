<?php

namespace App\Models\Cafca;

use App\Traits\Legacy\ReadOnlyTrait;

class Labor extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'followup_labor_analytical';
    protected $primaryKey = 'id';

    // Legacy SQL Server IDs are strings
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'hours' => 'float',
        'date' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }
}
