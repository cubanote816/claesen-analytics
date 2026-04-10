<?php

namespace Modules\Cafca\Models;

use Modules\Intelligence\Models\ProjectInsight;
use Modules\Core\Traits\ReadOnlyTrait;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'project';
    protected $primaryKey = 'id';

    public function insight(): HasOne
    {
        return $this->hasOne(ProjectInsight::class, 'project_id', 'id');
    }
}
