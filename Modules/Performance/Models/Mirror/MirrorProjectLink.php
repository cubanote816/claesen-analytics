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
}
