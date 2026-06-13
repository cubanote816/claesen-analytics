<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorProjectLink extends Model
{
    protected $table = 'intelligence_mirror_project_links';
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'estimate_id',
        'link_type',
    ];

    // Composite PK (project_id, estimate_id): Eloquent's default save query
    // targets a single 'id' column that doesn't exist here, so UPDATE on an
    // existing row fails. Scope save queries to both key columns instead.
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('project_id', $this->getAttribute('project_id'))
            ->where('estimate_id', $this->getAttribute('estimate_id'));
    }
}
