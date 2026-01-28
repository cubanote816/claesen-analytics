<?php

namespace App\Models\Cafca;

use App\Models\ProjectInsight;
use App\Traits\Legacy\ReadOnlyTrait;
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
