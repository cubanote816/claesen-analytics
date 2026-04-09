<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;

class Labor extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'followup_labor_analytical';
    protected $primaryKey = 'seqnr';

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
