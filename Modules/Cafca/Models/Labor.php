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
 
    /**
     * Scope for "Laden" (Loading/Unloading) labor entries.
     */
    public function scopeLaden($query)
    {
        return $query->where('labor_descr', 'LIKE', '%Laden%');
    }
 
    /**
     * Scope for "Werf" (On-site work) labor entries.
     */
    public function scopeWerf($query)
    {
        return $query->where('labor_descr', 'LIKE', '%Werf%');
    }
 
    /**
     * Scope for "Mobiliteit" (Mobility/Travel) labor entries.
     */
    public function scopeMobiliteit($query)
    {
        return $query->where(function ($q) {
            $q->where('labor_descr', 'LIKE', '%Mobiliteit%')
              ->orWhere('labor_descr', 'LIKE', '%Verplaatsing%');
        });
    }
}
