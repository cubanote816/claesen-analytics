<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectEstimate extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'project_estimates';
    
    // Composite or non-standard PK usually, but we focus on ID/relationships
    protected $primaryKey = 'project_id';
    public $incrementing = false;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    /**
     * Relationship with the items of this estimate.
     */
    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class, 'estimate_id', 'estimate_id');
    }
}
