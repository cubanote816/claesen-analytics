<?php

namespace Modules\Cafca\Models;

use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupCost extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'followup_cost';
    protected $primaryKey = 'id';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }
}
