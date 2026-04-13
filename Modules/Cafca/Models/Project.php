<?php

namespace Modules\Cafca\Models;

use Modules\Performance\Models\ProjectInsight;
use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'project';
    protected $primaryKey = 'id';
 
    protected $casts = [
        'estimated_total_hours_to_execute' => 'float',
    ];
 
    public function insight(): HasOne
    {
        return $this->hasOne(ProjectInsight::class, 'project_id', 'id');
    }
 
    /**
     * Relationship with invoices.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'project_id', 'id');
    }
}
